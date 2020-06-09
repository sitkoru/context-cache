<?php

namespace sitkoru\contextcache\common;

use sitkoru\contextcache\common\models\UpdateResult;

interface IEntitiesProvider
{
    public function getAll(array $ids): array;

    /**
     * @param mixed $id
     *
     * @return mixed
     */
    public function getOne($id);

    public function update(array $entities): UpdateResult;
}
