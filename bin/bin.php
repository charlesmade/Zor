<?php

define('ROOT_PATH', realpath(getcwd()));

$file = ROOT_PATH.'/vendor/autoload.php';
if (file_exists($file)) {
    require_once $file;
}else{
    die("include composer autoload.php fail\n");
}
class Install
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
function d(...$arg):void
{
    var_dump(...$arg);die;
}
$env_file = ROOT_PATH.'/dev.env';
\Zor\Config::getInstance()->loadEnv($env_file);
$conf = \Zor\Config::getInstance();
Install::showTag('main server', $conf->getConf('MAIN_SERVER.SERVER_TYPE'));
Install::showTag('listen address', $conf->getConf('MAIN_SERVER.LISTEN_ADDRESS'));
Install::showTag('listen port', $conf->getConf('MAIN_SERVER.PORT'));
\Zor\Core::getInstance()->initialize();

//$conf->setConf("MAIN_SERVER.SETTING.daemonize", true);
$list  = \Zor\ServerManager::getInstance()->getSubServerRegister();
$index = 1;
foreach ($list as $serverName => $item){
    $type = $item['type'] % 2 > 0 ? 'SWOOLE_TCP' : 'SWOOLE_UDP';
    Install::showTag('sub-Server'.$index, "{$serverName} => {$type}@{$item['listenAddress']}:{$item['port']}");
    $index++;
}
$ips = swoole_get_local_ip();
foreach ($ips as $eth => $val){
    Install::showTag('ip@'.$eth, $val);
}
Install::showTag('worker num', $conf->getConf('MAIN_SERVER.SETTING.worker_num'));
Install::showTag('task worker num', $conf->getConf('MAIN_SERVER.SETTING.task_worker_num'));
$user = $conf->getConf('MAIN_SERVER.SETTING.user');
if(empty($user)){
    $user = get_current_user();
}
Install::showTag('run at user', $user);
$daemonize = $conf->getConf("MAIN_SERVER.SETTING.daemonize");
if($daemonize){
    $daemonize = 'true';
}else{
    $daemonize = 'false';
}
Install::showTag('daemonize', $daemonize);
Install::showTag('swoole version', phpversion('swoole'));
Install::showTag('php version', phpversion());
//创建主服务
\Zor\Core::getInstance()->createServer();
//$conf = \Zor\Config::getInstance()->getConf();
//d($conf);
\Zor\Core::getInstance()->start();


