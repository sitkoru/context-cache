<?php

namespace sitkoru\contextcache\common\cache;

use function count;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Exception\InvalidArgumentException;
use MongoDB\Exception\UnsupportedException;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\common\ICacheCollection;
use sitkoru\contextcache\helpers\ArrayHelper;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

class MongoDbCacheCollection implements ICacheCollection
{
    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @var string
     */
    protected $keyField;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(Client $client, string $service, string $collection, string $keyField, ?LoggerInterface $logger)
    {
        $this->keyField = $keyField;
        $this->collection = $client->selectCollection($service, $collection);
        $this->logger = $logger;
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
     * @param string $field
     * @param array  $ids
     * @param mixed  $indexBy
     *
     * @return array
     *
     * @throws UnsupportedException
     * @throws InvalidArgumentException
     * @throws RuntimeException
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
        if ($indexBy === null) {
            $indexBy = $field;
        }
        $entities = ArrayHelper::index($entities, $indexBy);
        return $entities;
    }

    /**
     * @param array $entities
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws UnsupportedException
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
                        ['$set'   => $entity],
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

        if (count($filterItems) === 1) {
            $keyValue = $entity[$this->keyField];
        } else {
            $keyValue = $this->getArrayLastValueByArrayKeysNesting($entity, $filterItems);
        }

        return [$this->keyField => $keyValue];
    }

    /**
     * @param array $array
     * @param array $arrayKeysNesting
     *
     * @return array|mixed
     */
    private function getArrayLastValueByArrayKeysNesting(array $array, array $arrayKeysNesting)
    {
        $i = 0;
        $lastLevelArray = [];
        foreach ($arrayKeysNesting as $level) {
            //last item
            if (count($arrayKeysNesting) - 1 === $i) {
                return $lastLevelArray[$level];
            }

            if ($lastLevelArray === []) {
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
            try {
                $preparedEntity = self::getSerializer()->normalize($entity, 'json');
                $preparedEntities[] = $preparedEntity;
            } catch (ExceptionInterface $e) {
                if ($this->logger) {
                    $keyField = $this->keyField;
                    $this->logger->error("Can't serialize entry "
                        . $entity->$keyField
                        . " (" . get_class($entity) . ") to collection "
                        . $this->collection->getCollectionName() . ": " . $e->getMessage());
                }
            }
        }
        return $preparedEntities;
    }

    private function deserializeEntities(array $serializedEntities): array
    {
        $entities = [];
        foreach ($serializedEntities as $entry) {
            if ($entry->_class !== null) {
                try {
                    $entity = self::getSerializer()->denormalize($entry, $entry->_class, 'json');
                    $entities[] = $entity;
                } catch (ExceptionInterface $e) {
                    if ($this->logger) {
                        $keyField = $this->keyField;
                        $this->logger->error("Can't deserialize entry "
                            . $entry->$keyField
                            . " (" . $entry->_class . ") from collection "
                            . $this->collection->getCollectionName() . ": " . $e->getMessage());
                    }
                }
            }
        }
        return $entities;
    }

    public function clear(): void
    {
        $this->collection->deleteMany([]);
    }
}
