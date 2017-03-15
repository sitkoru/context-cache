<?php

namespace sitkoru\contextcache\common;


interface IEntitiesProvider
{
    public function getAll(array $ids): array;

    public function getOne($id);

    public function update(array $entities): bool;
}