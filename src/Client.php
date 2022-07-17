<?php

namespace Te;

use Te\Protocols\Protocols;

class Client
{

    /**
     * @var string
     */
    private $address;
    /**
     * @var resource
     */
    private $mainSocket;


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
     * 表示当前连接接受的字节数大小
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
     *  接受数据的边界
     *
     * @var int
     */
    private $readBufferSize = 1024;

    /**
     * 事件注册容器
     *
     * @var array
     */
    private $event;

    /**
     * 事件实例
     *
     * @var Protocols
     */
    private $protocols;

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
     * 初始状态
     *
     * @var int
     */
    private $status = 0;

    /**
     * 客户端数量
     *
     * @var int
     */
    private $clientNum = 0;


    /**
     * 调用 write 函数的次数
     *
     * @var int
     */
    private $writeNum = 0;

    /**
     * 发送消息的数量
     *
     * @var int
     */
    private $msgNum = 0;


    /**
     * 统计时间
     *
     * @var int
     */
    private $statisticsTime = 0;


    const STATUS_ESTABLISHED = 9;
    const STATUS_CLOSE = 10;


    public function __construct($address, Protocols $protocols)
    {
        $this->address = $address;
        $this->protocols = $protocols;
        $this->statisticsTime = time();
    }

    public function start()
    {
        $this->connect();
        //$this->eventLoop();
    }


    public function on(string $eventName, \Closure $fu)
    {
        $this->event[$eventName] = $fu;
    }


    public function statistics()
    {
        $now = time();
        if (($sub = $now - $this->statisticsTime) < 1) {
            return;
        }
        fprintf(STDOUT, "time=%d----socket=%d---fwrite=%s---sendMsg=%s\r\n", $sub, (int)$this->mainSocket, $this->writeNum /1000 .'K', $this->msgNum/ 1000 .'K');
        $this->writeNum = 0;
        $this->msgNum = 0;
        $this->statisticsTime = $now;
    }

    /**
     * 关闭
     */
    public function onClose()
    {
        $this->status = self::STATUS_CLOSE;
        $this->mainSocket = null;
        $this->runEvent(EVENT_CLOSE, $this);
    }

    /**
     * 判断是否是有效的连接
     *
     * @return bool
     */
    private function validConnect(): bool
    {
        return $this->status == self::STATUS_ESTABLISHED && is_resource($this->mainSocket);
    }

    public function connect()
    {
        $protocol = substr($this->address, 0, 3);
        $flag = null;
        switch (strtolower($protocol)) {
            case 'tcp':
                $flag = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
                break;
            case 'udp':
                $flag = STREAM_SERVER_BIND;
                break;
            default:
                err("not support $protocol server");
        }

        // socket, connect
        $socket = stream_socket_client($this->address, $errCode, $errMsg, $flag);
        if (!is_resource($socket)) {
            err("create server err" . $errMsg, $errCode);
        }

        $this->mainSocket = $socket;
        $this->status = self::STATUS_ESTABLISHED;
        $this->runEvent(EVENT_CONNECT, $this);
    }


    /**
     * 进入事件循环
     */
    public function eventLoop()
    {
//        while (1) {
            if (!$this->validConnect()) {
//                break;
                return false;
            }

            $read = [$this->mainSocket];
            $write = [$this->mainSocket];
            $except = [$this->mainSocket];
            $numChange = stream_select($read, $write, $except, null, null);
            if ($numChange === false || $numChange < 0) {
                err("stream_select err");
            }

            if ($read) {
                $this->recv();
            }

            if ($write) {
                $this->write2socket();
            }

            return true;
//        }
    }


    /**
     * 接受服务端数据
     */
    public function recv()
    {
        if ($this->recvLen < $this->recvBufferSize) {
            $data = fread($this->mainSocket, $this->readBufferSize);
            if ($data === "" || $data === false) {
                if (feof($this->mainSocket) || !is_resource($this->mainSocket)) {
                    // 服务端关闭
                    $this->onClose();
                    return;
                }
            }

            $this->recvLen += strlen($data);
            $this->bufferData .= $data;

        } else {
            $this->recvBufferFull++;
        }

        // 判断数据是否完整
        while ($this->protocols->integrity($this->bufferData)) {

            // 获取消息长度
            $msgLen = $this->protocols->msgLen($this->bufferData);
            $msg = substr($this->bufferData, 0, $msgLen);
            // 解码数据
            [$header, $cmd, $load] = $this->protocols->decode($msg);
            $this->bufferData = substr($this->bufferData, $header);
            $this->recvLen -= $header;

            // 接受客户端数据
            $this->runEvent(EVENT_RECEIVE, $this, $header, $load);
        }
    }

    /**
     * 给服务端发生数据
     *
     * @param string $data
     * @return void
     */
    public function write($data)
    {
        $package = $this->protocols->encode($data);
        $len = stream_socket_sendto($this->mainSocket, $package, 0);
        fprintf(STDOUT, "send msg len=%d\n", $len);
    }

    /**
     * 使用缓冲区发送
     *
     * @param $data
     */
    public function send($data)
    {
        if (!$this->validConnect()) {
            return false;
        }

        $package = $this->protocols->encode($data);
        $this->msgNum++;
        if ($this->sendLen + strlen($package) < $this->sendBufferSize) {
            $this->sendLen += strlen($package);
            $this->sendBuffer .= $package;
        } else {
            $this->sendBufferFull++;
        }
    }


    /**
     * 发送缓冲区内容
     */
    private function write2socket(): void
    {
        if (!$this->needWrite()) {
            return;
        }

        // 1. 发送长度等于缓冲区长度  2. 发送长度 < 缓冲区长度  3. 对端关闭
        $sendLen = fwrite($this->mainSocket, $this->sendBuffer, $this->sendLen);
        //fprintf(STDOUT, "send msg len=%d\n", $sendLen);
        $this->writeNum++;
        if ($sendLen === $this->sendLen) {
            $this->sendBuffer = '';
            $this->sendLen = 0;
        } elseif ($sendLen > 0) {
            $this->sendBuffer .= substr($this->sendBuffer, $sendLen);
            $this->sendLen -= $sendLen;
        } else {
            // 对端关闭
            $this->onClose();
        }
    }

    /**
     * 判断是否需要发送
     *
     * @return bool
     */
    public function needWrite() :bool
    {
        return $this->sendLen > 0;
    }

    public function runEvent($eventName, ...$args)
    {
        if (!isset($eventName)) {
            err("not register event:$eventName");
        }

        $this->event[$eventName](...$args);
    }
}