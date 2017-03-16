<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\campaigns\criterias\CampaignsSelectionCriteria;
use directapi\services\campaigns\enum\CampaignFieldEnum;
use directapi\services\campaigns\enum\MobileAppCampaignFieldEnum;
use directapi\services\campaigns\enum\TextCampaignFieldEnum;
use directapi\services\campaigns\models\CampaignGetItem;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;

class DirectCampaignsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    public function __construct(DirectApiService $directApiService, ICacheProvider $cacheProvider)
    {
        parent::__construct($directApiService, $cacheProvider);
        $this->collection = 'campaigns';
    }

    /**
     * @param array $ids
     * @return CampaignGetItem[]
     */
    public function getAll(array $ids): array
    {
        /**
         * @var CampaignGetItem[] $campaigns
         */
        $campaigns = $this->getFromCache($ids, 'Id');
        $found = array_keys($campaigns);
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            $criteria = new CampaignsSelectionCriteria();
            $criteria->Ids = $notFound;
            $fromService = $this->directApiService->getCampaignsService()->get($criteria,
                CampaignFieldEnum::getValues(),
                TextCampaignFieldEnum::getValues(), MobileAppCampaignFieldEnum::getValues());
            foreach ($fromService as $campaignGetItem) {
                $campaigns[$campaignGetItem->Id] = $campaignGetItem;
            }
            $this->addToCache($fromService);
        }
        return $campaigns;
    }

    /**
     * @return CampaignGetItem[]
     */
    public function getForService(): array
    {
        $campaigns = [];
        $criteria = new CampaignsSelectionCriteria();
        $fromService = $this->directApiService->getCampaignsService()->get($criteria,
            CampaignFieldEnum::getValues(),
            TextCampaignFieldEnum::getValues(), MobileAppCampaignFieldEnum::getValues());
        foreach ($fromService as $campaignGetItem) {
            $campaigns[$campaignGetItem->Id] = $campaignGetItem;
        }
        return $campaigns;
    }

    /**
     * @param $id
     * @return CampaignGetItem|null
     */
    public function getOne($id): CampaignGetItem
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

    public function update(array $entities): UpdateResult
    {
        return new UpdateResult();
    }
}