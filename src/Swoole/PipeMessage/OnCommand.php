<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/29
 * Time: 上午11:46
 */

namespace Zor\Swoole\PipeMessage;

use Lib\Component\Event;
use Lib\Component\Singleton;

class OnCommand extends Event
{
    use Singleton;
}