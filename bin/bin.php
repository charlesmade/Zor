<?php

define('ROOT_PATH', realpath(getcwd()));

$file = ROOT_PATH.'/vendor/autoload.php';
if (file_exists($file)) {
    require_once $file;
}else{
    die("include composer autoload.php fail\n");
}
class Show
{
    static function showTag($name, $value)
    {
        echo "\e[32m" . str_pad($name, 20, ' ', STR_PAD_RIGHT) . "\e[34m" . $value . "\e[0m\n";
    }


    public static function opCacheClear()
    {
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
}


\Zor\Config::getInstance()->loadEnv(ROOT_PATH.'/dev.env');
$conf = \Zor\Config::getInstance();
Show::showTag('main server', $conf->getConf('MAIN_SERVER.SERVER_TYPE'));
Show::showTag('listen address', $conf->getConf('MAIN_SERVER.LISTEN_ADDRESS'));
Show::showTag('listen port', $conf->getConf('MAIN_SERVER.PORT'));
\Zor\Core::getInstance()->initialize();

if(in_array('d', $argv) && array_search('d', $argv) == count($argv) - 1) $conf->setConf("MAIN_SERVER.SETTING.daemonize", true);
$list  = \Zor\ServerManager::getInstance()->getSubServerRegister();
$index = 1;
foreach ($list as $serverName => $item){
    $type = $item['type'] % 2 > 0 ? 'SWOOLE_TCP' : 'SWOOLE_UDP';
    Show::showTag('sub-Server'.$index, "{$serverName} => {$type}@{$item['listenAddress']}:{$item['port']}");
    $index++;
}
$ips = swoole_get_local_ip();
foreach ($ips as $eth => $val){
    Show::showTag('ip@'.$eth, $val);
}
Show::showTag('worker num', $conf->getConf('MAIN_SERVER.SETTING.worker_num'));
Show::showTag('task worker num', $conf->getConf('MAIN_SERVER.SETTING.task_worker_num'));
$user = $conf->getConf('MAIN_SERVER.SETTING.user');
if(empty($user)){
    $user = get_current_user();
}
Show::showTag('run at user', $user);
$daemonize = $conf->getConf("MAIN_SERVER.SETTING.daemonize");
if($daemonize){
    $daemonize = 'true';
}else{
    $daemonize = 'false';
}
Show::showTag('daemonize', $daemonize);
Show::showTag('swoole version', phpversion('swoole'));
Show::showTag('php version', phpversion());
//创建主服务
\Zor\Core::getInstance()->createServer();

\Zor\Core::getInstance()->start();

function d(...$arg):void
{
    var_dump(...$arg);
}
function e(...$arg):void
{
    var_dump(...$arg);exit;
}

