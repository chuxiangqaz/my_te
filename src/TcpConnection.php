<?php

namespace Te;

use Te\Event\Event;
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
    private $recvBufferSize = 1024 * 1024 * 2;


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
    private $sendBufferSize = 1 * 1024 * 1024; //1MB

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

    /**
     * 心跳时间
     *
     * @var int
     */
    public $heatTime = 0;


    public function __construct($fd, $address, $server, ?Protocols $protocols)
    {
        $this->fd = $fd;
        stream_set_blocking($fd, false);
        stream_set_read_buffer($fd, 0);
        stream_set_write_buffer($fd, 0);
        $this->address = $address;
        $this->server = $server;
        $this->protocols = $protocols;
        $this->heatTime = time();
    }

    /**
     * 接受客户端数据
     */
    public function recv()
    {
        if ($this->recvLen < $this->recvBufferSize) {
            $data = fread($this->fd, $this->readBufferSize);
            $this->server->onRecve($this);
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
            $this->server->runEvent(EVENT_READ_BUFFER_FULL, $this->server, $this);
        }

        $this->handleMessage();

    }

    /**
     * 处理消息内容
     */
    private function handleMessage()
    {
        if (is_null($this->protocols)) {
            // 没有协议的TCP数据
            // 接受客户端数据
            $this->server->onRecvMsg($this, $this->recvLen, $this->bufferData);
            $this->bufferData = '';
            $this->recvLen = 0;
            $this->recvBufferFull = 0;
        } else {
            // 判断数据是否完整
            while ($this->protocols->integrity($this->bufferData)) {

                // 获取消息长度
                $msgLen = $this->protocols->msgLen($this->bufferData);
                $msg = substr($this->bufferData, 0, $msgLen);
                // 解码数据
                $load = $this->protocols->decode($msg);
                $this->bufferData = substr($this->bufferData, $msgLen);
                $this->recvLen -= $msgLen;
                // 接受客户端数据
                $this->server->onRecvMsg($this, $msgLen, $load);
            }
        }
    }

    /**
     * 发送到缓冲区
     *
     * @param $data
     */
    public function send($data)
    {
        $package = is_null($this->protocols) ? $data : $this->protocols->encode($data);

        if ($this->sendLen + strlen($package) < $this->sendBufferSize) {
            $this->sendLen += strlen($package);
            $this->sendBuffer .= $package;
        } else {
            $this->sendBufferFull++;
            $this->server->runEvent(EVENT_WRITE_BUFFER_FULL, $this->server, $this);
        }

        $this->write2socket();

    }

    /**
     * 是否能发送
     *
     * @return bool
     */
    private function needWrite(): bool
    {
        return $this->sendLen > 0;
    }


    /**
     * 发送缓冲区内容
     */
    public function write2socket(): void
    {
        if (!$this->needWrite()) {
            return;
        }

        // 1. 发送长度等于缓冲区长度  2. 发送长度 < 缓冲区长度  3. 对端关闭
        $sendLen = fwrite($this->fd, $this->sendBuffer, $this->sendLen);
        //fprintf(STDOUT, "send msg len=%d\n", $sendLen);
        if ($sendLen === $this->sendLen) {
            $this->sendBuffer = '';
            $this->sendLen = 0;
            $this->server->ioEvent->delEvent($this->fd, Event::WRITE_EVENT);
        } elseif ($sendLen > 0) {
            $this->sendBuffer .= substr($this->sendBuffer, $sendLen);
            $this->sendLen -= $sendLen;
            $this->server->ioEvent->addEvent($this->fd, Event::WRITE_EVENT, [$this, "write2socket"]);
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

    public function close(): void
    {
        $this->server->closeClient($this->fd);
    }
}