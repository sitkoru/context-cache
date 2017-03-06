<?php

namespace sitkoru\contextcache\adwords;


use Google\AdsApi\AdWords\v201609\cm\AdGroupAd;
use Google\AdsApi\AdWords\v201702\cm\AdGroupAdService;
use Google\AdsApi\AdWords\v201702\cm\Predicate;
use Google\AdsApi\AdWords\v201702\cm\PredicateOperator;
use Google\AdsApi\AdWords\v201702\cm\Selector;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\helpers\ArrayHelper;

class AdWordsAdsProvider extends AdWordsEntitiesProvider implements IEntitiesProvider
{
    /**
     * @var AdGroupAdService
     */
    private $adGroupAdService;

    private static $fields = [
        'Id',
        'Name',
        'Url',
        'CreativeFinalUrls',
        'AdType'
    ];

    public function __construct(AdGroupAdService $adGroupService, ICacheProvider $cacheProvider)
    {
        parent::__construct($cacheProvider);
        $this->collection = 'adGroupAds';
        $this->adGroupAdService = $adGroupService;
    }

    public function getAll(array $ids): array
    {
        $notFound = $ids;
        /**
         * @var AdGroupAd[] $adGroupAds
         */
        $adGroupAds = $this->getFromCache($ids, 'ad.id');
        if ($adGroupAds) {
            $found = array_keys($adGroupAds);
            $notFound = array_values(array_diff($ids, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('Id', PredicateOperator::IN, $ids);
            $selector->setPredicates($predicates);
            $fromService = $this->adGroupAdService->get($selector);
            foreach ($fromService->getEntries() as $adGroupAdItem) {
                $adGroupAds[$adGroupAdItem->getAd()->getId()] = $adGroupAdItem;
            }
            $this->addToCache($fromService->getEntries());
        }
        return $adGroupAds;
    }

    public function getOne($id): AdGroupAd
    {
        $ads = $this->getAll([$id]);
        if ($ads) {
            return reset($ads);
        }
        return null;
    }

    public function getByAdGroupIds(array $adGroupIds): array
    {
        $notFound = $adGroupIds;

        /**
         * @var AdGroupAd[] $adGroupAds
         */
        $adGroupAds = $this->getFromCache($adGroupIds, 'adGroupId', 'ad.id');
        if ($adGroupAds) {
            $found = array_unique(ArrayHelper::getColumn($adGroupAds, 'adGroupId'));
            $notFound = array_values(array_diff($adGroupIds, $found));
        }
        if ($notFound) {
            $selector = new Selector();
            $selector->setFields(self::$fields);
            $predicates[] = new Predicate('AdGroupId', PredicateOperator::IN, $notFound);
            $selector->setPredicates($predicates);
            $fromService = $this->adGroupAdService->get($selector);
            foreach ($fromService->getEntries() as $adGroupAdItem) {
                $adGroupAds[$adGroupAdItem->getAd()->getId()] = $adGroupAdItem;
            }
            $this->addToCache((array)$fromService->getEntries());
        }
        return $adGroupAds;
    }
}