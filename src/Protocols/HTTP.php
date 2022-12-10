<?php

namespace Te\Protocols;

/**
 * HTTP 报文内容格式如下
 * @link https://www.runoob.com/http/http-messages.html
 */
class HTTP implements Protocols
{

    /**
     * 判断数据是否完整
     *
     * @param $data
     * @return bool
     */
    public function integrity($data): bool
    {
        return (new HttpContent($data))->resolve()->isIntegrity();
    }

    /**
     * 编码数据
     *
     * @param string $data
     * @return mixed
     */
    public function encode($data = '')
    {
        return $data;
    }

    /**
     * 解码单条消息
     *
     * @param string $data 表示单个报文的内容
     * @return mixed 返回报文内容
     */
    public function decode($data = '')
    {
        $httpRequest = (new HttpContent($data));
        $httpRequest->resolve();
        return $httpRequest;
    }

    /**
     * 获取一条消息总长度
     *
     * @param string $data
     * @return int
     */
    public function msgLen($data = ''): int
    {
        return (new HttpContent($data))->resolve()->getMsgLen();
    }
}