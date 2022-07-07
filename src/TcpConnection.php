<?php

namespace Te;

use Te\Protocols\Protocols;

class TcpConnection
{

    /**
     * 客户端地址信息
     *
     * @var string
     */
    private $address;

    /**
     * 连接 socket
     *
     * @var resource
     */
    private $fd;

    /**
     * @var Server
     */
    private $server;

    /**
     * 表示当前连接接受缓冲区的大小
     *
     * @var int
     */
    private $recvBufferSize = 1024 * 100;


    /**
     * 表示当前连接目前接收的字节数大小
     *
     * @var int
     */
    private $recvLen = 0;

    /**
     * 表示当前连接缓冲区已满次数
     *
     * @var int
     */
    private $recvBufferFull = 0;

    /**
     * 表示从缓冲区中拿到的数据
     *
     * @var string
     */
    private $bufferData = '';

    /**
     * 表示当前连接的发送缓冲区长度
     *
     * @var int
     */
    private $sendLen = 0;

    /**
     * 表示当前连接的发送缓冲区数据
     *
     * @var string
     */
    private $sendBuffer = '';

    /**
     * 表示发送缓冲区
     *
     * @var int
     */
    private $sendBufferSize = 1024 * 100;

    /**
     * 发送缓冲区满的次数
     *
     * @var int
     */
    private $sendBufferFull = 0;


    /**
     *  接受数据的边界
     *
     * @var int
     */
    private $readBufferSize = 1024;

    /**
     * @var Protocols
     */
    private $protocols;


    public function __construct($fd, $address, $server, ?Protocols $protocols)
    {
        $this->fd = $fd;
        $this->address = $address;
        $this->server = $server;
        $this->protocols = $protocols;
    }

    /**
     * 接受客户端数据
     */
    public function recv()
    {
        if ($this->recvLen < $this->recvBufferSize) {
            $data = fread($this->fd, $this->readBufferSize);
            if ($data === "" || $data === false) {
                if (feof($this->fd) || !is_resource($this->fd)) {
                    // 客户端关闭
                    $this->server->closeClient($this->fd);
                }
                return;

            }

            $this->recvLen += strlen($data);
            $this->bufferData .= $data;

        } else {
            $this->recvBufferFull++;
        }

        $this->handleMessage();

    }

    private function handleMessage()
    {
        if (is_null($this->protocols)) {
            // 没有协议的TCP数据
            // 接受客户端数据
            $this->server->runEvent(EVENT_RECEIVE, $this->server, $this, $this->recvLen, $this->bufferData);
            $this->bufferData = '';
            $this->recvLen = 0;
            $this->recvBufferFull = 0;
        } else {
            // 判断数据是否完整
            while ($this->protocols->integrity($this->bufferData)) {

                // 获取消息长度
                $msgLen = $this->protocols->msgLen($this->bufferData);
                $msg = substr($this->bufferData,0, $msgLen);
                // 解码数据
                [$header, $cmd, $load] = $this->protocols->decode($msg);
                $this->bufferData = substr($this->bufferData, $header);
                $this->recvLen -=$header;

                // 接受客户端数据
                $this->server->runEvent(EVENT_RECEIVE, $this->server, $this, $header, $load);
            }
        }
    }

    public function send($data)
    {
        $package = is_null($this->protocols) ? $data : $this->protocols->encode($data);

        if ($this->sendLen + strlen($package) < $this->sendBufferSize) {
            $this->sendLen += strlen($package);
            $this->sendBuffer .= $package;
        } else {
            $this->sendBufferFull++;
        }

        // 1. 发送长度等于缓冲区长度  2. 发送长度 < 缓冲区长度  3. 对端关闭
        $sendLen = fwrite($this->fd, $this->sendBuffer, $this->sendLen);
        fprintf(STDOUT, "send msg len=%d\n", $sendLen);
        if ($sendLen === $this->sendLen) {
            $this->sendBuffer = '';
            $this->sendLen = 0;
        } elseif ($sendLen > 0) {
            $this->sendBuffer .= substr($this->sendBuffer, $sendLen);
            $this->sendLen -= $sendLen;
        } else {
            // 对端关闭
            $this->server->closeClient($this->fd);
        }
    }

//    /**
//     * 给客户端发生数据
//     *
//     * @param string $data
//     * @return void
//     */
//    public function write($data)
//    {
//        $package = $this->protocols->encode($data);
//        $len = stream_socket_sendto($this->fd, $package, 0);
//        fprintf(STDOUT, "send msg len=%d\n", $len);
//    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @return resource
     */
    public function getFd()
    {
        return $this->fd;
    }


}