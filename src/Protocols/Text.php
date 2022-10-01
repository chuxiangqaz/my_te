<?php

namespace Te\Protocols;

class Text implements Protocols
{

    /**
     * 判断数据是否完整
     *
     * @param $data
     * @return bool
     */
    public function integrity($data): bool
    {
        return stripos($data, "\n") === false ? false : true;

    }

    /**
     * 编码数据
     *
     * @param string $data
     * @return mixed
     */
    public function encode($data = '')
    {
        return $data . "\n";
    }

    /**
     * 解码单条消息
     *
     * @param string $data
     * @return array
     */
    public function decode($data = ''): string
    {
        $msg = substr($data, 0,-1);
        return $msg;
    }

    /**
     * 获取一条消息总长度
     *
     * @param string $data
     * @return int
     */
    public function msgLen($data = ''): int
    {
        return stripos($data, "\n") + 1;
    }
}