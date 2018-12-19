<?php
namespace Lib\Data\Pool;

use Lib\Component\Pool\AbstractPool;
use Lib\Data\Source\RedisSource;
use Zor\Config;

class RedisPool extends AbstractPool
{
    protected function createObject()
    {
        // TODO: Implement createObject() method.
        $redis = new RedisSource();
        $conf = $GLOBALS['conf']->getConf('REDIS');
        if( $redis->connect($conf['host'],$conf['port'])){
            if(!empty($conf['auth'])){
                $redis->auth($conf['auth']);
            }
            return $redis;
        }else{
            return null;
        }
    }
}