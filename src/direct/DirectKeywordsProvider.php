<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\changes\enum\FieldNamesEnum;
use directapi\services\changes\models\CheckResponse;
use directapi\services\keywords\criterias\KeywordsSelectionCriteria;
use directapi\services\keywords\enum\KeywordFieldEnum;
use directapi\services\keywords\models\KeywordGetItem;
use directapi\services\keywords\models\KeywordUpdateItem;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use sitkoru\contextcache\helpers\ArrayHelper;

class DirectKeywordsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    const MAX_KEYWORDS_PER_UPDATE = 10000;
    const CRITERIA_MAX_IDS = 10000;
    const CRITERIA_MAX_AD_GROUP_IDS = 1000;
    const CRITERIA_MAX_CAMPAIGN_IDS = 10;

    public function __construct(
        DirectApiService $directApiService,
        ICacheProvider $cacheProvider,
        LoggerInterface $logger
    ) {
        parent::__construct($directApiService, $cacheProvider, $logger);
        $this->collection = 'keywords';
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
            foreach (array_chunk($notFound, self::CRITERIA_MAX_IDS) as $idsChunk) {
                $criteria = new KeywordsSelectionCriteria();
                $criteria->Ids = $idsChunk;
                $fromService = $this->directApiService->getKeywordsService()->get($criteria,
                    KeywordFieldEnum::getValues());
                foreach ($fromService as $keywordGetItem) {
                    $keywords[$keywordGetItem->Id] = $keywordGetItem;
                }
                $this->addToCache($fromService);
            }
        }
        return $keywords;
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
            foreach (array_chunk($notFound, self::CRITERIA_MAX_AD_GROUP_IDS) as $idsChunk) {
                $criteria = new KeywordsSelectionCriteria();
                $criteria->AdGroupIds = $idsChunk;
                $fromService = $this->directApiService->getKeywordsService()->get($criteria,
                    KeywordFieldEnum::getValues());
                foreach ($fromService as $keywordGetItem) {
                    $keywords[$keywordGetItem->Id] = $keywordGetItem;
                }
                $this->addToCache($fromService);
            }
        }
        return $keywords;
    }

    /**
     * @param int[] $ids
     * @return KeywordGetItem[]
     * @throws \Exception
     */
    public function getByCampaignIds(array $ids): array
    {
        /**
         * @var KeywordGetItem[] $keywords
         */
        $keywords = $this->getFromCache($ids, 'CampaignId', 'Id');
        $found = array_unique(ArrayHelper::getColumn($keywords, 'CampaignId'));
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_CAMPAIGN_IDS) as $idsChunk) {
                $criteria = new KeywordsSelectionCriteria();
                $criteria->CampaignIds = $idsChunk;
                $fromService = $this->directApiService->getKeywordsService()->get($criteria,
                    KeywordFieldEnum::getValues());
                foreach ($fromService as $keywordGetItem) {
                    $keywords[$keywordGetItem->Id] = $keywordGetItem;
                }
                $this->addToCache($fromService);
            }
        }
        return $keywords;
    }

    /**
     * @param KeywordGetItem[] $entities
     * @return UpdateResult
     * @throws \Exception
     */
    public function update(array $entities): UpdateResult
    {
        $result = new UpdateResult();
        $this->logger->info('Update keywords: ' . count($entities));
        foreach (array_chunk($entities, self::MAX_KEYWORDS_PER_UPDATE) as $index => $entitiesChunk) {
            $this->logger->info('Chunk: ' . $index . '. Upload.');
            $updEntities = $this->directApiService->getKeywordsService()->toUpdateEntities($entitiesChunk);
            $chunkResults = $this->directApiService->getKeywordsService()->update($updEntities);
            $this->logger->info('Chunk: ' . $index . '. Uploaded.');
            foreach ($chunkResults as $i => $chunkResult) {
                if (!array_key_exists($i, $updEntities)) {

                    continue;
                }
                /**
                 * @var KeywordUpdateItem $keyword
                 */
                $keyword = $updEntities[$i];
                if ($chunkResult->Errors) {
                    $result->success = false;
                    $keywordErrors = [];
                    foreach ($chunkResult->Errors as $error) {
                        $keywordErrors[] = $error->Message . ' ' . $error->Details;
                    }
                    $result->errors[$keyword->Id] = $keywordErrors;
                }
            }
            $this->logger->info('Chunk: ' . $index . '. Results processed.');
        }
        $this->clearCache();
        return $result;
    }

    protected function getChanges(array $ids, string $date): CheckResponse
    {
        return $this->directApiService->getChangesService()->check([], [], $ids, [FieldNamesEnum::AD_IDS], $date);
    }
}