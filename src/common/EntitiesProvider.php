<?php

namespace sitkoru\contextcache\common;

abstract class EntitiesProvider
{
    /**
     * @var ICacheProvider
     */
    protected $cacheProvider;

    /**
     * @var string
     */
    protected $serviceKey;

    /**
     * @var string
     */
    protected $collection;

    /**
     * @var ICacheCollection|null
     */
    private $cacheCollection;

    /**
     * @var bool
     */
    private $isCacheEnabled = true;

    /**
     * @var string
     */
    protected $keyField;

    /**
     * @var ContextEntitiesLogger
     */
    protected $logger;

    public function __construct(ICacheProvider $cacheProvider, ContextEntitiesLogger $logger)
    {
        $this->cacheProvider = $cacheProvider;
        $this->logger = $logger;
    }

    private function getCacheCollection(): ICacheCollection
    {
        if ($this->cacheCollection === null) {
            $this->cacheCollection = $this->cacheProvider->collection(
                $this->serviceKey,
                $this->collection,
                $this->keyField
            );
        }
        return $this->cacheCollection;
    }

    /**
     * @param array  $ids
     * @param string $field
     * @param mixed  $indexBy
     *
     * @return array
     */
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

    public function disableCache(): self
    {
        $this->isCacheEnabled = false;
        return $this;
    }

    public function enableCache(): self
    {
        $this->isCacheEnabled = true;
        return $this;
    }

    /**
     * @param array  $ids
     * @param string $field
     * @param mixed  $indexBy
     *
     * @return array
     */
    protected function getEntitiesFromCache(array $ids, string $field, $indexBy = null): array
    {
        return $this->getCacheCollection()->get($ids, $field, $indexBy);
    }

    protected function addToCache(array $entities): void
    {
        if ($this->isCacheEnabled && count($entities) > 0) {
            $this->getCacheCollection()->set($entities);
            $this->cacheProvider->setTimeStamp($this->serviceKey, time());
        }
    }

    public function clearCache(): void
    {
        $this->logger->debug('Clear cache');
        $this->getCacheCollection()->clear();
    }

    protected function hasChanges(array $ids): bool
    {
        return false;
    }

    protected function getLastCacheTimestamp(): int
    {
        return $this->cacheProvider->getTimeStamp($this->serviceKey);
    }

    protected function setLastCacheTimestamp(int $timestamp): void
    {
        $this->cacheProvider->setTimeStamp($this->serviceKey, $timestamp);
    }
}
