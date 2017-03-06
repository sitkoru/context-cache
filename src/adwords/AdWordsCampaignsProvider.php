<?php

namespace sitkoru\contextcache\adwords;


use Google\AdsApi\AdWords\v201702\cm\Campaign;
use Google\AdsApi\AdWords\v201702\cm\CampaignService;
use Google\AdsApi\AdWords\v201702\cm\Predicate;
use Google\AdsApi\AdWords\v201702\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201702\cm\Selector;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;

class AdWordsCampaignsProvider extends AdWordsEntitiesProvider implements IEntitiesProvider
{
    /**
     * @var CampaignService
     */
    private $campaignService;

    private static $fields = [
        'Id',
        'Name',
        'Status',
        'ServingStatus',
        'StartDate',
        'EndDate',
        'Amount',
        'BudgetId',
        'TargetGoogleSearch',
        'TargetSearchNetwork',
        'TargetContentNetwork',
        'TargetPartnerSearchNetwork',
        'BiddingStrategyId',
        'AdvertisingChannelType'
    ];

    public function __construct(CampaignService $campaignService, ICacheProvider $cacheProvider)
    {
        parent::__construct($cacheProvider);
        $this->collection = 'campaigns';
        $this->campaignService = $campaignService;
    }

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
            $fromService = $this->campaignService->get($selector);
            foreach ($fromService->getEntries() as $campaignItem) {
                $campaigns[$campaignItem->getId()] = $campaignItem;
            }
            $this->addToCache($fromService->getEntries());
        }
        return $campaigns;
    }

    public function getOne($id): Campaign
    {
        $campaigns = $this->getAll([$id]);
        if ($campaigns) {
            return reset($campaigns);
        }
        return null;
    }
}