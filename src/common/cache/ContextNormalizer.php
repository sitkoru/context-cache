<?php

namespace sitkoru\contextcache\common\cache;


use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

class ContextNormalizer extends GetSetMethodNormalizer
{
    public function supportsNormalization($data, $format = null)
    {
        return is_object($data);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return array_key_exists('_class', $data);
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $data = parent::normalize($object, $format, $context);
        $data['_class'] = \get_class($object);
        return array_filter($data, function ($value) {
            /*if (\is_array($value) && empty($value)) {
                return false;
            }*/
            return !\is_null($value);
        });
    }

    public function denormalize($data, $class, $format = null, array $context = [])
    {
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }
        if (\is_array($data) && isset($data['_class'])) {
            $class = $data['_class'];
        }
        return parent::denormalize($data, $class, $format, $context);
    }

    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = [])
    {
        if ($attribute === '_class') return;
        if ($attribute === '_id') return;
        $class = null;
        $isArray = false;
        if (\is_array($value)) {
            if (isset($value['_class'])) {
                $class = $value['_class'];
            } else {
                $first = reset($value);
                if (\is_array($first) && isset($first['_class'])) {
                    $class = $first['_class'];
                    $isArray = true;
                }
            }
        } elseif (\is_object($value) && isset($value->_class)) {
            $class = $value->_class;
        }
        if ($class) {
            if ($isArray) {
                $newValue = [];
                foreach ($value as $val) {
                    $val = $this->denormalize($val, $class, $format, $context);
                    $newValue[] = $val;
                }
                $value = $newValue;
            } else {
                $value = $this->denormalize($value, $class, $format, $context);
            }
        }

        $reflClass = new \ReflectionClass($object);
        foreach ($reflClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflProperty) {
            if ($reflProperty->name !== $attribute || !$reflProperty->isPublic() || $reflProperty->isStatic() || !$this->isAllowedAttribute($object, $reflProperty->name, $format, $context)) {
                continue;
            }

            $object->$attribute = $value;
            return;
        }

        parent::setAttributeValue($object, $attribute, $value, $format, $context);
    }

    protected function getConstructor(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes)
    {
        return null;
    }

    protected function extractAttributes($object, $format = null, array $context = [])
    {
        $attributes = parent::extractAttributes($object, $format, $context);

        $reflClass = new \ReflectionClass($object);
        foreach ($reflClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflProperty) {
            if ($reflProperty->isStatic() || !$this->isAllowedAttribute($object, $reflProperty->name, $format, $context)) {
                continue;
            }

            $attributes[] = $reflProperty->name;
        }

        return $attributes;
    }

    protected function getAttributeValue($object, $attribute, $format = null, array $context = [])
    {
        $value = parent::getAttributeValue($object, $attribute, $format, $context);

        $reflClass = new \ReflectionClass($object);
        foreach ($reflClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflProperty) {
            if ($reflProperty->name !== $attribute || !$reflProperty->isPublic() || $reflProperty->isStatic() || !$this->isAllowedAttribute($object, $reflProperty->name, $format, $context)) {
                continue;
            }
            $value = $object->$attribute;
        }

        return $value;
    }

}