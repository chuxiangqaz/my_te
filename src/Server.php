<?php

namespace Te;

class Server
{
    /**
     * @var resource
     */
    private $mainSocket;

    /**
     * @var string
     */
    private $address;

    /**
     * 客户端连接 fd
     *
     * @var TcpConnection[]
     */
    private static $connection = [];

    /**
     * 事件注册容器
     *
     * @var array
     */
    private $event;

    /**
     * @throws \Exception
     */
    public function __construct($address)
    {
        $this->address = $address;
    }

    public function on(string $eventName, \Closure $fu)
    {
        $this->event[$eventName] = $fu;
    }

    public function start()
    {
        $this->listen();
        $this->eventLoop();
    }

    public function listen()
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

        // socket, bind, listen
        $socket = stream_socket_server($this->address, $errCode, $errMsg, $flag);
        if (!is_resource($socket)) {
            err("create server err" . $errMsg, $errCode);
        }

        $this->mainSocket = $socket;
    }

    /**
     * 事件循环
     */
    public function eventLoop()
    {
        while (1) {
            $read[] = $this->mainSocket;
            foreach (self::$connection as $connect) {
                $read[] = $connect->getFd();
            }
            $write = [];
            $except = [];

            /**
             * 可读情况：
             *      1. socket 内核接受缓冲区的字节数>= SO_RCVLOWAT 标识，执行读操作返回字节数大于0
             *      2. 对端关闭时，此时读操作返回0
             *      3. 监听 socket 有新的客户端连接时
             *      4. socket 上有未处理的错误, 可使用getsocketopt 来读取和清除错误
             * 可写情况
             *      1. socket 内核发送缓冲区的可用字节数 >= SO_ANDLOWAT , 执行些操作字节数大于0
             *      2. 对端关闭, 写操作会触发 SIGPIPE 中断信号
             *      3. socket 有未处理的错误时候
             * 异常情况
             *      就是发送紧急数据（带外数据）时
             * ---------------------
             * select 可读事件发生：
             * 当计算机的网卡收到数据时（对端关闭，socket错误，监听socket上的可读事件不谈），会把数据写入到内存中并向CPU发起硬件中断请求，CPU会响应去执行中断程序
             * 并把数据（会根据端口号找到socket文件描述符）写入到对应的socket内核接受缓冲区中
             * 同时唤醒当前进程（select 返回）
             *  TODO 服务端端口只有一个是如何找到不一样的socket文件描述符
             */
            $numChange = stream_select($read, $write, $except, null);
            if ($numChange === false || $numChange < 0) {
                err("stream_select err");
            }

            if ($read) {
                foreach ($read as $fd) {
                    // 监听socket
                    if ($fd === $this->mainSocket) {
                        $this->accept();
                    } else {
                        // 连接 socket
                        self::$connection[(int)$fd]->recv();
                    }
                }
            }
        }
    }

    public function runEvent($eventName, ...$args)
    {
        if (!isset($eventName)) {
            err("not register event:$eventName");
        }

        $this->event[$eventName](...$args);
    }

    public function accept()
    {
        // 不设置超时事件,将默认使用php.ini 里面配置的事件.
        $fd = stream_socket_accept($this->mainSocket, -1, $address);
        if (!is_resource($fd)) {
            record(RECORD_ERR, "access is not resource");
            return;
        }
        $connection = new TcpConnection($fd, $address, $this);
        if (isset($this->event[EVENT_CONNECT])) {
            $this->runEvent(EVENT_CONNECT, $this, $connection);
        }

        self::$connection[(int)$fd] = $connection;
    }
}