<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 2018-12-10
 * Time: 23:20
 */

namespace App\HttpController;

use Lib\Http\AbstractInterface\AbstractRouter;
use FastRoute\RouteCollector;
use Lib\Http\Request;
use Lib\Http\Response;

class Router extends AbstractRouter
{
    function initialize(RouteCollector $routeCollector)
    {
        // TODO: Implement initialize() method.
        $routeCollector->get('/test','/test/index');
        $routeCollector->get('/hide','/test/test');
        $routeCollector->get('/','/');

        // TODO：Set Not Found & Not Allowed Call Method
        $this->setRouterNotFoundCallBack(function(Request $request, Response $response) {
            $response->write('<h1>未找到匹配的路由</h1>');
        });
    }
}