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

    protected $keyField;

    /**
     * @var Client
     */
    private $client;


    public function __construct(Client $client, string $service, string $collection, string $keyField)
    {
        $this->client = $client;
        $this->keyField = $keyField;
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
     * @throws \MongoDB\Exception\UnsupportedException
     */
    public function set(array $entities)
    {
        $entities = $this->serializeEntities($entities);
        $operations = [];
        foreach ($entities as $entity) {
            $operations[] = ['updateOne' => [$this->prepareOperationFilter($entity), ['$set' => $entity], ['upsert' => true]]];
        }

        $this->collection->bulkWrite($operations);
    }

    private function prepareOperationFilter(array $entity, string $keyField): array
    {
        $filterItems = explode('.', $keyField);

        if (count($filterItems) === 1) {
            $keyValue = $entity[$keyField];
        } else {
            $keyValue = $this->getArrayLastValueByArrayKeysNesting($entity, $filterItems);
        }

        return [$keyField => $keyValue];
    }

    private function getArrayLastValueByArrayKeysNesting(array $array, array $arrayKeysNesting)
    {
        $i = 0;
        $lastLevelArray = [];
        foreach ($arrayKeysNesting as $level) {
            //last item
            if (count($arrayKeysNesting) - 1 === $i) {
                return $lastLevelArray[$level];
            }

            if (!$lastLevelArray) {
                $lastLevelArray = $array[$level];
            } else {
                $lastLevelArray = $lastLevelArray[$level];
            }
            $i++;
        }
        return $array;
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