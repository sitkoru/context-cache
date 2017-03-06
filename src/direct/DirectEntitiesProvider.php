<?php

namespace sitkoru\contextcache\direct;


use directapi\DirectApiService;
use directapi\services\changes\models\CheckResponse;
use sitkoru\contextcache\common\EntitiesProvider;
use sitkoru\contextcache\common\ICacheProvider;

abstract class DirectEntitiesProvider extends EntitiesProvider
{
    /**
     * @var DirectApiService
     */
    protected $directApiService;

    public function __construct(DirectApiService $directApiService, ICacheProvider $cacheProvider)
    {
        parent::__construct($cacheProvider);
        $this->directApiService = $directApiService;
        $this->serviceKey = 'yandex';
    }

    protected function hasChanges($ids): bool
    {
        $ts = $this->getLastCacheTimestamp();
        if (!$ts || $ts < time() - 60) {
            $date = date('Y-m-d\TH:i:s\Z', $ts);
            $changes = $this->getChanges($ids, $date);
            $count = 0;
            if ($changes) {
                $count = count($changes->Modified) + count($changes->NotFound);
            }
            if ($count === 0) {
                $this->setLastCacheTimestamp(time());
                return false;
            }
            return true;
        }
        return false;
    }

    protected abstract function getChanges(array $ids, string $date): CheckResponse;
}