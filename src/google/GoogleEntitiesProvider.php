<?php

namespace sitkoru\contextcache\google;

use ReflectionClass;
use sitkoru\contextcache\common\EntitiesProvider;
use sitkoru\contextcache\common\ICacheProvider;

abstract class GoogleEntitiesProvider extends EntitiesProvider
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

    protected function addToCache(array $entities)
    {
        $preparedEntities = [];
        foreach ($entities as $entity) {
            $preparedEntity = json_decode($this->json_encode_private($entity), true);
            $preparedEntity['serialized'] = serialize($entity);
            $preparedEntities[] = $preparedEntity;
        }
        $this->cacheProvider->set($this->serviceKey, $this->collection, $preparedEntities);
        $this->cacheProvider->setTimeStamp($this->serviceKey, time());
    }

    protected function getEntitiesFromCache(string $field, array $ids, string $class): array
    {
        $entities = [];
        $data = $this->cacheProvider->get($this->serviceKey, $this->collection, $field, $ids);
        foreach ($data as $entry) {
            $object = json_decode(json_encode($entry), true);
            $entity = unserialize($object['serialized'], [$class]);
            $entities[$object[$field]] = $entity;
        }
        return $entities;
    }

    private function json_encode_private($object)
    {

        function extract_props($object)
        {
            $public = [];

            $reflection = new ReflectionClass(get_class($object));

            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);

                $value = $property->getValue($object);
                $name = $property->getName();
                if ($value !== null) {
                    if (is_array($value)) {
                        $public[$name] = [];

                        /** @var array $value */
                        foreach ($value as $item) {
                            if (is_object($item)) {
                                $itemArray = extract_props($item);
                                $public[$name][] = $itemArray;
                            } else {
                                $public[$name][] = $item;
                            }
                        }
                    } else {
                        if (is_object($value)) {
                            $public[$name] = extract_props($value);
                        } else {
                            $public[$name] = $value;
                        }
                    }
                }
            }

            return $public;
        }

        return json_encode(extract_props($object));
    }
}