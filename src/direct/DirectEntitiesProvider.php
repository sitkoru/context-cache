<?php

namespace sitkoru\contextcache\direct;


use directapi\DirectApiService;
use directapi\services\changes\models\CheckResponse;
use directapi\services\changes\models\CheckResponseIds;
use directapi\services\changes\models\CheckResponseModified;
use sitkoru\contextcache\common\ContextEntitiesLogger;
use sitkoru\contextcache\common\EntitiesProvider;
use sitkoru\contextcache\common\ICacheProvider;

abstract class DirectEntitiesProvider extends EntitiesProvider
{
    /**
     * @var DirectApiService
     */
    protected $directApiService;

    public function __construct(
        DirectApiService $directApiService,
        ICacheProvider $cacheProvider,
        ContextEntitiesLogger $logger
    ) {
        parent::__construct($cacheProvider, $logger);
        $this->directApiService = $directApiService;
        $this->serviceKey = 'yandex';
    }

    protected function hasChanges(array $ids): bool
    {
        $ts = $this->getLastCacheTimestamp();
        if (!$ts || $ts < time() - 60) {
            $date = date('Y-m-d\TH:i:s\Z', $ts);
            $changes = $this->getChanges($ids, $date);
            $count = 0;
            if ($changes) {
                $count = $this->getChangesCount($changes->Modified, $changes->NotFound);
            }
            if ($count === 0) {
                $this->setLastCacheTimestamp(time());
                return false;
            }
            return true;
        }
        return false;
    }

    abstract protected function getChanges(array $ids, string $date): CheckResponse;

    abstract protected function getChangesCount(?CheckResponseModified $modified, ?CheckResponseIds $notFound): int;
}