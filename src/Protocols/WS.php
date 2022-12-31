<?php

namespace Te\Protocols;

use Te\Protocols\HTTP\Request;
use Te\Protocols\HTTP\Response;
use Te\Protocols\WS\Frame;

/**
 * websocket 协议
 * @link  https://zhuanlan.zhihu.com/p/407711596
 */
class WS implements Protocols
{
    public const STATUS_WAIT = 'wait';
    public const STATUS_HANDSHAKE = 'handshake';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CLOSE = 'close';

    // websocket 状态
    // wait 等待客户端发送握手协议
    // handshake 已经握手成功
    // failed  握手失败
    //  close 关闭
    protected $status = self::STATUS_WAIT;

    /**
     * 判断数据是否完整
     *
     * @param $data
     * @return bool
     */
    public function integrity($data): bool
    {
        if (strlen($data) <=0) {
            return false;
        }

        // 还没握手
        if ($this->status === self::STATUS_WAIT) {
            $request = new Request($data);
            return $request->checkIntegrity();

        }

        if ($this->status === self::STATUS_HANDSHAKE) {
            (new Frame($data));

        }

        return false;
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
        // 还没握手
        if ($this->status === self::STATUS_WAIT) {
            return (new Request($data))->resolve();

        }

        if ($this->status === self::STATUS_HANDSHAKE) {
            // TODO

        }

        return false;
    }

    /**
     * 获取一条消息总长度
     *
     * @param string $data
     * @return int
     */
    public function msgLen($data = ''): int
    {
        // 还没握手
        if ($this->status === self::STATUS_WAIT) {
            $request = new Request($data);
            return $request->msgLen();

        }

        if ($this->status === self::STATUS_HANDSHAKE) {
            // TODO

        }

        return false;
    }

    /**
     * @param $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }
}