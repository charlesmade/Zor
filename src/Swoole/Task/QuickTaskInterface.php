<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/11/6
 * Time: 2:31 PM
 */

namespace Zor\Swoole\Task;


interface QuickTaskInterface
{
    static function run(\swoole_server $server,int $taskId,int $fromWorkerId);
}