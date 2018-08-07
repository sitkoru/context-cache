<?php

namespace sitkoru\contextcache\common;


interface ICacheCollection
{
    public function get(array $ids, string $field, ?string $indexBy = null): array;

    public function set(array $entities): void;

    public function clear(): void;
}