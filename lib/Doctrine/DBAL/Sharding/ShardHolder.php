<?php

namespace Doctrine\DBAL\Sharding;

/**
 * Interface ShardHolder
 * @package Doctrine\DBAL\Sharding
 */
interface ShardHolder
{
    /**
     * Returns shard id
     *
     * @return mixed
     */
    public function getShardId();
}
