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

    protected function getFromCache(string $field, array $ids, string $class): array
    {
        if ($this->hasChanges($ids)) {
            $this->clearCache();
            return [];
        }
        return $this->getEntitiesFromCache($field, $ids, $class);
    }

    protected function getEntitiesFromCache(string $field, array $ids, string $class): array
    {
        $entities = [];
        $data = $this->cacheProvider->get($this->serviceKey, $this->collection, $field, $ids);
        foreach ($data as $entry) {
            $object = json_decode(json_encode($entry));
            $entity = $this->mapper->map($object, new $class);
            $entities[$entity->$field] = $entity;
        }
        return $entities;
    }

    protected function addToCache(array $entities)
    {
        $preparedEntities = [];
        foreach ($entities as $entity) {
            $preparedEntity = json_decode(json_encode($entity));
            $preparedEntities[] = $preparedEntity;
        }
        $this->cacheProvider->set($this->serviceKey, $this->collection, $preparedEntities);
        $this->cacheProvider->setTimeStamp($this->serviceKey, time());
    }

    protected function clearCache()
    {
        $this->cacheProvider->clear($this->serviceKey, $this->collection);
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