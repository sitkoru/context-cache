<?php

namespace sitkoru\contextcache\adwords;


use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\v201702\cm\Campaign;
use Google\AdsApi\AdWords\v201702\cm\CampaignService;
use Google\AdsApi\AdWords\v201702\cm\Operand;
use Google\AdsApi\AdWords\v201702\cm\Predicate;
use Google\AdsApi\AdWords\v201702\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201702\cm\Selector;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;

class AdWordsCampaignsProvider extends AdWordsEntitiesProvider implements IEntitiesProvider
{
    /**
     * @var CampaignService
     */
    private $campaignService;

    private static $fields = [
        'AdServingOptimizationStatus',
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
        'EnhancedCpcEnabled',
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
        'TargetSpendEnhancedCpcEnabled',
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
        LoggerInterface $logger
    ) {
        parent::__construct($cacheProvider, $adWordsSession, $logger);
        $this->collection = 'campaigns';
        $this->campaignService = $campaignService;
    }

    /**
     * @param array $ids
     * @return Campaign[]
     * @throws \Google\AdsApi\AdWords\v201702\cm\ApiException
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
            $predicates[] = new Predicate('Id', PredicateOperator::IN, $ids);
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
     * @param $id
     * @return Campaign
     * @throws \Google\AdsApi\AdWords\v201702\cm\ApiException
     */
    public function getOne($id): Campaign
    {
        $campaigns = $this->getAll([$id]);
        if ($campaigns) {
            return reset($campaigns);
        }
        return null;
    }

    /**
     * @return array
     * @throws \Google\AdsApi\AdWords\v201702\cm\ApiException
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

    public function update(array $entities): UpdateResult
    {
        return new UpdateResult();
    }

    protected function getOperandEntity(Operand $operand)
    {
        return $operand->getCampaign();
    }
}