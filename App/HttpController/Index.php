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

class Index extends Controller
{
    function index()
    {
        // TODO: Implement index() method.
//        $models = new articleDetailModel();
//        $arr = $models->getDb()->get();
//        d(1,$arr);
//        $arr1 = $models->getDb()->get();
//        d(2,$arr1);
//        $arr2 = articleDetailModel::db()->get();
//        d(3,$arr2);
//        $arr3 = $models->getDb()->get();
//        d(4,$arr3);
//        $bool = $models->getDb()->where('id',2,'=')->delete();
//        d(5,$bool);
//        $arr4 = $models->getDb()->get();
//        d(6,$arr4);
//        $arr5 = $models->getDb()->get();
//        d(7,$arr5);
//        $arr6 = articleDetailModel::db()->get();
//        d(8,$arr6);
//        $arr7 = $models->getDb()->get();
//        d(9,$arr7);

        $redis = new RedisClient();
        $redis->del('name');
        $redis->del('sex');
    }
}