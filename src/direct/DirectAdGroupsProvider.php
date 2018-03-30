<?php

namespace sitkoru\contextcache\direct;


use directapi\DirectApiService;
use directapi\services\adgroups\criterias\AdGroupsSelectionCriteria;
use directapi\services\adgroups\enum\AdGroupFieldEnum;
use directapi\services\adgroups\enum\MobileAppAdGroupFieldEnum;
use directapi\services\adgroups\models\AdGroupGetItem;
use directapi\services\adgroups\models\AdGroupUpdateItem;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use directapi\services\changes\models\CheckResponseIds;
use directapi\services\changes\models\CheckResponseModified;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use sitkoru\contextcache\helpers\ArrayHelper;

class DirectAdGroupsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{

    const MAX_AD_GROUPS_PER_UPDATE = 1000;

    const CRITERIA_MAX_CAMPAIGN_IDS = 10;

    const CRITERIA_MAX_AD_GROUP_IDS = 10000;

    protected $keyField = 'Id';

    public function __construct(
        DirectApiService $directApiService,
        ICacheProvider $cacheProvider,
        LoggerInterface $logger
    )
    {
        parent::__construct($directApiService, $cacheProvider, $logger);
        $this->collection = 'adGroups';
    }

    /**
     * @param $id
     * @return AdGroupGetItem|null
     * @throws \Exception
     */
    public function getOne($id): ?AdGroupGetItem
    {
        $ads = $this->getAll([$id]);
        if ($ads) {
            return reset($ads);
        }
        return null;
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
        $adGroups = $this->getFromCache($ids, 'Id');
        $found = array_keys($adGroups);
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_AD_GROUP_IDS) as $idsChunk) {
                $criteria = new AdGroupsSelectionCriteria();
                $criteria->Ids = $idsChunk;
                $fromService = $this->directApiService->getAdGroupsService()->get($criteria,
                    AdGroupFieldEnum::getValues(),
                    MobileAppAdGroupFieldEnum::getValues());
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
     * @return AdGroupGetItem[]
     * @throws \Exception
     */
    public function getByCampaignIds(array $ids): array
    {
        /**
         * @var AdGroupGetItem[] $adGroups
         */
        $adGroups = $this->getFromCache($ids, 'CampaignId', 'Id');
        $found = array_unique(ArrayHelper::getColumn($adGroups, 'CampaignId'));
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_CAMPAIGN_IDS) as $campaignIdsChunk) {
                $criteria = new AdGroupsSelectionCriteria();
                $criteria->CampaignIds = $campaignIdsChunk;
                $fromService = $this->directApiService->getAdGroupsService()->get($criteria,
                    AdGroupFieldEnum::getValues(),
                    MobileAppAdGroupFieldEnum::getValues());
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
     * @return UpdateResult
     * @throws \Exception
     */
    public function update(array $entities): UpdateResult
    {
        $result = new UpdateResult();
        $this->logger->info('Update ad groups: ' . count($entities));
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
                if ($chunkResult->Errors) {
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

    protected function getChanges(array $ids, string $date): CheckResponse
    {
        return $this->directApiService->getChangesService()->check([], $ids, [], [FieldNamesEnum::AD_GROUP_IDS],
            $date);
    }

    protected function getChangesCount(CheckResponseModified $modified, CheckResponseIds $notFound): int
    {
        return count($modified->AdGroupIds) + count($notFound->AdGroupIds);
    }
}