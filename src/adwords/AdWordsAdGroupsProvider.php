<?php

namespace sitkoru\contextcache\adwords;

use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\v201702\cm\AdGroup;
use Google\AdsApi\AdWords\v201702\cm\AdGroupService;
use Google\AdsApi\AdWords\v201702\cm\Predicate;
use Google\AdsApi\AdWords\v201702\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201702\cm\Selector;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\helpers\ArrayHelper;

class AdWordsAdGroupsProvider extends AdWordsEntitiesProvider implements IEntitiesProvider
{
    /**
     * @var AdGroupService
     */
    private $adGroupService;

    private static $fields = [
        'Id',
        'Name',
        'Status',
        'CampaignId',
        'CampaignName',
        'BiddingStrategyType',
        'CpcBid',
    ];

    public function __construct(
        AdGroupService $adGroupService,
        ICacheProvider $cacheProvider,
        AdWordsSession $adWordsSession
    ) {
        parent::__construct($cacheProvider, $adWordsSession);
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
        $adGroups = $this->getFromCache($ids, 'id');
        if ($adGroups) {
            $found = array_keys($adGroups);
            $notFound = array_values(array_diff($ids, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('Id', PredicateOperator::IN, $ids);
            $selector->setPredicates($predicates);
            $fromService = (array)$this->adGroupService->get($selector)->getEntries();
            foreach ($fromService as $adGroupItem) {
                $adGroups[$adGroupItem->getId()] = $adGroupItem;
            }
            $this->addToCache($fromService);
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
        $adGroups = $this->getAll([$id]);
        if ($adGroups) {
            return reset($adGroups);
        }
        return null;
    }

    /**
     * @param array $campaignIds
     * @return AdGroup[]
     * @throws \Google\AdsApi\AdWords\v201702\cm\ApiException
     */
    public function getByCampaignIds(array $campaignIds): array
    {
        $notFound = $campaignIds;
        /**
         * @var AdGroup[] $adGroups
         */
        $adGroups = $this->getFromCache($campaignIds, 'campaignId', 'id');
        if ($adGroups) {
            $found = array_unique(ArrayHelper::getColumn($adGroups, 'campaignId'));
            $notFound = array_values(array_diff($campaignIds, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('CampaignId', PredicateOperator::IN, $notFound);
            $selector->setPredicates($predicates);
            $fromService = (array)$this->adGroupService->get($selector)->getEntries();
            foreach ($fromService as $adGroupItem) {
                $adGroups[$adGroupItem->getId()] = $adGroupItem;
            }
            $this->addToCache($fromService);
        }
        return $adGroups;
    }

    /**
     * @param AdGroup[] $entities
     * @return bool
     * @throws \Exception
     */
    public function update(array $entities): bool
    {
        return false;
    }
}