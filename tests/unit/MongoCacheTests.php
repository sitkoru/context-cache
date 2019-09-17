<?php

declare(strict_types=1);

namespace sitkoru\contextcache\tests\unit;

use directapi\common\enum\PriorityEnum;
use directapi\services\audiencetargets\models\AudienceTargetGetItem;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use sitkoru\contextcache\common\cache\MongoDbCacheProvider;
use sitkoru\contextcache\common\ICacheCollection;

class MongoDbTests extends TestCase
{
    public function testEntity()
    {
        $collection = $this->getCollection();
        $entity = new AudienceTargetGetItem();
        $entity->Id = 1;
        $entity->StrategyPriority = new PriorityEnum(PriorityEnum::HIGH);
        $collection->set([$entity]);
        $fromCache = $collection->get([$entity->Id], 'Id');
        $this->assertNotEmpty($fromCache);
        $this->assertArrayHasKey($entity->Id, $fromCache);
        /**
         * @var AudienceTargetGetItem $cachedEntity
         */
        $cachedEntity = $fromCache[$entity->Id];
        $this->assertInstanceOf(AudienceTargetGetItem::class, $cachedEntity);
        $this->assertTrue($cachedEntity->StrategyPriority->compare(PriorityEnum::HIGH));
    }

    protected function getCollection(): ICacheCollection
    {
        $logger = new Logger('directUpdateLogger');
        $logger->pushHandler(new ErrorLogHandler());
        $cacheProvider = new MongoDbCacheProvider('mongodb://mongodb', $logger);
        return $cacheProvider->collection('yandex', 'retargetinglists', "Id");
    }

    public function testEntityWithNullProperty()
    {
        $collection = $this->getCollection();
        $entity = new AudienceTargetGetItem();
        $entity->Id = 2;
        $collection->set([$entity]);
        $fromCache = $collection->get([$entity->Id], 'Id');
        $this->assertNotEmpty($fromCache);
        $this->assertArrayHasKey($entity->Id, $fromCache);
        /**
         * @var AudienceTargetGetItem $cachedEntity
         */
        $cachedEntity = $fromCache[$entity->Id];
        $this->assertInstanceOf(AudienceTargetGetItem::class, $cachedEntity);
        $this->assertNull($cachedEntity->StrategyPriority);
    }
}
