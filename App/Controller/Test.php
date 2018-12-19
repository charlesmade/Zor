<?php
/**
 * Created by PhpStorm.
 * User: cozz
 * Date: 2018/12/10
 * Time: 18:34
 */

namespace App\Controller;

use Lib\Http\AbstractInterface\Controller;
use Zor\ServerManager;

class Test extends Controller
{
    function index()
    {
        $ip = ServerManager::getInstance()->getSwooleServer()->connection_info($this->request()->getSwooleRequest()->fd);
//        var_dump($ip);
        $this->response()->write('your ip:'.$ip['remote_ip']);
        $this->response()->write('Index Controller is run');
        // TODO: Implement index() method.
    }

    function test()
    {
        $this->response()->write("router test");
    }

    function jp()
    {
        $this->response()->write("router jump");
    }
}