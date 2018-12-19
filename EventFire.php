<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/28
 * Time: 下午6:33
 */

namespace Zor;


use Zor\Swoole\EventRegister;
use Zor\AbstractInterface\Event;
use Lib\Http\Request;
use Lib\Http\Response;
use Lib\Component\Pool\PoolManager;
use Lib\Data\Pool\RedisPool;
use Lib\Data\Pool\MysqlPool;

class EventFire implements Event
{

    public static function initialize()
    {
        // TODO: Implement initialize() method.
        date_default_timezone_set('Asia/Shanghai');

        // 注册redis连接池
        PoolManager::getInstance()->register(RedisPool::class, $GLOBALS['conf']->getConf('REDIS.POOL_MAX_NUM'));

        // 注册mysql数据库连接池
        PoolManager::getInstance()->register(MysqlPool::class, $GLOBALS['conf']->getConf('MYSQL.POOL_MAX_NUM'));
    }

    public static function mainServerCreate(EventRegister $register)
    {
        // TODO: Implement mainServerCreate() method.
    }

    public static function onRequest(Request $request, Response $response): bool
    {
        // TODO: Implement onRequest() method.
        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
        // TODO: Implement afterAction() method.
    }

    public static function onReceive(\swoole_server $server, int $fd, int $reactor_id, string $data):void
    {

    }

}