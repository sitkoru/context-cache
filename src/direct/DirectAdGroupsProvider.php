<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\adgroups\criterias\AdGroupsSelectionCriteria;
use directapi\services\adgroups\enum\AdGroupFieldEnum;
use directapi\services\adgroups\enum\DynamicTextAdGroupFieldEnum;
use directapi\services\adgroups\enum\DynamicTextFeedAdGroupFieldEnum;
use directapi\services\adgroups\enum\MobileAppAdGroupFieldEnum;
use directapi\services\adgroups\enum\SmartAdGroupFieldEnum;
use directapi\services\adgroups\models\AdGroupGetItem;
use directapi\services\adgroups\models\AdGroupUpdateItem;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use directapi\services\changes\models\CheckResponseIds;
use directapi\services\changes\models\CheckResponseModified;
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use sitkoru\contextcache\helpers\ArrayHelper;

class DirectAdGroupsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    public const MAX_AD_GROUPS_PER_UPDATE = 1000;

    public const CRITERIA_MAX_CAMPAIGN_IDS = 10;

    public const CRITERIA_MAX_AD_GROUP_IDS = 10000;

    public function __construct(
        DirectApiService $directApiService,
        ICacheProvider $cacheProvider,
        ContextEntitiesLogger $logger
    ) {
        parent::__construct($directApiService, $cacheProvider, $logger);
        $this->collection = 'adGroups';
        $this->keyField = 'Id';
    }

    /**
     * @param int $id
     *
     * @return AdGroupGetItem|null
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getOne($id): ?AdGroupGetItem
    {
        $ads = $this->getAll([$id]);
        if (count($ads) > 0) {
            return reset($ads);
        }
        return null;
    }

    /**
     * @param array $ids
     *
     * @return AdGroupGetItem[]
     *
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAll(array $ids): array
    {
        /**
         * @var AdGroupGetItem[] $adGroups
         */
        $adGroups = $this->getFromCache($ids, 'Id');
        $found = array_keys($adGroups);
        $notFound = array_values(array_diff($ids, $found));
        if (count($notFound) > 0) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_AD_GROUP_IDS) as $idsChunk) {
                $criteria = new AdGroupsSelectionCriteria();
                $criteria->Ids = $idsChunk;
                $fromService = $this->directApiService->getAdGroupsService()->get(
                    $criteria,
                    AdGroupFieldEnum::getValues(),
                    MobileAppAdGroupFieldEnum::getValues(),
                    DynamicTextAdGroupFieldEnum::getValues(),
                    DynamicTextFeedAdGroupFieldEnum::getValues(),
                    SmartAdGroupFieldEnum::getValues()
                );
                foreach ($fromService as $adGroupGetItem) {
                    $adGroups[$adGroupGetItem->Id] = $adGroupGetItem;
                }
                $this->addToCache($fromService);
            }
        }
        return $adGroups;
    }

    /**
     * @param array $ids
     *
     * @return AdGroupGetItem[]
     *
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getByCampaignIds(array $ids): array
    {
        /**
         * @var AdGroupGetItem[] $adGroups
         */
        $adGroups = $this->getFromCache($ids, 'CampaignId', 'Id');
        $found = array_unique(ArrayHelper::getColumn($adGroups, 'CampaignId'));
        $notFound = array_values(array_diff($ids, $found));
        if (count($notFound) > 0) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_CAMPAIGN_IDS) as $campaignIdsChunk) {
                $criteria = new AdGroupsSelectionCriteria();
                $criteria->CampaignIds = $campaignIdsChunk;
                $fromService = $this->directApiService->getAdGroupsService()->get(
                    $criteria,
                    AdGroupFieldEnum::getValues(),
                    MobileAppAdGroupFieldEnum::getValues(),
                    DynamicTextAdGroupFieldEnum::getValues(),
                    DynamicTextFeedAdGroupFieldEnum::getValues(),
                    SmartAdGroupFieldEnum::getValues()
                );
                foreach ($fromService as $adGroupGetItem) {
                    $adGroups[$adGroupGetItem->Id] = $adGroupGetItem;
                }
                $this->addToCache($fromService);
            }
        }
        return $adGroups;
    }

    /**
     * @param AdGroupGetItem[] $entities
     *
     * @return UpdateResult
     *
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function update(array $entities): UpdateResult
    {
        $result = new UpdateResult();
        $this->logger->info('Update ad groups: ' . \count($entities));
        foreach (array_chunk($entities, self::MAX_AD_GROUPS_PER_UPDATE) as $index => $entitiesChunk) {
            $this->logger->info('Chunk: ' . $index . '. Upload.');
            $updEntities = $this->directApiService->getAdGroupsService()->toUpdateEntities($entitiesChunk);
            $chunkResults = $this->directApiService->getAdGroupsService()->update($updEntities);
            $this->logger->info('Chunk: ' . $index . '. Uploaded.');
            foreach ($chunkResults as $i => $chunkResult) {
                if (!array_key_exists($i, $updEntities)) {
                    continue;
                }
                /**
                 * @var AdGroupUpdateItem $adGroup
                 */
                $adGroup = $updEntities[$i];
                if (count($chunkResult->Errors) > 0) {
                    $result->success = false;
                    $adGroupErrors = [];
                    foreach ($chunkResult->Errors as $error) {
                        $adGroupErrors[] = $error->Message . ' ' . $error->Details;
                    }
                    $result->errors[$adGroup->Id] = $adGroupErrors;
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonMapper_Exception
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     */
    protected function getChanges(array $ids, string $date): ?CheckResponse
    {
        // @phpstan-ignore-next-line
        return $this->directApiService->getChangesService()->check(
            [],
            $ids,
            [],
            [FieldNamesEnum::AD_GROUP_IDS],
            $date
        );
    }

    protected function getChangesCount(?CheckResponseModified $modified, ?CheckResponseIds $notFound): int
    {
        $count = 0;
        if ($modified !== null && $modified->AdGroupIds !== null) {
            $count += count($modified->AdGroupIds);
        }
        if ($notFound !== null && $notFound->AdGroupIds !== null) {
            $count += count($notFound->AdGroupIds);
        }
        return $count;
    }
}
