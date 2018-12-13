<?php
/**
 * Created by PhpStorm.
 * User: cozz
 * Date: 2018/12/13
 * Time: 12:07
 */

namespace App\HttpController;

use App\Model\articleDetailModel;
use App\Model\UserModel;
use Lib\Data\RedisClient;
use Lib\Http\AbstractInterface\ViewController;

class testView extends ViewController
{
    function index()
    {
        echo '1'.PHP_EOL;
        // TODO: Implement index() method.
        $redis = new RedisClient();
        $name = $redis->get('name');
        $sex = $redis->get('sex');
        $article_list = (new articleDetailModel())->getDb()->get();
//        $article_list = [];
        $this->assign(compact('name','sex', 'article_list'));
        $this->fetch('testView/index.html');
    }

    function user()
    {
        echo '2'.PHP_EOL;
        $user_list = (new UserModel())->getDb()->get();
        $this->assign('user_list', $user_list);
        $this->fetch('testView/user.html');
    }
}