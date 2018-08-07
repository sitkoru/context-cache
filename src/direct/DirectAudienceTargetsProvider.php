<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\audiencetargets\criterias\AudienceTargetSelectionCriteria;
use directapi\services\audiencetargets\enum\AudienceTargetFieldEnum;
use directapi\services\audiencetargets\models\AudienceTargetGetItem;
use directapi\services\changes\models\CheckResponse;
use directapi\services\changes\models\CheckResponseIds;
use directapi\services\changes\models\CheckResponseModified;
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;

class DirectAudienceTargetsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    public const CRITERIA_MAX_IDS = 10000;


    public function __construct(
        DirectApiService $directApiService,
        ICacheProvider $cacheProvider,
        ContextEntitiesLogger $logger
    ) {
        parent::__construct($directApiService, $cacheProvider, $logger);
        $this->collection = 'audiencetargets';
        $this->keyField = 'Id';
    }

    /**
     * @param int $id
     * @return AudienceTargetGetItem|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     * @throws \directapi\exceptions\DirectAccountNotExistException
     * @throws \directapi\exceptions\DirectApiException
     * @throws \directapi\exceptions\DirectApiNotEnoughUnitsException
     * @throws \directapi\exceptions\RequestValidationException
     * @throws \directapi\exceptions\UnknownPropertyException
     */
    public function getOne($id): ?AudienceTargetGetItem
    {
        $entities = $this->getAll([$id]);
        if ($entities) {
            return reset($entities);
        }
        return null;
    }

    /**
     * @param array $ids
     * @return AudienceTargetGetItem[]
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
         * @var AudienceTargetGetItem[] $targets
         */
        $targets = $this->getFromCache($ids, 'Id');
        $found = array_keys($targets);
        $notFound = array_values(array_diff($ids, $found));
        if ($notFound) {
            foreach (array_chunk($notFound, self::CRITERIA_MAX_IDS) as $idsChunk) {
                $criteria = new AudienceTargetSelectionCriteria();
                $criteria->Ids = $idsChunk;
                $fromService = $this->directApiService->getAudienceTargetsService()->get($criteria,
                    AudienceTargetFieldEnum::getValues());
                foreach ($fromService as $target) {
                    $targets[$target->Id] = $target;
                }
                $this->addToCache($fromService);
            }
        }
        return $targets;
    }

    /**
     * @param array $entities
     * @return UpdateResult
     * @throws \Exception
     */
    public function update(array $entities): UpdateResult
    {
        throw new \Exception('This sevice does not support updates');
    }

    /**
     * @param array  $ids
     * @param string $date
     * @return CheckResponse
     * @throws \directapi\exceptions\UnknownPropertyException
     */
    protected function getChanges(array $ids, string $date): CheckResponse
    {
        return new CheckResponse();
    }

    protected function getChangesCount(?CheckResponseModified $modified, ?CheckResponseIds $notFound): int
    {
        return 0;
    }
}