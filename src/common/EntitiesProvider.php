<?php

namespace sitkoru\contextcache\common;


abstract class EntitiesProvider
{
    /**
     * @var ICacheProvider
     */
    protected $cacheProvider;

    /**
     * @var \JsonMapper
     */
    private $mapper;

    protected $serviceKey;

    protected $collection;

    public function __construct(ICacheProvider $cacheProvider)
    {
        $this->cacheProvider = $cacheProvider;
        $mapper = new \JsonMapper();
        $mapper->bStrictNullTypes = false;
        $mapper->bIgnoreVisibility = true;
        $this->mapper = $mapper;
    }

    protected function getFromCache(array $ids, string $field, $indexBy = null): array
    {
        if ($this->hasChanges($ids)) {
            $this->clearCache();
            return [];
        }
        return $this->getEntitiesFromCache($ids, $field, $indexBy);
    }

    protected function getEntitiesFromCache(array $ids, string $field, $indexBy = null): array
    {
        $entities = $this->cacheProvider->collection($this->serviceKey, $this->collection)->get($ids, $field, $indexBy);
        return $entities;
    }

    protected function addToCache(array $entities)
    {
        if ($entities) {
            $this->cacheProvider->collection($this->serviceKey, $this->collection)->set($entities);
            $this->cacheProvider->setTimeStamp($this->serviceKey, time());
        }
    }

    public function clearCache()
    {
        $this->cacheProvider->collection($this->serviceKey, $this->collection)->clear();
    }

    protected function hasChanges($ids): bool
    {
        return false;
    }

    protected function getLastCacheTimestamp(): int
    {
        return $this->cacheProvider->getTimeStamp($this->serviceKey);
    }

    protected function setLastCacheTimestamp($timestamp)
    {
        $this->cacheProvider->setTimeStamp($this->serviceKey, $timestamp);
    }
}