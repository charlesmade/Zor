<?php
/**
 * Created by PhpStorm.
 * User: cozz
 * Date: 2018/12/12
 * Time: 17:57
 */

namespace Lib\Data;
use Lib\Data\Pool\RedisPool;
use Lib\Component\Pool\PoolManager;
use Lib\Data\Source\RedisSource;
use Zor\Config;

class RedisClient
{
    private $redisDb;

    function __construct()
    {
        $redisDb = PoolManager::getInstance()->getPool(RedisPool::class)->getObj(Config::getInstance()->getConf('REDIS.POOL_TIME_OUT'));
        if ($redisDb instanceof RedisSource) {
            $this->redisDb = $redisDb;
        } else {
            throw new \Exception('redis pool is empty');
        }
        return $this;
    }

    function getDb()
    {
        return $this->redisDb;
    }

    function set(string $key, $value, $ttl = null)
    {
        if(!is_null($ttl))
            return $this->getDb()->set($key,$value,$ttl);
        else
            return $this->getDb()->set($key,$value);
    }

    function del(string $key)
    {
        if(!is_null($key))
            return $this->getDb()->del($key);
        return true;
    }

    function get(string $key)
    {
        return $this->getDb()->get($key);
    }

    function __destruct()
    {
        // TODO: Implement __destruct() method.
        PoolManager::getInstance()->getPool(RedisPool::class)->recycleObj($this->redisDb);
    }
}