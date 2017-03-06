<?php

namespace sitkoru\contextcache\common\cache;


use MongoDB\Client;
use ReflectionClass;
use sitkoru\contextcache\common\ICacheCollection;
use sitkoru\contextcache\helpers\ArrayHelper;

class MongoDbCacheCollection implements ICacheCollection
{
    /**
     * @var \MongoDB\Collection
     */
    protected $collection;

    /**
     * @var Client
     */
    private $client;


    public function __construct(Client $client, string $service, string $collection)
    {
        $this->client = $client;
        $this->collection = $client->selectCollection($service, $collection);
    }

    /**
     * @param string      $field
     * @param array       $ids
     * @param string|null $indexBy
     * @return array
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function get(array $ids, string $field, $indexBy = null): array
    {
        $filter = [
            $field => [
                '$in' => $ids
            ]
        ];
        $entities = $this->collection->find($filter)->toArray();
        $entities = $this->deserializeEntities($entities);
        if (!$indexBy) {
            $indexBy = $field;
        }
        $entities = ArrayHelper::index($entities, $indexBy);
        return $entities;
    }

    /**
     * @param array $entities
     * @throws \MongoDB\Driver\Exception\RuntimeException
     * @throws \MongoDB\Exception\InvalidArgumentException
     */
    public function set(array $entities)
    {
        $this->collection->insertMany($this->serializeEntities($entities));
    }

    private function serializeEntities(array $entities): array
    {
        $preparedEntities = [];
        foreach ($entities as $entity) {
            $preparedEntity = $this->extractEntityProperties($entity);
            $preparedEntity['serialized'] = serialize($entity);
            $preparedEntities[] = $preparedEntity;
        }
        return $preparedEntities;
    }

    private function deserializeEntities(array $serializedEntities): array
    {
        $entities = [];
        foreach ($serializedEntities as $entry) {
            $object = json_decode(json_encode($entry), true);
            $entity = unserialize($object['serialized']);
            $entities[] = $entity;
        }
        return $entities;
    }

    public function clear()
    {
        $this->collection->deleteMany([]);
    }

    private function extractEntityProperties($entity)
    {
        $public = [];

        $reflection = new ReflectionClass(get_class($entity));

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);

            $value = $property->getValue($entity);
            $name = $property->getName();
            if ($value !== null) {
                if (is_array($value)) {
                    $public[$name] = [];

                    /** @var array $value */
                    foreach ($value as $item) {
                        if (is_object($item)) {
                            $itemArray = $this->extractEntityProperties($item);
                            $public[$name][] = $itemArray;
                        } else {
                            $public[$name][] = $item;
                        }
                    }
                } else {
                    if (is_object($value)) {
                        $public[$name] = $this->extractEntityProperties($value);
                    } else {
                        $public[$name] = $value;
                    }
                }
            }
        }

        return $public;
    }
}