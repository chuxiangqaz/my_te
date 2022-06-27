<?php

namespace Te\Protocols;

/**
 * 字节流协议
 * 4 字节: 表示整体的正文长度
 * 2 字节: 表示执行的命令
 * n 字节: 表示数据附载
 */
class Stream implements Protocols
{

    public function integrity($data): bool
    {
        if (strlen($data) <= 6) {
            return false;
        }

        $header = substr($data,0,4);
        $arrayLen = unpack("Nlen", $header);
        $len = $arrayLen['len'];

        return strlen($data) >= $len;
    }

    public function encode($data = '')
    {
        $header = strlen($data) + 6;
        $cmd = "1";
        $bin = pack("Nn", $header, $cmd). $data;

        return $bin;

    }

    public function decode($data = '') :array
    {

        $header = substr($data,0,4);
        $arrayLen = unpack("Nlen", $header);
        $headerData = $arrayLen['len'];
        $cmd = unpack("ncmd", substr($data,4,2));
        $cmdData = $cmd['cmd'];

        $load = substr($data, 6, $headerData);


        return [$headerData, $cmdData, $load];
    }
}