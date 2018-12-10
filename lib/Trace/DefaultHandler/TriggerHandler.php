<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/8/14
 * Time: 下午1:21
 */

namespace Lib\Trace\DefaultHandler;


use Lib\Trace\AbstractInterface\TriggerInterface;
use Lib\Trace\Bean\Location;
use Lib\Trace\Logger;

class TriggerHandler implements TriggerInterface
{
    protected $logger;

    function __construct(Logger $logger = null)
    {
        if($logger == null){
            $logger = new Logger();
        }
        $this->logger = $logger;
    }

    public function error($msg, Location $location)
    {
        // TODO: Implement error() method.
        $debug = "Error at file[{$location->getFile()}] line[{$location->getLine()}] message:[{$msg}]";
        $this->logger->console($debug,false);
        $this->logger->log($debug,'debugError');
    }

    public function throwable(\Throwable $throwable)
    {
        // TODO: Implement throwable() method.
        $str = self::exceptionSummery($throwable);
        $this->logger->console($str,false);
        $this->logger->log($str,'debugException');
    }

    public static function exceptionSummery(\Throwable $throwable):string
    {
        return "Exception at file[{$throwable->getFile()}] line[{$throwable->getLine()}] message:[{$throwable->getMessage()}]";
    }
}