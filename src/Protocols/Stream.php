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

    /**
     * 判断数据是否完整
     *
     * @param $data
     * @return bool
     */
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

    /**
     * 编码数据
     *
     * @param string $data
     * @return mixed
     */
    public function encode($data = '')
    {
        $header = strlen($data) + 6;
        $cmd = "1";
        $bin = pack("Nn", $header, $cmd). $data;

        return $bin;

    }

    /**
     * 解码单条消息
     *
     * @param string $data
     * @return array
     */
    public function decode($data = '') :array
    {

        $header = substr($data,0,4);
        $arrayLen = unpack("Nlen", $header);
        $headerData = $arrayLen['len'];
        $cmd = unpack("ncmd", substr($data,4,2));
        $cmdData = $cmd['cmd'];
        $load = substr($data, 6);

        return [$headerData, $cmdData, $load];
    }


    /**
     * 获取消息长度
     * @param string $data
     * @return int
     */
    public function msgLen($data = ''): int
    {
        $header = substr($data,0,4);
        $arrayLen = unpack("Nlen", $header);
        return $arrayLen['len'];
    }
}