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

    protected function getFromCache(string $field, array $ids): array
    {
        if ($this->hasChanges($ids)) {
            $this->clearCache();
            return [];
        }
        return $this->getEntitiesFromCache($field, $ids);
    }

    protected function getEntitiesFromCache(string $field, array $ids): array
    {
        $entities = $this->cacheProvider->collection($this->serviceKey, $this->collection)->get($field, $ids);
        return $entities;
    }

    protected function addToCache(array $entities)
    {
        $this->cacheProvider->collection($this->serviceKey, $this->collection)->set($entities);
        $this->cacheProvider->setTimeStamp($this->serviceKey, time());
    }

    protected function clearCache()
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