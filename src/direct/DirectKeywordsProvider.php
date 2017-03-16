<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use directapi\services\keywords\criterias\KeywordsSelectionCriteria;
use directapi\services\keywords\enum\KeywordFieldEnum;
use directapi\services\keywords\models\KeywordGetItem;
use directapi\services\keywords\models\KeywordUpdateItem;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use sitkoru\contextcache\helpers\ArrayHelper;

class DirectKeywordsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    const MAX_KEYWORDS_PER_UPDATE = 10000;

    public function __construct(DirectApiService $directApiService, ICacheProvider $cacheProvider)
    {
        parent::__construct($directApiService, $cacheProvider);
        $this->collection = 'keywords';
    }

    /**
     * @param array $ids
     * @return KeywordGetItem[]
     */
    public function getAll(array $ids): array
    {
        /**
         * @var KeywordGetItem[] $keywords
         */
        $keywords = $this->getFromCache($ids, 'Id');
        $found = array_keys($keywords);
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            $criteria = new KeywordsSelectionCriteria();
            $criteria->Ids = $notFound;
            $fromService = $this->directApiService->getKeywordsService()->get($criteria, KeywordFieldEnum::getValues());
            foreach ($fromService as $keywordGetItem) {
                $keywords[$keywordGetItem->Id] = $keywordGetItem;
            }
            $this->addToCache($fromService);
        }
        return $keywords;
    }

    /**
     * @param $id
     * @return KeywordGetItem|null
     */
    public function getOne($id): ?KeywordGetItem
    {
        $entities = $this->getAll([$id]);
        if ($entities) {
            return reset($entities);
        }
        return null;
    }

    /**
     * @param int[] $ids
     * @return KeywordGetItem[]
     * @throws \Exception
     */
    public function getByAdGroupIds(array $ids): array
    {
        /**
         * @var KeywordGetItem[] $keywords
         */
        $keywords = $this->getFromCache($ids, 'AdGroupId', 'Id');
        $found = array_unique(ArrayHelper::getColumn($keywords, 'AdGroupId'));
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            $criteria = new KeywordsSelectionCriteria();
            $criteria->AdGroupIds = $notFound;
            $fromService = $this->directApiService->getKeywordsService()->get($criteria, KeywordFieldEnum::getValues());
            foreach ($fromService as $keywordGetItem) {
                $keywords[$keywordGetItem->Id] = $keywordGetItem;
            }
            $this->addToCache($fromService);
        }
        return $keywords;
    }


    protected function getChanges(array $ids, string $date): CheckResponse
    {
        return $this->directApiService->getChangesService()->check([], [], $ids, [FieldNamesEnum::AD_IDS], $date);
    }

    /**
     * @param KeywordGetItem[] $entities
     * @return UpdateResult
     * @throws \Exception
     */
    public function update(array $entities): UpdateResult
    {
        $result = new UpdateResult();
        $updEntities = $this->directApiService->getKeywordsService()->toUpdateEntities($entities);
        foreach (array_chunk($updEntities, self::MAX_KEYWORDS_PER_UPDATE) as $entitiesChunk) {
            $chunkResults = $this->directApiService->getKeywordsService()->update($entitiesChunk);
            foreach ($chunkResults as $i => $chunkResult) {
                if (!array_key_exists($i, $entitiesChunk)) {

                    continue;
                }
                /**
                 * @var KeywordUpdateItem $keyword
                 */
                $keyword = $entitiesChunk[$i];
                if ($chunkResult->Errors) {
                    $result->success = false;
                    $keywordErrors = [];
                    foreach ($chunkResult->Errors as $error) {
                        $keywordErrors[] = $error->Message . ' ' . $error->Details;
                    }
                    $result->errors[$keyword->Id] = $keywordErrors;
                }
            }
        }
        $this->clearCache();
        return $result;
    }
}