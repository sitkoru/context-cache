<?php

namespace sitkoru\contextcache\yandex;

use directapi\DirectApiService;
use directapi\services\ads\criterias\AdsSelectionCriteria;
use directapi\services\ads\enum\AdFieldEnum;
use directapi\services\ads\enum\DynamicTextAdFieldEnum;
use directapi\services\ads\enum\MobileAppAdFieldEnum;
use directapi\services\ads\enum\TextAdFieldEnum;
use directapi\services\ads\models\AdGetItem;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;

class YandexAdsProvider extends YandexEntitiesProvider implements IEntitiesProvider
{
    public function __construct(DirectApiService $directApiService, ICacheProvider $cacheProvider)
    {
        parent::__construct($directApiService, $cacheProvider);
        $this->collection = 'ads';
    }

    /**
     * @param array $ids
     * @return AdGetItem[]
     */
    public function getAll(array $ids): array
    {
        /**
         * @var AdGetItem[] $ads
         */
        $ads = $this->getFromCache('Id', $ids, AdGetItem::class);
        $found = array_keys($ads);
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            $criteria = new AdsSelectionCriteria();
            $criteria->Ids = $notFound;
            $fromService = $this->directApiService->getAdsService()->get($criteria, AdFieldEnum::getValues(),
                TextAdFieldEnum::getValues(), MobileAppAdFieldEnum::getValues(), DynamicTextAdFieldEnum::getValues());
            foreach ($fromService as $adGetItem) {
                $ads[$adGetItem->Id] = $adGetItem;
            }
            $this->addToCache($fromService);
        }
        return $ads;
    }

    /**
     * @param $id
     * @return AdGetItem|null
     */
    public function getOne($id): AdGetItem
    {
        $entities = $this->getAll([$id]);
        if ($entities) {
            return reset($entities);
        }
        return null;
    }


    protected function getChanges(array $ids, string $date): CheckResponse
    {
        return $this->directApiService->getChangesService()->check([], [], $ids, [FieldNamesEnum::AD_IDS], $date);
    }
}