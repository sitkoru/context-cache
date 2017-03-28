<?php

namespace sitkoru\contextcache\common;


use Psr\Log\LoggerInterface;

abstract class EntitiesProvider
{
    /**
     * @var ICacheProvider
     */
    protected $cacheProvider;

    protected $serviceKey;

    protected $collection;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(ICacheProvider $cacheProvider, LoggerInterface $logger)
    {
        $this->cacheProvider = $cacheProvider;
        $this->logger = $logger;
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
        $this->logger->debug('Clear cache');
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