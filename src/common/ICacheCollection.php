<?php

namespace sitkoru\contextcache\common;


interface ICacheCollection
{
    public function get(array $ids, string $field, $indexBy = null): array;

    public function set(array $entities);

    public function clear();
}