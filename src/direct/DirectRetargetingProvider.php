<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\changes\models\CheckResponse;
use directapi\services\changes\models\CheckResponseIds;
use directapi\services\changes\models\CheckResponseModified;
use directapi\services\retargetinglists\criterias\RetargetingListSelectionCriteria;
use directapi\services\retargetinglists\enum\RetargetingListFieldEnum;
use directapi\services\retargetinglists\models\RetargetingListGetItem;
use directapi\services\retargetinglists\models\RetargetingListUpdateItem;
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;

class DirectRetargetingProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    public const MAX_LISTS_PER_UPDATE = 10000;
    public const CRITERIA_MAX_IDS = 10000;

    public function __construct(
        DirectApiService $directApiService,
        ICacheProvider $cacheProvider,
        ContextEntitiesLogger $logger
    ) {
        parent::__construct($directApiService, $cacheProvider, $logger);
        $this->collection = 'retargetinglists';
        $this->keyField = 'Id';
    }

    /**
     * @param mixed $id
     *
     * @return RetargetingListGetItem|null
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     * @throws \directapi\exceptions\UnknownPropertyException
     */
    public function getOne($id): ?RetargetingListGetItem
    {
        $entities = $this->getAll([$id]);
        if (count($entities) > 0) {
            return reset($entities);
        }
        return null;
    }

    /**
     * @param array $ids
     *
     * @return RetargetingListGetItem[]
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
         * @var RetargetingListGetItem[] $lists
         */
        $lists = $this->getFromCache($ids, 'Id');
        $found = array_keys($lists);
        $notFound = array_values(array_diff($ids, $found));
        if (count($notFound) > 0) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_IDS) as $idsChunk) {
                $criteria = new RetargetingListSelectionCriteria();
                $criteria->Ids = $idsChunk;
                $fromService = $this->directApiService->getRetargetingListsService()->get(
                    $criteria,
                    RetargetingListFieldEnum::getValues()
                );
                foreach ($fromService as $listGetItem) {
                    $lists[$listGetItem->Id] = $listGetItem;
                }
                $this->addToCache($fromService);
            }
        }
        return $lists;
    }

    /**
     * @param RetargetingListGetItem[] $entities
     *
     * @return UpdateResult
     *
     * @throws \ErrorException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     */
    public function update(array $entities): UpdateResult
    {
        $result = new UpdateResult();
        $this->logger->info('Update retargeting lists: ' . \count($entities));
        foreach (array_chunk($entities, self::MAX_LISTS_PER_UPDATE) as $index => $entitiesChunk) {
            $this->logger->info('Chunk: ' . $index . '. Upload.');
            $updEntities = $this->directApiService->getRetargetingListsService()->toUpdateEntities($entitiesChunk);
            $chunkResults = $this->directApiService->getRetargetingListsService()->update($updEntities);
            $this->logger->info('Chunk: ' . $index . '. Uploaded.');
            foreach ($chunkResults as $i => $chunkResult) {
                if (!array_key_exists($i, $updEntities)) {
                    continue;
                }
                /**
                 * @var RetargetingListUpdateItem $list
                 */
                $list = $updEntities[$i];
                if (count($chunkResult->Errors) > 0) {
                    $result->success = false;
                    $keywordErrors = [];
                    foreach ($chunkResult->Errors as $error) {
                        $keywordErrors[] = $error->Message . ' ' . $error->Details;
                    }
                    $result->errors[$list->Id] = $keywordErrors;
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
     * @throws \directapi\exceptions\UnknownPropertyException
     */
    protected function getChanges(array $ids, string $date): ?CheckResponse
    {
        return null;
    }

    protected function getChangesCount(?CheckResponseModified $modified, ?CheckResponseIds $notFound): int
    {
        return 0;
    }
}
