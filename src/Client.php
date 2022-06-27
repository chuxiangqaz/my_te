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


    public function __construct($address, Protocols $protocols)
    {
        $this->address = $address;
        $this->protocols = $protocols;
    }

    public function start()
    {
        $this->connect();
        $this->eventLoop();
    }


    public function on(string $eventName, \Closure $fu)
    {
        $this->event[$eventName] = $fu;
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
        $this->runEvent(EVENT_CONNECT, $this);
    }

    /**
     * 进入事件循环
     */
    public function eventLoop()
    {
        while (1) {
            $read = [$this->mainSocket];
            $write = [];
            $except = [];
            $numChange = stream_select($read, $write, $except, null, null);
            if ($numChange === false || $numChange < 0) {
                err("stream_select err");
            }

            if ($read && $this->recv() === false) {
                break;
            }
        }
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
                    $this->runEvent(EVENT_CLOSE, $this);
                }
                return false;
            }

            $this->recvLen += strlen($data);
            $this->bufferData .= $data;


            // 接受客户端数据
            $this->runEvent(EVENT_RECEIVE, $this, $data);
        } else {
            $this->recvBufferFull++;
        }
    }

    /**
     * 给客户端发生数据
     *
     * @param string $data
     * @return void
     */
    public function write($data)
    {
        $len = stream_socket_sendto($this->mainSocket, $data, 0);
        fprintf(STDOUT, "send msg len=%d\n", $len);
    }

    public function runEvent($eventName, ...$args)
    {
        if (!isset($eventName)) {
            err("not register event:$eventName");
        }

        $this->event[$eventName](...$args);
    }
}