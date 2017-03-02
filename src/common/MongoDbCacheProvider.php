<?php

namespace sitkoru\contextcache\common;


use MongoDB\Client;

class MongoDbCacheProvider implements ICacheProvider
{
    private $client;

    public function __construct($mongoUrl)
    {
        $this->client = new Client($mongoUrl);
    }

    /**
     * @param string $service
     * @param string $collection
     * @param string $field
     * @param array  $ids
     * @return array
     * @throws \MongoDB\Exception\UnsupportedException
     * @throws \MongoDB\Exception\InvalidArgumentException
     * @throws \MongoDB\Driver\Exception\RuntimeException
     */
    public function get(string $service, string $collection, string $field, array $ids): array
    {
        $entitiesCollection = $this->client->selectCollection($service, $collection);
        return $entitiesCollection->find([
            $field => ['$in' => $ids]
        ])->toArray();
    }

    /**
     * @param string $service
     * @param string $collection
     * @param array  $entities
     * @throws \MongoDB\Driver\Exception\RuntimeException
     * @throws \MongoDB\Exception\InvalidArgumentException
     */
    public function set(string $service, string $collection, array $entities)
    {
        $entitiesCollection = $this->client->selectCollection($service, $collection);
        $entitiesCollection->insertMany($entities);
    }

    public function clear(string $service, string $collection)
    {
        $entitiesCollection = $this->client->selectCollection($service, $collection);
        $entitiesCollection->deleteMany([]);
    }

    public function getTimeStamp(string $service): int
    {
        $collection = $this->client->selectCollection($service, 'settings');
        $setting = $collection->findOne(['name' => 'timestamp']);
        if ($setting) {
            return $setting->value;
        }
        return null;
    }

    public function setTimeStamp(string $service, int $timestamp)
    {
        $collection = $this->client->selectCollection($service, 'settings');
        $collection->updateOne([
            'name' => 'timestamp'
        ],
            ['$set' => ['value' => $timestamp]],
            ['upsert' => true]
        );
    }
}