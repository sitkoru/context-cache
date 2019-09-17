<?php

declare(strict_types=1);

namespace sitkoru\contextcache\tests\unit;

use directapi\common\enum\PriorityEnum;
use directapi\services\audiencetargets\models\AudienceTargetGetItem;
use PHPUnit\Framework\TestCase;
use sitkoru\contextcache\common\cache\ContextNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

class SerializationTests extends TestCase
{
    public function testEnum()
    {
        $serializer = new Serializer([new ContextNormalizer(), new ArrayDenormalizer()]);

        $enum = new PriorityEnum(PriorityEnum::HIGH);
        $serialized = json_decode(json_encode($serializer->normalize($enum, 'json')));
        $deserialized = $serializer->denormalize($serialized, $serialized->_class, 'json');
        $this->assertTrue($deserialized->compare(PriorityEnum::HIGH));
    }

    public function testEnumInEntity(){
        $serializer = new Serializer([new ContextNormalizer(), new ArrayDenormalizer()]);
        $entity = new AudienceTargetGetItem();
        $entity->StrategyPriority = new PriorityEnum(PriorityEnum::HIGH);
        $serialized = json_decode(json_encode($serializer->normalize($entity, 'json')));
        $deserialized = $serializer->denormalize($serialized, $serialized->_class, 'json');

        $this->assertTrue($deserialized->StrategyPriority->compare(PriorityEnum::HIGH));
    }

    public function testNullEnumInEntity(){
        $serializer = new Serializer([new ContextNormalizer(), new ArrayDenormalizer()]);
        $entity = new AudienceTargetGetItem();
        $entity->StrategyPriority = null;
        $serialized = json_decode(json_encode($serializer->normalize($entity, 'json')));
        $deserialized = $serializer->denormalize($serialized, $serialized->_class, 'json');

        $this->assertEquals($deserialized->StrategyPriority, null);
    }

    public function testEnumInEntitiesArray(){
        $serializer = new Serializer([new ContextNormalizer(), new ArrayDenormalizer()]);
        $entity = new AudienceTargetGetItem();
        $entity->StrategyPriority = new PriorityEnum(PriorityEnum::HIGH);
        $serialized = json_decode(json_encode($serializer->normalize($entity, 'json')));
        $deserialized = $serializer->denormalize($serialized, $serialized->_class, 'json');

        $this->assertTrue($deserialized->StrategyPriority->compare(PriorityEnum::HIGH));
    }
}
