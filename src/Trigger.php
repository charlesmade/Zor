<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午6:17
 */

namespace Zor;


use Lib\Component\Singleton;
use Lib\Trace\AbstractInterface\TriggerInterface;
use Lib\Trace\DefaultHandler\TriggerHandler;

class Trigger extends \Lib\Trace\Trigger
{
    use Singleton;

    /*
     * 自带的TriggerHandler用的是自带的Logger,里面开启了控制台socket 推送。如果你重写了TriggerHandler，请自己复制Logger里面的推送逻辑
     */
    function __construct(TriggerInterface $trigger = null)
    {
        if($trigger == null){
            $trigger = new TriggerHandler(Logger::getInstance());
        }
        parent::__construct($trigger);
    }
}