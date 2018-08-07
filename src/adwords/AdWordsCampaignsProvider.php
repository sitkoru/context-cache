<?php

namespace sitkoru\contextcache\adwords;


use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\v201802\cm\Campaign;
use Google\AdsApi\AdWords\v201802\cm\CampaignOperation;
use Google\AdsApi\AdWords\v201802\cm\CampaignService;
use Google\AdsApi\AdWords\v201802\cm\Operand;
use Google\AdsApi\AdWords\v201802\cm\Operator;
use Google\AdsApi\AdWords\v201802\cm\Predicate;
use Google\AdsApi\AdWords\v201802\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201802\cm\Selector;
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;

class AdWordsCampaignsProvider extends AdWordsEntitiesProvider implements IEntitiesProvider
{
    /**
     * @var CampaignService
     */
    private $campaignService;

    /**
     * @var array
     */
    private static $fields = [
        'AdvertisingChannelSubType',
        'AdvertisingChannelType',
        'Amount',
        'BaseCampaignId',
        'BidCeiling',
        'BidType',
        'BiddingStrategyId',
        'BiddingStrategyName',
        'BiddingStrategyType',
        'BudgetId',
        'BudgetName',
        'BudgetReferenceCount',
        'BudgetStatus',
        'CampaignTrialType',
        'DeliveryMethod',
        'Eligible',
        'EndDate',
        'FrequencyCapMaxImpressions',
        'Id',
        'IsBudgetExplicitlyShared',
        'Labels',
        'Level',
        'Name',
        'PricingMode',
        'RejectionReasons',
        'SelectiveOptimization',
        'ServingStatus',
        'Settings',
        'StartDate',
        'Status',
        'TargetContentNetwork',
        'TargetCpa',
        'TargetCpaMaxCpcBidCeiling',
        'TargetCpaMaxCpcBidFloor',
        'TargetGoogleSearch',
        'TargetPartnerSearchNetwork',
        'TargetRoas',
        'TargetRoasBidCeiling',
        'TargetRoasBidFloor',
        'TargetSearchNetwork',
        'TargetSpendBidCeiling',
        'TargetSpendSpendTarget',
        'TimeUnit',
        'TrackingUrlTemplate',
        'UrlCustomParameters',
        'VanityPharmaDisplayUrlMode',
        'VanityPharmaText'
    ];


    public function __construct(
        CampaignService $campaignService,
        ICacheProvider $cacheProvider,
        AdWordsSession $adWordsSession,
        ContextEntitiesLogger $logger
    ) {
        parent::__construct($cacheProvider, $adWordsSession, $logger);
        $this->collection = 'campaigns';
        $this->campaignService = $campaignService;
        $this->keyField = 'id';
    }

    /**
     * @param array $ids
     * @return Campaign[]
     * @throws \Google\AdsApi\AdWords\v201802\cm\ApiException
     */
    public function getAll(array $ids): array
    {
        $notFound = $ids;
        /**
         * @var Campaign[] $campaigns
         */
        $campaigns = $this->getFromCache($ids, 'id');
        if ($campaigns) {
            $found = array_keys($campaigns);
            $notFound = array_values(array_diff($ids, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates = [new Predicate('Id', PredicateOperator::IN, $ids)];
            $selector->setPredicates($predicates);
            $fromService = (array)$this->campaignService->get($selector)->getEntries();
            foreach ($fromService as $campaignItem) {
                $campaigns[$campaignItem->getId()] = $campaignItem;
            }
            $this->addToCache($fromService);
        }
        return $campaigns;
    }

    /**
     * @param int $id
     * @return Campaign
     * @throws \Google\AdsApi\AdWords\v201802\cm\ApiException
     */
    public function getOne($id): ?Campaign
    {
        $campaigns = $this->getAll([$id]);
        if ($campaigns) {
            return reset($campaigns);
        }
        return null;
    }

    /**
     * @return array
     * @throws \Google\AdsApi\AdWords\v201802\cm\ApiException
     */
    public function getForService(): array
    {
        $campaigns = [];
        $selector = new Selector();
        $selector->setFields(self::$fields);
        $fromService = (array)$this->campaignService->get($selector)->getEntries();
        foreach ($fromService as $campaignItem) {
            $campaigns[$campaignItem->getId()] = $campaignItem;
        }
        return $campaigns;
    }

    /**
     * @param Campaign[] $entities
     * @return UpdateResult
     * @throws \ErrorException
     * @throws \Google\AdsApi\AdWords\v201802\cm\ApiException
     * @throws \UnexpectedValueException
     * @throws AdWordsBatchJobCancelledException
     */
    public function update(array $entities): UpdateResult
    {
        $result = new UpdateResult();
        $addOperations = [];
        $this->logger->info('Build operations');
        foreach ($entities as $entity) {
            $addOperation = new CampaignOperation();
            $addOperation->setOperand($entity);
            $addOperation->setOperator(Operator::SET);
            $addOperations[] = $addOperation;
        }
        $this->logger->info('Update operations: ' . \count($addOperations));

        foreach (array_chunk($addOperations, self::MAX_OPERATIONS_SIZE) as $i => $addChunk) {
            $this->logger->info('Update chunk #' . $i . '. Size: ' . \count($addChunk));
            $jobResults = $this->runMutateJob($addChunk);
            $this->processJobResult($result, $jobResults);
        }
        $this->logger->info('Done');
        $this->clearCache();
        return $result;
    }

    /**
     * @param Operand $operand
     * @return Campaign|mixed
     */
    protected function getOperandEntity(Operand $operand)
    {
        return $operand->getCampaign();
    }
}