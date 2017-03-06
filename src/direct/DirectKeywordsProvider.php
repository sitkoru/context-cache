<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use directapi\services\keywords\criterias\KeywordsSelectionCriteria;
use directapi\services\keywords\enum\KeywordFieldEnum;
use directapi\services\keywords\models\KeywordGetItem;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;

class DirectKeywordsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
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
        $keywords = $this->getFromCache('Id', $ids, KeywordGetItem::class);
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
    public function getOne($id): KeywordGetItem
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
}