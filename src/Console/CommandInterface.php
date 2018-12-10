<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/10/29
 * Time: 9:55 PM
 */

namespace Zor\Console;


use Lib\Socket\Bean\Caller;
use Lib\Socket\Bean\Response;

interface CommandInterface
{
    public function exec(Caller $caller,Response $response);
    public function help(Caller $caller,Response $response);
}