<?php

namespace sitkoru\contextcache\common;


interface ICacheProvider
{
    public function get(string $service, string $collection, string $field, array $ids): array;

    public function set(string $service, string $collection, array $entities);

    public function clear(string $service, string $collection);

    public function getTimeStamp(string $service): int;

    public function setTimeStamp(string $service, int $timestamp);
}