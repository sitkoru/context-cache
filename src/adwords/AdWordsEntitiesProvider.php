<?php

namespace sitkoru\contextcache\adwords;

use function count;
use ErrorException;
use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\BatchJobs\v201809\BatchJobs;
use Google\AdsApi\AdWords\v201809\cm\ApiError;
use Google\AdsApi\AdWords\v201809\cm\ApiException;
use Google\AdsApi\AdWords\v201809\cm\BatchJob;
use Google\AdsApi\AdWords\v201809\cm\BatchJobOperation;
use Google\AdsApi\AdWords\v201809\cm\BatchJobService;
use Google\AdsApi\AdWords\v201809\cm\BatchJobStatus;
use Google\AdsApi\AdWords\v201809\cm\ErrorList;
use Google\AdsApi\AdWords\v201809\cm\MutateResult;
use Google\AdsApi\AdWords\v201809\cm\Operand;
use Google\AdsApi\AdWords\v201809\cm\Operator;
use Google\AdsApi\AdWords\v201809\cm\PolicyViolationError;
use Google\AdsApi\AdWords\v201809\cm\Predicate;
use Google\AdsApi\AdWords\v201809\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201809\cm\Selector;
use Google\AdsApi\AdWords\v201809\cm\TemporaryUrl;
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\EntitiesProvider;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use SoapFault;
use Throwable;
use UnexpectedValueException;

abstract class AdWordsEntitiesProvider extends EntitiesProvider
{
    public const POLL_FREQUENCY_SECONDS = 10;
    public const MAX_POLL_ATTEMPTS = 60;
    public const MAX_POLL_FREQUENCY = 60;
    public const MAX_OPERATIONS_SIZE = 2000;
    public const MAX_RESPONSE_COUNT = 200;

    /**
     * @var BatchJobService
     */
    private $batchJobService;

    /**
     * @var AdWordsSession
     */
    private $adWordsSession;

    public function __construct(
        ICacheProvider $cacheProvider,
        AdWordsSession $adWordsSession,
        ContextEntitiesLogger $logger
    ) {
        parent::__construct($cacheProvider, $logger);
        $this->serviceKey = 'google';
        $this->adWordsSession = $adWordsSession;
        /**
         * @var BatchJobService
         */
        $batchJobService = (new AdWordsServices())->get($adWordsSession, BatchJobService::class);
        $this->batchJobService = $batchJobService;
    }

    protected function hasChanges(array $ids): bool
    {
        $ts = $this->getLastCacheTimestamp();
        return $ts === 0 || $ts < time() - 60 * 30;
    }

    /**
     * @param array $operations
     *
     * @return bool|MutateResult[]
     *
     * @throws ApiException
     * @throws UnexpectedValueException
     * @throws AdWordsBatchJobCancelledException
     * @throws ErrorException
     */
    public function runMutateJob(array $operations)
    {
        $addOp = new BatchJobOperation();
        $addOp->setOperator(Operator::ADD);
        $addOp->setOperand(new BatchJob());

        try {
            $result = $this->batchJobService->mutate([$addOp]);
            /**
             * @var BatchJob $job
             */
            $job = $result->getValue()[0];
        } catch (SoapFault $ex) {
            return false;
        }

        $uploadUrl = $job->getUploadUrl()->getUrl();

        $batchJobs = new BatchJobs($this->adWordsSession);
        try {
            $batchJobs->uploadBatchJobOperations($operations, $uploadUrl);
        } catch (Throwable $ex) {
            throw new ErrorException(
                'Произошла ошибка при загрузке данных в Google AdWords. Попробуйте ещё раз немного позже',
                $ex->getCode(),
                1,
                __FILE__,
                __LINE__,
                $ex
            );
        }

        // Poll for completion of the batch job using an exponential back off.
        $pollAttempts = 0;
        $isPending = true;
        do {
            $sleepSeconds = self::POLL_FREQUENCY_SECONDS * (2 ** $pollAttempts);
            if ($sleepSeconds > self::MAX_POLL_FREQUENCY) {
                $sleepSeconds = self::MAX_POLL_FREQUENCY;
            }
            $this->logger->debug("Sleeping {$sleepSeconds} seconds...");
            sleep((int)$sleepSeconds);
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
            $this->logger->info('Batch job ID ' . $batchJob->getId() . " has status '{$batchJob->getStatus()}'. Last sleep {$sleepSeconds}");
            $this->logger->debug('Batch job ID ' . $batchJob->getId() . " has status '{$batchJob->getStatus()}'");
            $pollAttempts++;
            $status = $batchJob->getStatus();
            if ($status !== BatchJobStatus::ACTIVE &&
                $status !== BatchJobStatus::AWAITING_FILE &&
                $status !== BatchJobStatus::CANCELING
            ) {
                $isPending = false;
            }
        } while ($isPending && $pollAttempts <= self::MAX_POLL_ATTEMPTS);
        if ($isPending) {
            throw new UnexpectedValueException(
                sprintf(
                    'Job is still pending state after polling %d times.',
                    self::MAX_POLL_ATTEMPTS
                )
            );
        }
        if ($batchJob->getStatus() === BatchJobStatus::CANCELED) {
            throw new AdWordsBatchJobCancelledException('Task was cancelled');
        }
        if (count($batchJob->getProcessingErrors()) > 0) {
            $i = 0;
            foreach ($batchJob->getProcessingErrors() as $processingError) {
                $this->logger->error(
                    " Processing error [{$i}]: errorType={$processingError->getApiErrorType()}, trigger={$processingError->getTrigger()}, errorString={$processingError->getErrorString()},"
                    . " fieldPath={$processingError->getFieldPath()}, reason={$processingError->getReason()}"
                );
            }
        }
        /**
         * @var TemporaryUrl|null
         */
        $tempUrl = $batchJob->getDownloadUrl();
        if ($tempUrl !== null) {
            $mutateResults = $batchJobs->downloadBatchJobResults($batchJob->getDownloadUrl()->getUrl());
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
     *
     * @throws ErrorException
     */
    protected function processErrors($jobResult): array
    {
        $skipped = [];
        $failed = [];
        $errors = [];
        $genericErrors = [];
        if (!is_array($jobResult)) {
            throw new ErrorException('Empty result from google');
        }

        foreach ($jobResult as $mutateResult) {
            /**
             * @var ErrorList|null
             */
            $errorList = $mutateResult->getErrorList();
            if ($errorList !== null) {
                foreach ($errorList->getErrors() as $error) {
                    /**
                     * @var ApiError|array $error
                     */
                    if (is_array($error)) {
                        $error = $error[0];
                    }

                    [$skipped, $failed, $errors, $genericErrors] = $this->fillErrors(
                        $error,
                        $skipped,
                        $failed,
                        $errors,
                        $genericErrors
                    );
                }
            }
        }
        return [$skipped, $failed, $errors, $genericErrors];
    }

    /**
     * Get the index of source operation that failed.
     *
     * @param ApiError $error
     *
     * @return mixed|null
     */
    private static function getSourceOperationIndex(ApiError $error)
    {
        $matches = [];
        // Get the index of operations that has a problem.
        if (preg_match(
            '/^operations\[(\d+)]/',
            $error->getFieldPath(),
            $matches
        ) === 1) {
            return $matches[1];
        }
        // No field path was returned from the server.
        return null;
    }

    protected function processMutateResult(
        UpdateResult $result,
        array $operations,
        array $entities = [],
        ?array $mutateErrors = []
    ): UpdateResult {
        /**
         * @var int[]   $failed
         * @var int[]   $skipped
         * @var array[] $errors
         */
        [$skipped, $failed, $errors] = $this->processMutateErrors($mutateErrors);
        $succeeded = [];
        foreach ($operations as $k => $operation) {
            if (in_array($k, $failed, true) || in_array($k, $skipped, true)) {
                continue;
            }

            $entry = $entities[$k];
            if (!$entry) {
                continue;
            }
            $succeeded[$k] = $entry;
        }

        $this->logJobResult($result, $succeeded, $skipped, $failed, $errors);

        return $result;
    }

    /**
     * @param ApiError[] $mutateErrors
     *
     * @return array
     */
    protected function processMutateErrors(?array $mutateErrors = []): array
    {
        $skipped = [];
        $failed = [];
        $errors = [];
        $genericErrors = [];

        if ($mutateErrors !== null && count($mutateErrors) > 0) {
            foreach ($mutateErrors as $mutateError) {
                [$skipped, $failed, $errors, $genericErrors] = $this->fillErrors(
                    $mutateError,
                    $skipped,
                    $failed,
                    $errors,
                    $genericErrors
                );
            }
        }

        return [$skipped, $failed, $errors, $genericErrors];
    }

    /**
     * @param UpdateResult   $jobResult
     * @param MutateResult[] $mutateResults
     *
     * @return UpdateResult
     *
     * @throws ErrorException
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
            /**
             * @var Operand|null
             */
            $entryResult = $mutateResult->getResult();
            if ($entryResult === null) {
                continue;
            }
            $result = $this->getOperandEntity($entryResult);
            if ($result !== null) {
                $succeeded[$i] = $result;
            }
        }

        $this->logJobResult($jobResult, $succeeded, $skipped, $failed, $errors);

        return $jobResult;
    }

    /**
     * @param callable $request
     * @param int      $try
     * @param int      $maxTry
     *
     * @return mixed
     *
     * @throws ApiException
     */
    protected function doRequest(callable $request, int $try = 0, int $maxTry = 10)
    {
        $try++;
        try {
            return $request();
        } catch (ApiException $ex) {
            if (stripos($ex->getMessage(), 'UNEXPECTED_INTERNAL_API_ERROR ') !== false) {
                if ($try <= $maxTry) {
                    $this->logger->warning('Internal google error. Wait 30 seconds and retry');
                    sleep(30);
                    return $this->doRequest($request);
                }
                $this->logger->warning('Internal google error after ' . $maxTry . ' retries');
            }
            throw $ex;
        }
    }

    /**
     * @param Operand $operand
     *
     * @return mixed
     */
    abstract protected function getOperandEntity(Operand $operand);

    /**
     * @param UpdateResult $result
     * @param array        $succeeded
     * @param array        $skipped
     * @param array        $failed
     * @param array        $errors
     */
    protected function logJobResult(
        UpdateResult $result,
        array $succeeded,
        array $skipped,
        array $failed,
        array $errors
    ): void {
        // Display the results of the job.
        $this->logger->info(sprintf('%d entities were added/updated successfully', count($succeeded)));

        $this->logger->info(
            sprintf(
                "%d entities were skipped and should be retried: %s\n",
                count($skipped),
                implode(', ', $skipped)
            )
        );
        if (count($failed) > 0) {
            $result->success = false;
        }
        $this->logger->error(sprintf("%d entities were not added due to errors:\n", count($failed)));
        foreach ($failed as $errorIndex) {
            $text = "Entity {$errorIndex} errors:" . PHP_EOL;
            foreach ($errors[$errorIndex] as $i => $error) {
                $path = substr($error->getFieldPath(), (int)strrpos($error->getFieldPath(), '.') + 1);
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
            $result->errors[$errorIndex][0] = $text;
            $this->logger->error($text);
        }
    }

    /**
     * @param ApiError $error
     * @param array    $skipped
     * @param array    $failed
     * @param array    $errors
     * @param array    $genericErrors
     *
     * @return array
     */
    protected function fillErrors(
        ApiError $error,
        array $skipped,
        array $failed,
        array $errors,
        array $genericErrors
    ): array {
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
        return [$skipped, $failed, $errors, $genericErrors];
    }
}
