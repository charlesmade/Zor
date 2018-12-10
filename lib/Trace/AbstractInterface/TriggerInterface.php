<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午12:39
 */

namespace Lib\Trace\AbstractInterface;


use Lib\Trace\Bean\Location;

interface TriggerInterface
{
    public function error($msg,Location $location);
    public function throwable(\Throwable $throwable);
}