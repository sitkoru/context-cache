<?php

namespace sitkoru\contextcache\adwords;

use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\BatchJobs\v201702\BatchJobs;
use Google\AdsApi\AdWords\v201702\cm\ApiError;
use Google\AdsApi\AdWords\v201702\cm\BatchJob;
use Google\AdsApi\AdWords\v201702\cm\BatchJobOperation;
use Google\AdsApi\AdWords\v201702\cm\BatchJobService;
use Google\AdsApi\AdWords\v201702\cm\BatchJobStatus;
use Google\AdsApi\AdWords\v201702\cm\MutateResult;
use Google\AdsApi\AdWords\v201702\cm\Operand;
use Google\AdsApi\AdWords\v201702\cm\Operator;
use Google\AdsApi\AdWords\v201702\cm\PolicyViolationError;
use Google\AdsApi\AdWords\v201702\cm\Predicate;
use Google\AdsApi\AdWords\v201702\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201702\cm\Selector;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\common\EntitiesProvider;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use SoapFault;
use UnexpectedValueException;

define('ADWORDS_POLL_FREQUENCY_SECONDS', 10);
define('MAX_POLL_ATTEMPTS', 60);
define('MAX_POLL_FREQUENCY', 60);
define('MAX_OPERATIONS_SIZE', 2000);

abstract class AdWordsEntitiesProvider extends EntitiesProvider
{
    /**
     * @var BatchJobService
     */
    private $batchJobService;
    /**
     * @var AdWordsSession
     */
    private $adWordsSession;

    public function __construct(ICacheProvider $cacheProvider, AdWordsSession $adWordsSession, LoggerInterface $logger)
    {
        parent::__construct($cacheProvider, $logger);
        $this->serviceKey = 'google';
        $this->adWordsSession = $adWordsSession;
        $this->batchJobService = (new AdWordsServices())->get($adWordsSession, BatchJobService::class);
    }

    protected function hasChanges($ids): bool
    {
        $ts = $this->getLastCacheTimestamp();
        return !$ts || $ts < time() - 60 * 30;
    }

    /**
     * @param $operations
     *
     * @return bool|MutateResult[]
     * @throws \Google\AdsApi\AdWords\v201702\cm\ApiException
     * @throws \UnexpectedValueException
     */
    public function runMutateJob($operations)
    {
        $addOp = new BatchJobOperation();
        $addOp->setOperator(Operator::ADD);
        $addOp->setOperand(new BatchJob());

        try {
            /**
             * @var BatchJob $job
             */
            $result = $this->batchJobService->mutate([$addOp]);
            $job = $result->getValue()[0];
        } catch (SoapFault $ex) {

            return false;
        }

        $uploadUrl = $job->getUploadUrl()->getUrl();

        $batchJobs = new BatchJobs($this->adWordsSession);
        try {
            $batchJobs->uploadBatchJobOperations($operations, $uploadUrl);
        } catch (\Throwable $ex) {
            $this->logger->error('Произошла ошибка при загрузке данных в Google AdWords. Попробуйте ещё раз немного позже');

            return false;
        }

        // Poll for completion of the batch job using an exponential back off.
        $pollAttempts = 0;
        $isPending = true;
        do {
            $sleepSeconds = ADWORDS_POLL_FREQUENCY_SECONDS * (2 ** $pollAttempts);
            if ($sleepSeconds > MAX_POLL_FREQUENCY) {
                $sleepSeconds = MAX_POLL_FREQUENCY;
            }
            $this->logger->debug("Sleeping {$sleepSeconds} seconds...");
            sleep($sleepSeconds);
            $selector = new Selector();
            $selector->setFields([
                'Id',
                'Status',
                'DownloadUrl',
                'ProcessingErrors',
                'ProgressStats'
            ]);
            $selector->setPredicates([new Predicate('Id', PredicateOperator::EQUALS, [$job->getId()])]);
            $batchJob = $this->batchJobService->get($selector)->getEntries()[0];
            $this->logger->debug('Batch job ID ' . $batchJob->getId() . " has status '{$batchJob->getStatus()}'");
            $pollAttempts++;
            $status = $batchJob->getStatus();
            if ($status !== BatchJobStatus::ACTIVE &&
                $status !== BatchJobStatus::AWAITING_FILE &&
                $status !== BatchJobStatus::CANCELING
            ) {
                $isPending = false;
            }
        } while ($isPending && $pollAttempts <= MAX_POLL_ATTEMPTS);
        if ($isPending) {
            throw new UnexpectedValueException(
                sprintf('Job is still pending state after polling %d times.',
                    MAX_POLL_ATTEMPTS));
        }
        if ($batchJob->getProcessingErrors() !== null) {
            $i = 0;
            foreach ($batchJob->getProcessingErrors() as $processingError) {
                $this->logger->error(
                    " Processing error [{$i}]: errorType={$processingError->getApiErrorType()}, trigger={$processingError->getTrigger()}, errorString={$processingError->getErrorString()},"
                    . " fieldPath={$processingError->getFieldPath()}, reason={$processingError->getReason()}"
                );
            }
        }
        if ($batchJob->getDownloadUrl() !== null
            && $batchJob->getDownloadUrl()->getUrl() !== null
        ) {
            $mutateResults = $batchJobs->downloadBatchJobResults(
                $batchJob->getDownloadUrl()->getUrl());
            $this->logger->info("Downloaded results from {$batchJob->getDownloadUrl()->getUrl()}:");

            if (count($mutateResults) === 0) {
                $this->logger->info('No results available.');
            } else {
                return $mutateResults;
            }
        } else {
            $this->logger->info('No results available for download.');
        }

        return false;
    }

    /**
     * @param MutateResult[] $jobResult
     *
     * @return array
     * @throws \ErrorException
     */
    protected function processErrors($jobResult)
    {
        $skipped = [];
        $failed = [];
        $errors = [];
        $genericErrors = [];
        if (!is_array($jobResult)) {
            throw new \ErrorException('Empty result from google');
        }

        foreach ($jobResult as $mutateResult) {
            if ($mutateResult->getErrorList()) {
                foreach ($mutateResult->getErrorList()->getErrors() as $error) {
                    if (is_array($error)) {
                        $error = $error[0];
                    }

                    $index = self::getSourceOperationIndex($error);
                    if ($index !== null) {
                        $type = explode('.', $error->getErrorString())[1];
                        switch ($type) {
                            case 'UNPROCESSED_RESULT':
                            case 'BATCH_FAILURE':
                                $skipped[] = $index;
                                break;
                            default:
                                if (!in_array($index, $failed, true)) {
                                    $failed[] = $index;
                                }
                                $errors[$index][] = $error;
                        }
                    } else {
                        $genericErrors[] = $error;
                    }
                }
            }
        }
        return [$skipped, $failed, $errors, $genericErrors];
    }

    /**
     * Get the index of source operation that failed.
     * @param ApiError $error
     * @return mixed|null
     */
    private static function getSourceOperationIndex(ApiError $error)
    {
        $matches = [];
        // Get the index of operations that has a problem.
        if (preg_match('/^operations\[(\d+)\]/', $error->getFieldPath(),
            $matches)) {
            return $matches[1];
        }
        // No field path was returned from the server.
        return null;
    }

    /**
     * @param UpdateResult   $jobResult
     * @param MutateResult[] $mutateResults
     * @return UpdateResult
     */
    protected function processJobResult(UpdateResult $jobResult, $mutateResults): UpdateResult
    {
        /**
         * @var int[]   $failed
         * @var int[]   $skipped
         * @var array[] $errors
         */
        [$skipped, $failed, $errors] = $this->processErrors($mutateResults);

        $succeeded = [];
        foreach ($mutateResults as $i => $mutateResult) {
            $entryResult = $mutateResult->getResult();
            if (!$entryResult) {
                continue;
            }
            $result = $this->getOperandEntity($entryResult);
            if ($result) {
                $succeeded[$i] = $result;
            }
        }

        // Display the results of the job.
        $this->logger->info(sprintf('%d entities were added/updated successfully', count($succeeded)));

        $this->logger->info(sprintf("%d entities were skipped and should be retried: %s\n", count($skipped),
                implode(', ', $skipped)
            )
        );
        if (count($failed)) {
            $jobResult->success = false;
        }
        $this->logger->error(sprintf("%d entities were not added due to errors:\n", count($failed)));
        foreach ($failed as $errorIndex) {
            $text = "Entity {$errorIndex} errors:" . PHP_EOL;
            foreach ($errors[$errorIndex] as $i => $error) {
                /**
                 * @var ApiError $error
                 */
                $path = substr($error->getFieldPath(), strrpos($error->getFieldPath(), '.') + 1);
                switch (true) {
                    case $error instanceof PolicyViolationError:
                        /**
                         * @var PolicyViolationError $error
                         */
                        $text .= '<strong>' . $error->getExternalPolicyName() . '</strong> in <strong>'
                            . $path . '</strong>:<br />' . $error->getExternalPolicyDescription() . '<br/>';
                        break;
                    case $error instanceof ApiError:
                        $text .= $error->getErrorString();
                        break;
                    default:
                        $text .= json_encode($error);
                        break;
                }
            }
            $jobResult->errors[$errorIndex][$i] = $text;
            $this->logger->error($text);
        }

        return $jobResult;
    }

    protected abstract function getOperandEntity(Operand $operand);
}