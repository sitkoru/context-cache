<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use directapi\services\changes\models\CheckResponseIds;
use directapi\services\changes\models\CheckResponseModified;
use directapi\services\keywords\criterias\KeywordsSelectionCriteria;
use directapi\services\keywords\enum\KeywordFieldEnum;
use directapi\services\keywords\models\KeywordGetItem;
use directapi\services\keywords\models\KeywordUpdateItem;
use directapi\services\retargetinglists\criterias\RetargetingListSelectionCriteria;
use directapi\services\retargetinglists\enum\RetargetingListFieldEnum;
use directapi\services\retargetinglists\models\RetargetingListGetItem;
use directapi\services\retargetinglists\models\RetargetingListUpdateItem;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use sitkoru\contextcache\helpers\ArrayHelper;

class DirectRetargetingProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    const MAX_LISTS_PER_UPDATE = 10000;
    const CRITERIA_MAX_IDS = 10000;

    protected $keyField = 'Id';

    public function __construct(
        DirectApiService $directApiService,
        ICacheProvider $cacheProvider,
        LoggerInterface $logger
    ) {
        parent::__construct($directApiService, $cacheProvider, $logger);
        $this->collection = 'retargetinglists';
    }

    /**
     * @param $id
     * @return RetargetingListGetItem|null
     */
    public function getOne($id): ?RetargetingListGetItem
    {
        $entities = $this->getAll([$id]);
        if ($entities) {
            return reset($entities);
        }
        return null;
    }

    /**
     * @param array $ids
     * @return RetargetingListGetItem[]
     */
    public function getAll(array $ids): array
    {
        /**
         * @var RetargetingListGetItem[] $lists
         */
        $lists = $this->getFromCache($ids, 'Id');
        $found = array_keys($lists);
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_IDS) as $idsChunk) {
                $criteria = new RetargetingListSelectionCriteria();
                $criteria->Ids = $idsChunk;
                $fromService = $this->directApiService->getRetargetingListsService()->get($criteria,
                    RetargetingListFieldEnum::getValues());
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
     * @return UpdateResult
     * @throws \Exception
     */
    public function update(array $entities): UpdateResult
    {
        $result = new UpdateResult();
        $this->logger->info('Update retargeting lists: ' . count($entities));
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
                if ($chunkResult->Errors) {
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

    protected function getChanges(array $ids, string $date): CheckResponse
    {
        return new CheckResponse();
    }

    protected function getChangesCount(CheckResponseModified $modified, CheckResponseIds $notFound): int
    {
        return 0;
    }
}