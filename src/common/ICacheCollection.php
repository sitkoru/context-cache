<?php

namespace sitkoru\contextcache\common;

interface ICacheCollection
{
    /**
     * @param array  $ids
     * @param string $field
     * @param mixed  $indexBy
     *
     * @return array
     */
    public function get(array $ids, string $field, $indexBy = null): array;

    public function set(array $entities): void;

    public function clear(): void;
}
