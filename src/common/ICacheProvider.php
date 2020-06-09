<?php

namespace sitkoru\contextcache\common;

interface ICacheProvider
{
    public function getTimeStamp(string $service): int;

    public function setTimeStamp(string $service, int $timestamp): void;

    public function collection(string $service, string $collection, string $keyField): ICacheCollection;
}
