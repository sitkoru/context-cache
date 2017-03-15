<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\ads\criterias\AdsSelectionCriteria;
use directapi\services\ads\enum\AdFieldEnum;
use directapi\services\ads\enum\DynamicTextAdFieldEnum;
use directapi\services\ads\enum\MobileAppAdFieldEnum;
use directapi\services\ads\enum\TextAdFieldEnum;
use directapi\services\ads\models\AdGetItem;
use directapi\services\ads\models\AdUpdateItem;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\helpers\ArrayHelper;

class DirectAdsProvider extends DirectEntitiesProvider implements IEntitiesProvider
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
        $ads = $this->getFromCache($ids, 'Id');
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
     * @param array $ids
     * @return AdGetItem[]
     * @throws \Exception
     */
    public function getByAdGroupIds(array $ids): array
    {
        /**
         * @var AdGetItem[] $ads
         */
        $ads = $this->getFromCache($ids, 'AdGroupId', 'Id');
        $found = array_unique(ArrayHelper::getColumn($ads, 'AdGroupId'));
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            $criteria = new AdsSelectionCriteria();
            $criteria->AdGroupIds = $notFound;
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
     * @param array $ids
     * @return AdGetItem[]
     * @throws \Exception
     */
    public function getByCampaignIds(array $ids): array
    {
        /**
         * @var AdGetItem[] $ads
         */
        $ads = $this->getFromCache($ids, 'CampaignId', 'Id');
        $found = array_unique(ArrayHelper::getColumn($ads, 'CampaignId'));
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            $criteria = new AdsSelectionCriteria();
            $criteria->CampaignIds = $notFound;
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
    public function getOne($id): ?AdGetItem
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

    /**
     * @param AdGetItem[] $entities
     * @return bool
     * @throws \Exception
     */
    public function update(array $entities): bool
    {
        $updEntities = $this->directApiService->getAdsService()->toUpdateEntities($entities);
        $this->directApiService->getAdsService()->update($updEntities);
        $this->clearCache();
        return true;
    }
}