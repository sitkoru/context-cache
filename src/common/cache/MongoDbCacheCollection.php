<?php

namespace sitkoru\contextcache\common\cache;


use MongoDB\Client;
use sitkoru\contextcache\common\ICacheCollection;
use sitkoru\contextcache\helpers\ArrayHelper;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

class MongoDbCacheCollection implements ICacheCollection
{
    /**
     * @var \MongoDB\Collection
     */
    protected $collection;

    /**
     * @var string
     */
    protected $keyField;

    public function __construct(Client $client, string $service, string $collection, string $keyField)
    {
        $this->keyField = $keyField;
        $this->collection = $client->selectCollection($service, $collection);
    }

    /**
     * @var Serializer
     */
    private static $serializer;

    /**
     * @return Serializer
     */
    private static function getSerializer(): Serializer
    {

        if (!self::$serializer) {
            self::$serializer = new Serializer([new ContextNormalizer(), new ArrayDenormalizer()]);
        }
        return self::$serializer;
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
    public function get(array $ids, string $field, ?string $indexBy = null): array
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
    public function set(array $entities): void
    {
        foreach (array_chunk($entities, 500) as $chunk) {
            $operations = [];
            $serializedChunk = $this->serializeEntities($chunk);
            foreach ($serializedChunk as $entity) {
                $operations[] = [
                    'updateOne' => [
                        $this->prepareOperationFilter($entity),
                        ['$set' => $entity],
                        ['upsert' => true]
                    ]
                ];
            }
            $this->collection->bulkWrite($operations);
        }
    }

    private function prepareOperationFilter(array $entity): array
    {
        $filterItems = explode('.', $this->keyField);

        if (\count($filterItems) === 1) {
            $keyValue = $entity[$this->keyField];
        } else {
            $keyValue = $this->getArrayLastValueByArrayKeysNesting($entity, $filterItems);
        }

        return [$this->keyField => $keyValue];
    }

    /**
     * @param array $array
     * @param array $arrayKeysNesting
     * @return array|mixed
     */
    private function getArrayLastValueByArrayKeysNesting(array $array, array $arrayKeysNesting)
    {
        $i = 0;
        $lastLevelArray = [];
        foreach ($arrayKeysNesting as $level) {
            //last item
            if (\count($arrayKeysNesting) - 1 === $i) {
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
            $preparedEntity = self::getSerializer()->normalize($entity, 'json');
            $preparedEntities[] = $preparedEntity;
        }
        return $preparedEntities;
    }

    private function deserializeEntities(array $serializedEntities): array
    {
        $entities = [];
        foreach ($serializedEntities as $entry) {
            if ($entry->_class !== null) {
                $entity = self::getSerializer()->denormalize($entry, $entry->_class, 'json');
                $entities[] = $entity;
            }
        }
        return $entities;
    }

    public function clear(): void
    {
        $this->collection->deleteMany([]);
    }
}