<?php
/**
 * Created by PhpStorm.
 * User: cozz
 * Date: 2018/12/12
 * Time: 11:25
 */

namespace App\HttpController;


use App\Model\articleDetailModel;
use Lib\Data\RedisClient;
use Lib\Http\AbstractInterface\Controller;
use Lib\Http\Response;
use Lib\Http\Request;

class Index extends Controller
{
    function index()
    {
        // TODO: Implement index() method.
        $this->response()->write("router test");
    }

    function useMysqlDemo()
    {
        $models = new articleDetailModel();
        $arr = $models->getDb()->get();
        //        $arr2 = articleDetailModel::db()->get();
        //        d(3,$arr2);
    }

    function useRedisDemo()
    {
        for ($i = 1; $i < 20; $i++) {
            $name = "redis$i";
            $name = new RedisClient();
            echo $i.PHP_EOL;
        }
        $this->response()->write("write end!");
    }
}