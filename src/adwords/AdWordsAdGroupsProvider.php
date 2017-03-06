<?php

namespace sitkoru\contextcache\adwords;

use Google\AdsApi\AdWords\v201702\cm\AdGroup;
use Google\AdsApi\AdWords\v201702\cm\AdGroupService;
use Google\AdsApi\AdWords\v201702\cm\Predicate;
use Google\AdsApi\AdWords\v201702\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201702\cm\Selector;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;

class AdWordsAdGroupsProvider extends AdWordsEntitiesProvider implements IEntitiesProvider
{
    /**
     * @var AdGroupService
     */
    private $adGroupService;

    public function __construct(AdGroupService $adGroupService, ICacheProvider $cacheProvider)
    {
        parent::__construct($cacheProvider);
        $this->collection = 'adGroups';
        $this->adGroupService = $adGroupService;
    }

    /**
     * @param array $ids
     * @return AdGroup[]
     * @throws \Exception
     */
    public function getAll(array $ids): array
    {
        $notFound = $ids;
        /**
         * @var AdGroup[] $adGroups
         */
        $adGroups = $this->getFromCache('id', $ids, AdGroup::class);
        if ($adGroups) {
            $found = array_keys($adGroups);
            $notFound = array_values(array_diff($ids, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields([
                'Id',
                'Name',
                'Status',
                'CampaignId',
                'CampaignName',
                'BiddingStrategyType',
                'CpcBid',
            ]);
            $predicates[] = new Predicate('Id', PredicateOperator::IN, $ids);
            $selector->setPredicates($predicates);
            $fromService = $this->adGroupService->get($selector);
            foreach ($fromService->getEntries() as $adGroupItem) {
                $adGroups[$adGroupItem->getId()] = $adGroupItem;
            }
            $this->addToCache($fromService->getEntries());
        }
        return $adGroups;
    }

    /**
     * @param $id
     * @return AdGroup|null
     * @throws \Exception
     */
    public function getOne($id): AdGroup
    {
        $ads = $this->getAll([$id]);
        if ($ads) {
            return reset($ads);
        }
        return null;
    }
}