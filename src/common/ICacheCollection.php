<?php

namespace sitkoru\contextcache\common;


interface ICacheCollection
{
    public function get(string $field, array $ids): array;

    public function set(array $entities);

    public function clear();
}