<?php

namespace sitkoru\contextcache\adwords;

use sitkoru\contextcache\common\EntitiesProvider;
use sitkoru\contextcache\common\ICacheProvider;

abstract class AdWordsEntitiesProvider extends EntitiesProvider
{
    public function __construct(ICacheProvider $cacheProvider)
    {
        parent::__construct($cacheProvider);
        $this->serviceKey = 'google';
    }

    protected function hasChanges($ids): bool
    {
        $ts = $this->getLastCacheTimestamp();
        return !$ts || $ts < time() - 60 * 30;
    }
}