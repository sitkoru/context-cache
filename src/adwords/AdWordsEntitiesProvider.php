<?php

namespace sitkoru\contextcache\adwords;

use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\BatchJobs\v201702\BatchJobs;
use Google\AdsApi\AdWords\v201702\cm\BatchJob;
use Google\AdsApi\AdWords\v201702\cm\BatchJobOperation;
use Google\AdsApi\AdWords\v201702\cm\BatchJobService;
use Google\AdsApi\AdWords\v201702\cm\BatchJobStatus;
use Google\AdsApi\AdWords\v201702\cm\MutateResult;
use Google\AdsApi\AdWords\v201702\cm\Operator;
use Google\AdsApi\AdWords\v201702\cm\Predicate;
use Google\AdsApi\AdWords\v201702\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201702\cm\Selector;
use sitkoru\contextcache\common\EntitiesProvider;
use sitkoru\contextcache\common\ICacheProvider;
use SoapFault;
use UnexpectedValueException;

define('POLL_FREQUENCY_SECONDS', 10);
define('MAX_POLL_ATTEMPTS', 60);

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

    public function __construct(ICacheProvider $cacheProvider, AdWordsSession $adWordsSession)
    {
        parent::__construct($cacheProvider);
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
     * @throws \Google\AdsApi\AdWords\v201609\cm\ApiException
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

            return false;
        }


        // Poll for completion of the batch job using an exponential back off.
        $pollAttempts = 0;
        $isPending = true;
        do {
            $sleepSeconds = POLL_FREQUENCY_SECONDS * (2 ** $pollAttempts);
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
                /*LogHelper::log(
                    " Processing error [{$i}]: errorType={$processingError->getApiErrorType()}, trigger={$processingError->getTrigger()}, errorString={$processingError->getErrorString()},"
                    . " fieldPath={$processingError->getFieldPath()}, reason={$processingError->getReason()}" . PHP_EOL
                );*/
            }
        }
        if ($batchJob->getDownloadUrl() !== null
            && $batchJob->getDownloadUrl()->getUrl() !== null
        ) {
            $mutateResults = $batchJobs->downloadBatchJobResults(
                $batchJob->getDownloadUrl()->getUrl());
            printf("Downloaded results from %s:\n",
                $batchJob->getDownloadUrl()->getUrl());

            if (count($mutateResults) === 0) {
                printf("  No results available.\n");
            } else {
                return $mutateResults;
            }
        } else {
            printf("No results available for download.\n");
        }

        return false;
    }
}