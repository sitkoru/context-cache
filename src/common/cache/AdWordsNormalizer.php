<?php

namespace sitkoru\contextcache\common\cache;


use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

class AdWordsNormalizer extends GetSetMethodNormalizer
{
    public function normalize($object, $format = null, array $context = [])
    {
        $data = parent::normalize($object, $format, $context);
        $data['_class'] = \get_class($object);
        return array_filter($data, function ($value) {
            if (\is_array($value) && empty($value)) {
                return false;
            }
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
        parent::setAttributeValue($object, $attribute, $value, $format, $context);
    }

    protected function getConstructor(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes)
    {
        return null;
    }
}