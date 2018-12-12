<?php

namespace Lib\Data\Source;
use Lib\Component\Pool\PoolObjectInterface;
use Swoole\Coroutine\Redis;
class RedisSource extends Redis implements PoolObjectInterface
{
    function gc()
    {
        // TODO: Implement gc() method.
        $this->close();
    }
    function objectRestore()
    {
        // TODO: Implement objectRestore() method.
    }
    function beforeUse(): bool
    {
        // TODO: Implement beforeUse() method.
        return true;
    }
}