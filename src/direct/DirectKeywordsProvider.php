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
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;
use sitkoru\contextcache\helpers\ArrayHelper;

class DirectKeywordsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    public const MAX_KEYWORDS_PER_UPDATE = 10000;
    public const CRITERIA_MAX_IDS = 10000;
    public const CRITERIA_MAX_AD_GROUP_IDS = 1000;
    public const CRITERIA_MAX_CAMPAIGN_IDS = 10;

    public function __construct(
        DirectApiService $directApiService,
        ICacheProvider $cacheProvider,
        ContextEntitiesLogger $logger
    ) {
        parent::__construct($directApiService, $cacheProvider, $logger);
        $this->collection = 'keywords';
        $this->keyField = 'Id';
    }

    /**
     * @param int $id
     *
     * @return KeywordGetItem|null
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     * @throws \directapi\exceptions\UnknownPropertyException
     */
    public function getOne($id): ?KeywordGetItem
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
     * @return KeywordGetItem[]
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
         * @var KeywordGetItem[] $keywords
         */
        $keywords = $this->getFromCache($ids, 'Id');
        $found = array_keys($keywords);
        $notFound = array_values(array_diff($ids, $found));
        if (count($notFound) > 0) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_IDS) as $idsChunk) {
                $criteria = new KeywordsSelectionCriteria();
                $criteria->Ids = $idsChunk;
                $fromService = $this->directApiService->getKeywordsService()->get(
                    $criteria,
                    KeywordFieldEnum::getValues()
                );
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
     *
     * @return KeywordGetItem[]
     *
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
         * @var KeywordGetItem[] $keywords
         */
        $keywords = $this->getFromCache($ids, 'AdGroupId', 'Id');
        $found = array_unique(ArrayHelper::getColumn($keywords, 'AdGroupId'));
        $notFound = array_values(array_diff($ids, $found));
        if (count($notFound) > 0) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_AD_GROUP_IDS) as $idsChunk) {
                $criteria = new KeywordsSelectionCriteria();
                $criteria->AdGroupIds = $idsChunk;
                $fromService = $this->directApiService->getKeywordsService()->get(
                    $criteria,
                    KeywordFieldEnum::getValues()
                );
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
     *
     * @return KeywordGetItem[]
     *
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
         * @var KeywordGetItem[] $keywords
         */
        $keywords = $this->getFromCache($ids, 'CampaignId', 'Id');
        $found = array_unique(ArrayHelper::getColumn($keywords, 'CampaignId'));
        $notFound = array_values(array_diff($ids, $found));
        if (count($notFound) > 0) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_CAMPAIGN_IDS) as $idsChunk) {
                $criteria = new KeywordsSelectionCriteria();
                $criteria->CampaignIds = $idsChunk;
                $fromService = $this->directApiService->getKeywordsService()->get(
                    $criteria,
                    KeywordFieldEnum::getValues()
                );
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
        $this->logger->info('Update keywords: ' . \count($entities));
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
                if (count($chunkResult->Errors) > 0) {
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
        return $this->directApiService->getChangesService()->check([], [], $ids, [FieldNamesEnum::AD_IDS], $date);
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
