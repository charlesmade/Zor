<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/12/7
 * Time: 10:23 PM
 */

namespace Zor\FastCache;


class Protocol
{
    public static function pack(string $data): string
    {
        return pack('N', strlen($data)).$data;
    }

    public static function packDataLength(string $head): int
    {
        return unpack('N', $head)[1];
    }

    public static function unpack(string $data):string
    {
        return substr($data,4);
    }
}