<?php

namespace sitkoru\contextcache\helpers;


class ArrayHelper
{
    /**
     * @param array $array
     * @param mixed      $name
     * @param bool  $keepKeys
     * @return array
     */
    public static function getColumn(array $array, $name, bool $keepKeys = true): array
    {
        $result = [];
        if ($keepKeys) {
            foreach ($array as $k => $element) {
                $result[$k] = static::getValue($element, $name);
            }
        } else {
            foreach ($array as $element) {
                $result[] = static::getValue($element, $name);
            }
        }

        return $result;
    }

    /**
     * @param array $array
     * @param mixed $key
     * @return array
     */
    public static function index(array $array, $key): array
    {
        $result = [];

        foreach ($array as $element) {
            $lastArray = &$result;

            $value = static::getValue($element, $key);
            if ($value !== null) {
                if (\is_float($value)) {
                    $value = (string)$value;
                }
                $lastArray[$value] = $element;
            }
            unset($lastArray);
        }

        return $result;
    }

    /**
     * @param array|object $array
     * @param mixed        $key
     * @param mixed        $default
     * @return mixed|null
     */
    public static function getValue($array, $key, $default = null)
    {
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }

        if (\is_array($key)) {
            $lastKey = array_pop($key);
            foreach ($key as $keyPart) {
                $array = static::getValue($array, $keyPart);
            }
            $key = $lastKey;
        }

        if (\is_array($array) && (isset($array[$key]) || array_key_exists($key, $array))) {
            return $array[$key];
        }

        if (($pos = strrpos($key, '.')) !== false) {
            $array = static::getValue($array, substr($key, 0, $pos), $default);
            $key = substr($key, $pos + 1);
        }

        if (\is_object($array)) {
            $getter = 'get' . $key;
            if (method_exists($array, $getter)) {
                return $array->$getter();
            }

            return $array->$key;
        }

        if (\is_array($array)) {
            return (isset($array[$key]) || array_key_exists($key, $array)) ? $array[$key] : $default;
        }

        return $default;
    }
}