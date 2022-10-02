<?php

namespace Te;

use Te\Event\Event;
use Te\Protocols\Protocols;

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
     * @var Protocols
     */
    private $protocols;

    /**
     * 客户端数量
     *
     * @var int
     */
    private $clientNum = 0;


    /**
     * 调用 recv 函数的次数
     *
     * @var int
     */
    private $recvNum = 0;

    /**
     * 接受消息的数量
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

    /**
     * @var Event
     */
    public $ioEvent;

    /**
     * @throws \Exception
     */
    public function __construct($address, ?Protocols $protocols, Event $event)
    {
        $this->address = $address;
        $this->protocols = $protocols;
        $this->statisticsTime = time();
        $this->ioEvent = $event;
    }

    public function on(string $eventName, \Closure $fu)
    {
        $this->event[$eventName] = $fu;
    }

    /**
     * 加入客户端
     *
     * @param TcpConnection $connection
     */
    public function onJoin(TcpConnection $connection)
    {
        $this->clientNum++;
        if (isset($this->event[EVENT_CONNECT])) {
            $this->runEvent(EVENT_CONNECT, $this, $connection);
        }
    }

    /**
     * 接受到消息
     * @param TcpConnection $connection
     */
    public function onRecve(TcpConnection $connection)
    {
        $connection->heatTime = time();
        $this->recvNum++;
    }


    /**
     * 接收到数据包文
     *
     * @param TcpConnection $connection
     * @param int $msgLen
     * @param string $msg
     */
    public function onRecvMsg(TcpConnection $connection, int $msgLen, string $msg)
    {
        $this->msgNum++;
        $this->runEvent(EVENT_RECEIVE, $this, $connection, $msgLen, $msg);
    }

    /**
     * 客户端关闭
     *
     * @param $fd
     * @return void
     */
    public function closeClient($fd)
    {
        $this->ioEvent->delEvent($fd, Event::READ_EVENT);
        $this->ioEvent->delEvent($fd, Event::WRITE_EVENT);
        $this->runEvent(EVENT_CLOSE, $this, self::$connection[(int)$fd]);
        unset(self::$connection[(int)$fd]);
        @fclose($fd);
        $this->clientNum--;
    }

    public function statistics()
    {
        $now = time();
        if (($sub = $now - $this->statisticsTime) < 1) {
            return;
        }
        fprintf(STDOUT, "time=%d---接收到客户端:%d---fread=%s---revcmsg=%s\r\n", $sub, $this->clientNum, $this->recvNum /1000 .'K', $this->msgNum/ 1000 .'K');
        $this->recvNum = 0;
        $this->msgNum = 0;
        $this->statisticsTime = $now;
    }

    public function start()
    {
        $this->listen();
        $this->registerEvent();
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

        // 设置套接字半连接队列大小
        $options['socket']['backlog'] = 1000;
        $context = stream_context_create($options);

        // socket, bind, listen
        $socket = stream_socket_server($this->address, $errCode, $errMsg, $flag, $context);
        if (!is_resource($socket)) {
            err("create server err" . $errMsg, $errCode);
        }

        $this->mainSocket = $socket;
        stream_set_blocking($this->mainSocket, false);
        $this->ioEvent->addEvent($this->mainSocket, Event::READ_EVENT, [$this, "accept"]);
    }

    /**
     * 心跳检查
     */
    public function heartbeat() :void
    {
        foreach (self::$connection as $connect) {
            if (time() - $connect->heatTime > 10) {
                fprintf(STDOUT, "[%s]心跳超时\r\n", $connect->getAddress());
                $this->closeClient($connect->getFd());
            }
        }
    }

    /**
     * 事件循环
     */
    public function eventLoop()
    {
        $this->ioEvent->eventLoop();
//        while (1) {
//            $this->statistics();
//            //$this->heartbeat();
//            $read = [];
//            $read[] = $this->mainSocket;
//            $write = [];
//            $except = [];
//            foreach (self::$connection as $connect) {
//                $read[] = $connect->getFd();
//                $write[] = $connect->getFd();
//            }
//
//            /**
//             * 可读情况：
//             *      1. socket 内核接受缓冲区的字节数>= SO_RCVLOWAT 标识，执行读操作返回字节数大于0
//             *      2. 对端关闭时，此时读操作返回0
//             *      3. 监听 socket 有新的客户端连接时
//             *      4. socket 上有未处理的错误, 可使用getsocketopt 来读取和清除错误
//             * 可写情况
//             *      1. socket 内核发送缓冲区的可用字节数 >= SO_ANDLOWAT , 执行些操作字节数大于0
//             *      2. 对端关闭, 写操作会触发 SIGPIPE 中断信号
//             *      3. socket 有未处理的错误时候
//             * 异常情况
//             *      就是发送紧急数据（带外数据）时
//             * ---------------------
//             * select 可读事件发生：
//             * 当计算机的网卡收到数据时（对端关闭，socket错误，监听socket上的可读事件不谈），会把数据写入到内存中并向CPU发起硬件中断请求，CPU会响应去执行中断程序
//             * 并把数据（会根据端口号找到socket文件描述符）写入到对应的socket内核接受缓冲区中
//             * 同时唤醒当前进程（select 返回）
//             *  TODO 服务端端口只有一个是如何找到不一样的socket文件描述符
//             */
//            $numChange = stream_select($read, $write, $except, null);
//            if ($numChange === false || $numChange < 0) {
//                err("stream_select err");
//            }
//
//            if ($read) {
//                foreach ($read as $fd) {
//                    // 监听socket
//                    if ($fd === $this->mainSocket) {
//                        $this->accept();
//                    } else {
//                        // 连接 socket
//                        self::$connection[(int)$fd]->recv();
//                    }
//                }
//            }
//
//            if ($write) {
//                foreach ($write as $fd) {
//                        if (isset(self::$connection[(int)$fd])) {
//                            // 连接 socket
//                            self::$connection[(int)$fd]->write2socket();
//                        }
//
//                }
//            }
//        }
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
        $connection = new TcpConnection($fd, $address, $this, $this->protocols);
        self::$connection[(int)$fd] = $connection;
        $this->ioEvent->addEvent($fd, Event::READ_EVENT, [$connection, "recv"]);
        // 不添加可写事件,在需要写入的时候在添加可写事件，减少CPU通知消耗
        //$this->ioEvent->addEvent($fd, Event::WRITE_EVENT, [$connection, "write2socket"]);
        $this->onJoin($connection);
    }

    private function registerEvent()
    {
        //$this->ioEvent->addTimer("statistics", 1, [$this, "statistics"]);
    }


}