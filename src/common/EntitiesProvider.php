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

    private $cacheCollection;

    private $isCacheEnabled = true;

    protected $keyField;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(ICacheProvider $cacheProvider, LoggerInterface $logger)
    {
        $this->cacheProvider = $cacheProvider;
        $this->logger = $logger;
    }

    private function getCacheCollection(): ICacheCollection
    {
        if (!$this->cacheCollection) {
            $this->cacheCollection = $this->cacheProvider->collection($this->serviceKey, $this->collection, $this->keyField);
        }
        return $this->cacheCollection;
    }

    protected function getFromCache(array $ids, string $field, $indexBy = null): array
    {
        if (!$this->isCacheEnabled) {
            return [];
        }
        if ($this->hasChanges($ids)) {
            $this->clearCache();
            return [];
        }
        return $this->getEntitiesFromCache($ids, $field, $indexBy);
    }

    public function disableCache()
    {
        $this->isCacheEnabled = false;
        return $this;
    }

    public function enableCache()
    {
        $this->isCacheEnabled = true;
        return $this;
    }

    protected function getEntitiesFromCache(array $ids, string $field, $indexBy = null): array
    {
        return $this->getCacheCollection()->get($ids, $field, $indexBy);
    }

    protected function addToCache(array $entities)
    {
        if ($this->isCacheEnabled && $entities) {
            $this->getCacheCollection()->set($entities);
            $this->cacheProvider->setTimeStamp($this->serviceKey, time());
        }
    }

    public function clearCache()
    {
        $this->logger->debug('Clear cache');
        $this->getCacheCollection()->clear();
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