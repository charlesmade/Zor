<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/24
 * Time: 下午11:02
 */

namespace Lib\Http;


class UrlParser
{
    public static function pathInfo($path)
    {
        $basePath = dirname($path);
        $info = pathInfo($path);
        if($basePath == '/'){
            $basePath = $basePath.$info['filename'];
        }else{
            $basePath = $basePath.'/'.$info['filename'];
        }
        return $basePath;
    }
}