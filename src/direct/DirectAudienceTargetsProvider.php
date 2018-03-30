<?php

namespace sitkoru\contextcache\direct;

use directapi\DirectApiService;
use directapi\services\audiencetargets\criterias\AudienceTargetSelectionCriteria;
use directapi\services\audiencetargets\enum\AudienceTargetFieldEnum;
use directapi\services\audiencetargets\models\AudienceTargetGetItem;
use directapi\services\changes\models\CheckResponse;
use directapi\services\changes\models\CheckResponseIds;
use directapi\services\changes\models\CheckResponseModified;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\common\ICacheProvider;
use sitkoru\contextcache\common\IEntitiesProvider;
use sitkoru\contextcache\common\models\UpdateResult;

class DirectAudienceTargetsProvider extends DirectEntitiesProvider implements IEntitiesProvider
{
    const CRITERIA_MAX_IDS = 10000;

    protected $keyField = 'Id';

    public function __construct(
        DirectApiService $directApiService,
        ICacheProvider $cacheProvider,
        LoggerInterface $logger
    ) {
        parent::__construct($directApiService, $cacheProvider, $logger);
        $this->collection = 'audiencetargets';
    }

    /**
     * @param $id
     * @return AudienceTargetGetItem|null
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

    protected function getChanges(array $ids, string $date): CheckResponse
    {
        return new CheckResponse();
    }

    protected function getChangesCount(CheckResponseModified $modified, CheckResponseIds $notFound): int
    {
        return 0;
    }
}