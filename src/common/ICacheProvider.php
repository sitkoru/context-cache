<?php

namespace sitkoru\contextcache\common;


interface ICacheProvider
{
    public function getTimeStamp(string $service): int;

    public function setTimeStamp(string $service, int $timestamp);

    public function collection(string $service, string $collection): ICacheCollection;
}