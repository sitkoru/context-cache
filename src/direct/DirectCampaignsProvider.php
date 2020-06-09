<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\campaigns\criterias\CampaignsSelectionCriteria;
use directapi\services\campaigns\enum\CampaignFieldEnum;
use directapi\services\campaigns\enum\CpmBannerCampaignFieldEnum;
use directapi\services\campaigns\enum\DynamicCampaignFieldEnum;
use directapi\services\campaigns\enum\MobileAppCampaignFieldEnum;
use directapi\services\campaigns\enum\SmartCampaignFieldEnum;
use directapi\services\campaigns\enum\TextCampaignFieldEnum;
use directapi\services\campaigns\models\CampaignGetItem;
use directapi\services\campaigns\models\CampaignUpdateItem;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use directapi\services\changes\models\CheckResponseIds;
use directapi\services\changes\models\CheckResponseModified;
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;

class DirectCampaignsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    public const CRITERIA_MAX_CAMPAIGN_IDS = 1000;
    public const MAX_CAMPAIGNS_PER_UPDATE = 10;

    public function __construct(
        DirectApiService $directApiService,
        ICacheProvider $cacheProvider,
        ContextEntitiesLogger $logger
    ) {
        parent::__construct($directApiService, $cacheProvider, $logger);
        $this->collection = 'campaigns';
        $this->keyField = 'Id';
    }

    /**
     * @return CampaignGetItem[]
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     * @throws \directapi\exceptions\UnknownPropertyException
     */
    public function getForService(): array
    {
        $campaigns = [];
        $criteria = new CampaignsSelectionCriteria();
        $fromService = $this->directApiService->getCampaignsService()->get(
            $criteria,
            CampaignFieldEnum::getValues(),
            TextCampaignFieldEnum::getValues(),
            MobileAppCampaignFieldEnum::getValues(),
            DynamicCampaignFieldEnum::getValues(),
            CpmBannerCampaignFieldEnum::getValues(),
            SmartCampaignFieldEnum::getValues()
        );
        foreach ($fromService as $campaignGetItem) {
            $campaigns[$campaignGetItem->Id] = $campaignGetItem;
        }
        return $campaigns;
    }

    /**
     * @param int $id
     *
     * @return CampaignGetItem|null
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     * @throws \directapi\exceptions\UnknownPropertyException
     */
    public function getOne($id): ?CampaignGetItem
    {
        $entities = $this->getAll([$id]);
        if ($entities) {
            return reset($entities);
        }
        return null;
    }

    /**
     * @param array $ids
     *
     * @return CampaignGetItem[]
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     * @throws \directapi\exceptions\UnknownPropertyException
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
            foreach (array_chunk($notFound, self::CRITERIA_MAX_CAMPAIGN_IDS) as $idsChunk) {
                $criteria = new CampaignsSelectionCriteria();
                $criteria->Ids = $idsChunk;
                $fromService = $this->directApiService->getCampaignsService()->get(
                    $criteria,
                    CampaignFieldEnum::getValues(),
                    TextCampaignFieldEnum::getValues(),
                    MobileAppCampaignFieldEnum::getValues(),
                    DynamicCampaignFieldEnum::getValues(),
                    CpmBannerCampaignFieldEnum::getValues(),
                    SmartCampaignFieldEnum::getValues()
                );
                foreach ($fromService as $campaignGetItem) {
                    $campaigns[$campaignGetItem->Id] = $campaignGetItem;
                }
                $this->addToCache($fromService);
            }
        }
        return $campaigns;
    }

    /**
     * @param CampaignGetItem[] $entities
     *
     * @return UpdateResult
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonMapper_Exception
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     */
    public function update(array $entities): UpdateResult
    {
        $result = new UpdateResult();
        $this->logger->info('Update campaigns: ' . \count($entities));
        foreach (array_chunk($entities, self::MAX_CAMPAIGNS_PER_UPDATE) as $index => $entitiesChunk) {
            $this->logger->info('Chunk: ' . $index . '. Upload.');
            $updEntities = $this->directApiService->getCampaignsService()->toUpdateEntities($entitiesChunk);
            $chunkResults = $this->directApiService->getCampaignsService()->update($updEntities);
            $this->logger->info('Chunk: ' . $index . '. Uploaded.');
            foreach ($chunkResults as $i => $chunkResult) {
                if (!array_key_exists($i, $updEntities)) {
                    continue;
                }
                /**
                 * @var CampaignUpdateItem $campaign
                 */
                $campaign = $updEntities[$i];
                if ($chunkResult->Errors) {
                    $result->success = false;
                    $campaignErrors = [];
                    foreach ($chunkResult->Errors as $error) {
                        $campaignErrors[] = $error->Message . ' ' . $error->Details;
                    }
                    $result->errors[$campaign->Id] = $campaignErrors;
                }
            }
            $this->logger->info('Chunk: ' . $index . '. Results processed.');
        }
        $this->clearCache();
        return $result;
    }

    /**
     * @param array  $ids
     * @param string $date
     *
     * @return CheckResponse
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonMapper_Exception
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     */
    protected function getChanges(array $ids, string $date): CheckResponse
    {
        return $this->directApiService->getChangesService()->check([], [], $ids, [FieldNamesEnum::CAMPAIGN_IDS], $date);
    }

    protected function getChangesCount(?CheckResponseModified $modified, ?CheckResponseIds $notFound): int
    {
        $count = 0;
        if ($modified && \is_array($modified->CampaignIds)) {
            $count += \count($modified->CampaignIds);
        }
        if ($notFound && \is_array($notFound->CampaignIds)) {
            $count += \count($notFound->CampaignIds);
        }
        return $count;
    }
}
