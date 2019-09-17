<?php

namespace sitkoru\contextcache\common\cache;


use MongoDB\Client;
use Psr\Log\LoggerInterface;
use sitkoru\contextcache\common\ICacheCollection;
use sitkoru\contextcache\common\ICacheProvider;

class MongoDbCacheProvider implements ICacheProvider
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(string $mongoUrl, ?LoggerInterface $logger)
    {
        $this->client = new Client($mongoUrl);
        $this->logger = $logger;
    }

    public function getTimeStamp(string $service): int
    {
        $collection = $this->client->selectCollection($service, 'settings');
        $setting = $collection->findOne(['name' => 'timestamp']);
        if ($setting) {
            return $setting->value;
        }
        return 0;
    }

    public function setTimeStamp(string $service, int $timestamp): void
    {
        $collection = $this->client->selectCollection($service, 'settings');
        $collection->updateOne([
            'name' => 'timestamp'
        ],
            ['$set' => ['value' => $timestamp]],
            ['upsert' => true]
        );
    }

    public function collection(string $service, string $collection, string $keyField): ICacheCollection
    {
        return new MongoDbCacheCollection($this->client, $service, $collection, $keyField, $this->logger);
    }
}
