<?php

namespace Lib\Data\Pool;
use Lib\Component\Pool\AbstractPool;
use Zor\Config;
use Lib\Mysqli\Config as MysqlConfig;
use Lib\Data\Source\MysqlSource;
class MysqlPool extends AbstractPool
{
    /**
     * 请在此处返回一个数据库链接实例
     * @return MysqlSource
     */
    protected function createObject()
    {
        $conf = $GLOBALS['conf']->getConf("MYSQL");
        $dbConf = new MysqlConfig($conf);
        return new MysqlSource($dbConf);
    }
}