<?php

namespace Te\Protocols;

use Te\Protocols\HTTP\Request;
use Te\Protocols\WS\Frame;


/**
+-+-+-+-+-------+-+-------------+-------------------------------+
|F|R|R|R| opcode|M| Payload len |    Extended payload length    |
|I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
|N|V|V|V|       |S|             |   (if payload len==126/127)   |
| |1|2|3|       |K|             |                               |
+-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
|     Extended payload length continued, if payload len == 127  |
+ - - - - - - - - - - - - - - - +-------------------------------+
|                               |Masking-key, if MASK set to 1  |
+-------------------------------+-------------------------------+
| Masking-key (continued)       |          Payload Data         |
+-------------------------------- - - - - - - - - - - - - - - - +
:                     Payload Data continued ...                :
+ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
|                     Payload Data continued ...                |
+---------------------------------------------------------------+

 **/

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
        if (strlen($data) <= 2) {
            return false;
        }

        // 未握手发送HTTP请求
        if ($this->status === self::STATUS_WAIT) {
            $request = new Request($data);
            return $request->checkIntegrity();

        }

        // 握手成功发送数据帧
        if ($this->status === self::STATUS_HANDSHAKE) {
            return (new Frame($data))->integrity();

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
     * @return Frame|Request|false 返回报文内容
     */
    public function decode($data = '')
    {
        // 未握手发送HTTP请求
        if ($this->status === self::STATUS_WAIT) {
            return (new Request($data))->resolve();

        }

        // 握手成功发送数据帧
        if ($this->status === self::STATUS_HANDSHAKE) {
            return (new Frame($data))->resolve();
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
        // 未握手发送HTTP请求
        if ($this->status === self::STATUS_WAIT) {
            $request = new Request($data);
            return $request->msgLen();

        }

        // 握手成功发送数据帧
        if ($this->status === self::STATUS_HANDSHAKE) {
            return (new Frame($data))->msgLen();
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