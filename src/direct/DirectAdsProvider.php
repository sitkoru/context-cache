<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\ads\criterias\AdsSelectionCriteria;
use directapi\services\ads\enum\AdFieldEnum;
use directapi\services\ads\enum\CpcVideoAdBuilderAdFieldEnum;
use directapi\services\ads\enum\CpmBannerAdBuilderAdFieldEnum;
use directapi\services\ads\enum\CpmVideoAdBuilderAdFieldEnum;
use directapi\services\ads\enum\DynamicTextAdFieldEnum;
use directapi\services\ads\enum\MobileAppAdBuilderAdFieldEnum;
use directapi\services\ads\enum\MobileAppAdFieldEnum;
use directapi\services\ads\enum\MobileAppImageAdFieldEnum;
use directapi\services\ads\enum\TextAdBuilderAdFieldEnum;
use directapi\services\ads\enum\TextAdFieldEnum;
use directapi\services\ads\enum\TextAdPriceExtensionFieldEnum;
use directapi\services\ads\enum\TextImageAdFieldEnum;
use directapi\services\ads\models\AdGetItem;
use directapi\services\ads\models\AdUpdateItem;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use directapi\services\changes\models\CheckResponseIds;
use directapi\services\changes\models\CheckResponseModified;
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use sitkoru\contextcache\helpers\ArrayHelper;

class DirectAdsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    public const MAX_ADS_PER_UPDATE = 1000;
    public const CRITERIA_MAX_CAMPAIGN_IDS = 10;
    public const CRITERIA_MAX_AD_GROUP_IDS = 1000;
    public const CRITERIA_MAX_AD_IDS = 10000;


    public function __construct(
        DirectApiService $directApiService,
        ICacheProvider $cacheProvider,
        ContextEntitiesLogger $logger
    )
    {
        parent::__construct($directApiService, $cacheProvider, $logger);
        $this->collection = 'ads';
        $this->keyField = 'AdGroupId';
    }

    /**
     * @param array $ids
     * @return AdGetItem[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     * @throws \directapi\exceptions\UnknownPropertyException
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
            foreach (array_chunk($notFound, self::CRITERIA_MAX_AD_GROUP_IDS) as $idsChunk) {
                $criteria = new AdsSelectionCriteria();
                $criteria->AdGroupIds = $idsChunk;
                $fromService = $this->directApiService->getAdsService()->get($criteria,
                    AdFieldEnum::getValues(),
                    TextAdFieldEnum::getValues(),
                    MobileAppAdFieldEnum::getValues(),
                    DynamicTextAdFieldEnum::getValues(),
                    TextImageAdFieldEnum::getValues(),
                    MobileAppImageAdFieldEnum::getValues(),
                    TextAdBuilderAdFieldEnum::getValues(),
                    MobileAppAdBuilderAdFieldEnum::getValues(),
                    TextAdPriceExtensionFieldEnum::getValues(),
                    CpcVideoAdBuilderAdFieldEnum::getValues(),
                    CpmBannerAdBuilderAdFieldEnum::getValues(),
                    CpmVideoAdBuilderAdFieldEnum::getValues());
                foreach ($fromService as $adGetItem) {
                    $ads[$adGetItem->Id] = $adGetItem;
                }
                $this->addToCache($fromService);
            }
        }
        return $ads;
    }

    /**
     * @param array $ids
     * @return AdGetItem[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     * @throws \directapi\exceptions\UnknownPropertyException
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
            foreach (array_chunk($notFound, self::CRITERIA_MAX_CAMPAIGN_IDS) as $idsChunk) {
                $criteria = new AdsSelectionCriteria();
                $criteria->CampaignIds = $idsChunk;
                $fromService = $this->directApiService->getAdsService()->get($criteria,
                    AdFieldEnum::getValues(),
                    TextAdFieldEnum::getValues(),
                    MobileAppAdFieldEnum::getValues(),
                    DynamicTextAdFieldEnum::getValues(),
                    TextImageAdFieldEnum::getValues(),
                    MobileAppImageAdFieldEnum::getValues(),
                    TextAdBuilderAdFieldEnum::getValues(),
                    MobileAppAdBuilderAdFieldEnum::getValues(),
                    TextAdPriceExtensionFieldEnum::getValues(),
                    CpcVideoAdBuilderAdFieldEnum::getValues(),
                    CpmBannerAdBuilderAdFieldEnum::getValues(),
                    CpmVideoAdBuilderAdFieldEnum::getValues());
                foreach ($fromService as $adGetItem) {
                    $ads[$adGetItem->Id] = $adGetItem;
                }
                $this->addToCache($fromService);
            }
        }
        return $ads;
    }

    /**
     * @param int $id
     * @return AdGetItem|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     * @throws \directapi\exceptions\UnknownPropertyException
     */
    public function getOne($id): ?AdGetItem
    {
        $entities = $this->getAll([$id]);
        if ($entities) {
            return reset($entities);
        }
        return null;
    }

    /**
     * @param array $ids
     * @return AdGetItem[]
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
         * @var AdGetItem[] $ads
         */
        $ads = $this->getFromCache($ids, 'Id');
        $found = array_keys($ads);
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_AD_IDS) as $idsChunk) {
                $criteria = new AdsSelectionCriteria();
                $criteria->Ids = $idsChunk;
                $fromService = $this->directApiService->getAdsService()->get($criteria,
                    AdFieldEnum::getValues(),
                    TextAdFieldEnum::getValues(),
                    MobileAppAdFieldEnum::getValues(),
                    DynamicTextAdFieldEnum::getValues(),
                    TextImageAdFieldEnum::getValues(),
                    MobileAppImageAdFieldEnum::getValues(),
                    TextAdBuilderAdFieldEnum::getValues(),
                    MobileAppAdBuilderAdFieldEnum::getValues(),
                    TextAdPriceExtensionFieldEnum::getValues(),
                    CpcVideoAdBuilderAdFieldEnum::getValues(),
                    CpmBannerAdBuilderAdFieldEnum::getValues(),
                    CpmVideoAdBuilderAdFieldEnum::getValues()
                );
                foreach ($fromService as $adGetItem) {
                    $ads[$adGetItem->Id] = $adGetItem;
                }
                $this->addToCache($fromService);
            }
        }
        return $ads;
    }

    /**
     * @param AdGetItem[] $entities
     * @return UpdateResult
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
        $this->logger->info('Update ads: ' . \count($entities));
        foreach (array_chunk($entities, self::MAX_ADS_PER_UPDATE) as $index => $entitiesChunk) {
            $this->logger->info('Chunk: ' . $index . '. Upload.');
            $updEntities = $this->directApiService->getAdsService()->toUpdateEntities($entitiesChunk);
            $chunkResults = $this->directApiService->getAdsService()->update($updEntities);
            $this->logger->info('Chunk: ' . $index . '. Uploaded.');
            foreach ($chunkResults as $i => $chunkResult) {
                if (!array_key_exists($i, $updEntities)) {

                    continue;
                }
                /**
                 * @var AdUpdateItem $ad
                 */
                $ad = $updEntities[$i];
                if ($chunkResult->Errors) {
                    $result->success = false;
                    $adErrors = [];
                    foreach ($chunkResult->Errors as $error) {
                        $adErrors[] = $error->Message . ' ' . $error->Details;
                    }
                    $result->errors[$ad->Id] = $adErrors;
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
     * @return CheckResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonMapper_Exception
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     */
    protected function getChanges(array $ids, string $date): CheckResponse
    {
        return $this->directApiService->getChangesService()->check([], [], $ids, [FieldNamesEnum::AD_IDS], $date);
    }

    protected function getChangesCount(?CheckResponseModified $modified, ?CheckResponseIds $notFound): int
    {
        $count = 0;
        if ($modified && \is_array($modified->AdIds)) {
            $count += \count($modified->AdIds);
        }
        if ($notFound && \is_array($notFound->AdIds)) {
            $count += \count($notFound->AdIds);
        }
        return $count;
    }
}
