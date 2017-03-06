<?php

namespace sitkoru\contextcache\direct;


use directapi\DirectApiService;
use directapi\services\adgroups\criterias\AdGroupsSelectionCriteria;
use directapi\services\adgroups\enum\AdGroupFieldEnum;
use directapi\services\adgroups\enum\MobileAppAdGroupFieldEnum;
use directapi\services\adgroups\models\AdGroupGetItem;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;

class DirectAdGroupsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    public function __construct(DirectApiService $directApiService, ICacheProvider $cacheProvider)
    {
        parent::__construct($directApiService, $cacheProvider);
        $this->collection = 'adGroups';
    }

    /**
     * @param array $ids
     * @return AdGroupGetItem[]
     * @throws \Exception
     */
    public function getAll(array $ids): array
    {
        /**
         * @var AdGroupGetItem[] $adGroups
         */
        $adGroups = $this->getFromCache('Id', $ids, AdGroupGetItem::class);
        $found = array_keys($adGroups);
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            $criteria = new AdGroupsSelectionCriteria();
            $criteria->Ids = $notFound;
            $fromService = $this->directApiService->getAdGroupsService()->get($criteria, AdGroupFieldEnum::getValues(),
                MobileAppAdGroupFieldEnum::getValues());
            foreach ($fromService as $adGroupGetItem) {
                $adGroups[$adGroupGetItem->Id] = $adGroupGetItem;
            }
            $this->addToCache($fromService);
        }
        return $adGroups;
    }

    /**
     * @param $id
     * @return AdGroupGetItem|null
     * @throws \Exception
     */
    public function getOne($id): AdGroupGetItem
    {
        $ads = $this->getAll([$id]);
        if ($ads) {
            return reset($ads);
        }
        return null;
    }


    protected function getChanges(array $ids, string $date): CheckResponse
    {
        return $this->directApiService->getChangesService()->check([], $ids, [], [FieldNamesEnum::AD_GROUP_IDS],
            $date);
    }
}