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

    private $setting = [];

    /**
     * @var Event
     */
    public $ioEvent;

    /**
     * @var array
     */
    private $pids;

    /**
     * @var string
     */
    private $serviceStatus;

    /**
     * @var int
     */
    private $masterPid;

    /**
     * @var \Socket
     */
    private $taskSocket;

    /**
     * 连接 task 进程的 客户端
     * @var \Socket
     */
    private $workClientSocket;

    /**
     * @throws \Exception
     */
    public function __construct(array $setting = [])
    {
        $this->setting += $setting;
        $this->address = $this->setting['address'];
        $this->protocols = new $this->setting['protocols']();
        $this->statisticsTime = time();
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
        //record(RECORD_DEBUG, "time=%d---接收到客户端:%d---fread=%s---revcmsg=%s", $sub, $this->clientNum, $this->recvNum /1000 .'K', $this->msgNum/ 1000 .'K');
        $this->recvNum = 0;
        $this->msgNum = 0;
        $this->statisticsTime = $now;
    }

    public function start()
    {
        cli_set_process_title("Te/master");
        // 获取 matser 进程id
        $this->setMasterPid();
        // 注册主进程信号
        $this->registerSigV2();
        // fork child process
        $this->forkWork($this->setting['work'], [$this, 'work'], [$this, 'workSuccess']);
        $this->forkWork($this->setting['task']['num'], [$this, 'task'], [$this, 'taskSuccess']);
        // 等待子进程退出
        $this->waitChild();
    }

    public function workSuccess($pid)
    {
        $this->pids[$pid] = "work";
        record(RECORD_INFO, "fork work success,pid=%d", $pid);
    }

    public function taskSuccess($pid)
    {
        $this->pids[$pid] = "task";
        record(RECORD_INFO, "fork task success,pid=%d", $pid);
    }

    /**
     * fork子进程
     *
     * @param int $num
     */
    public function forkWork(int $num, callable $children, callable $forkSuccess): void
    {
        if ($num === 0) {
            return;
        }

        for ($i = 0; $i < $num; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                record(RECORD_ERR, "fork err return -1");
            } elseif ($pid === 0) {
                // 子进程
                $children($i);
                exit(0);
            } else {
                $forkSuccess($pid);
            }
        }
    }

    // work 进程处理请求
    public function work()
    {
        // 需要再每一个子进程里适用自己的 event base
        // 不然当监听只会触发一个子进程
        $this->ioEvent = new $this->setting['event']();
        cli_set_process_title("Te/work");
        $this->listen();
        $this->registerEvent();
        $this->connectTask();
        $this->eventLoop();
    }

    public function task($i)
    {
        $this->ioEvent = new $this->setting['event']();
        cli_set_process_title("Te/task");
        $this->createTask($i);
        $this->registerTaskEvent();
        $this->eventLoop();
    }

    public function createTask($i)
    {
        $serverSocket = sprintf($this->setting['task']['server_socket'], $i);
        @unlink($serverSocket);

        $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        if ($socket === false) {
            err("create task server socket err:" . socket_strerror(socket_last_error()));
        }

        socket_bind($socket, $serverSocket);
        $this->taskSocket = $socket;
        $this->ioEvent->addEvent(socket_export_stream($socket), Event::READ_EVENT, [$this, 'taskAccept']);
    }

    public function taskAccept()
    {
        $data = socket_recvfrom($this->taskSocket, $msg, 65535, 0, $clientAddress);
        if ($data === false) {
            $this->runEvent(EVENT_TASK_CLOSE);
        } else {
            $this->runEvent(EVENT_TASK_RECEIVE, $msg, $clientAddress);
        }
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
        $options['socket']['so_reuseport'] = true;
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
    public function heartbeat(): void
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

    /**
     * 注册 work 进程的信号 定时器等读写事件
     */
    private function registerEvent()
    {
        $childExitFn = function () {
            $this->gc();
            exit(0);

        };
        $this->ioEvent->addTimer("statistics", 1, [$this, "statistics"]);
        pcntl_signal(SIGTERM, SIG_IGN, true);
        pcntl_signal(SIGINT, SIG_IGN, true);
        pcntl_signal(SIGQUIT, SIG_IGN, true);
        $this->ioEvent->addSignal(SIGTERM, $childExitFn);
        $this->ioEvent->addSignal(SIGINT, $childExitFn);
        $this->ioEvent->addSignal(SIGQUIT, $childExitFn);
    }

    /**
     * 信号注册V1版本, 子进程退出回收使用 SIGCHLD 信号进行处理，但是由于信号处理函数的逻辑如果过大，会回收子进程比较慢（因为信号函数是一个一个执行的）
     */
    private function registerSigV1(): void
    {
        $chldFn = function () {
            $id = pcntl_waitpid(-1, $status, WNOHANG);
            record(RECORD_INFO, "回收子进程退出pid=%d,status=%d", $id, $status);
            $pid = pcntl_fork();
            if ($pid == -1) {
                record(RECORD_ERR, "fork err return -1");
            } elseif ($pid == 0) {
                // 子进程
                $this->work();
                exit(0);
            } else {
                // 夫进程
                record(RECORD_INFO, "fork success,pid=%d", $pid);
            }
            unset($$this->pid[$id]);
            $this->pids[$pid] = true;
        };

        if ($this->serviceStatus == 'wait_close') {
            $chldFn = SIG_DFL;
        }

        pcntl_signal(SIGCHLD, $chldFn, true);

        pcntl_signal(SIGTERM, function () {
            $this->serviceStatus = 'wait_close';
            // 发送退出信号
            foreach ($this->pids as $pid => $v) {
                posix_kill($pid, SIGTERM);
                pcntl_waitpid($pid, $status);
                record(RECORD_INFO, "已经回收了子进程 %d", $pid);
            }

            exit(0);
        }, false);

    }

    /**
     * 注册信号V2版本, 回收进程由 master 进程回收
     */
    private function registerSigV2(): void
    {
        $exitFn = function () {
            // 父进程
            $this->serviceStatus = 'wait_close';
            foreach ($this->pids as $pid => $v) {
                posix_kill($pid, SIGTERM);
            }
        };

        pcntl_signal(SIGTERM, $exitFn, true);
        pcntl_signal(SIGINT, $exitFn, true);
        pcntl_signal(SIGQUIT, $exitFn, true);
    }

    /**
     * 监控子进程
     */
    private function waitChild(): void
    {
        while (true) {
            pcntl_signal_dispatch();
            $pid = pcntl_wait($status, WNOHANG);
            if ($pid > 0) {
                record(RECORD_INFO, "回收子进程退出pid=%d,status=%d", $pid, $status);
                $processType = $this->pids[$pid];
                unset($this->pids[$pid]);

                if ($this->serviceStatus != 'wait_close') {
                    $this->forkWork(1, [$this, $processType], [$this, $processType . 'Success']);
                }

                if (empty($this->pids)) {
                    break;
                }
            }
        }
    }

    /**
     * 进行垃圾回收
     */
    private function gc()
    {
        // 暂停事件循环
        $this->ioEvent->stop();
        // 关闭客户端
        foreach (self::$connection as $connect) {
            $this->closeClient($connect->getFd());
        }

        // 关闭socket
        fclose($this->mainSocket);
    }

    /**
     * 设置 master 进程的 pid
     */
    private function setMasterPid()
    {
        $pid = getmypid();
        if ($pid === false) {
            err("获取master pid 失败");
        }

        $this->masterPid = getmypid();
    }

    private function registerTaskEvent()
    {
        $childExitFn = function () {
            // 暂停事件循环
            $this->ioEvent->stop();
            socket_shutdown($this->taskSocket);
            socket_close($this->taskSocket);
            exit(0);

        };
        pcntl_signal(SIGTERM, SIG_IGN, true);
        pcntl_signal(SIGINT, SIG_IGN, true);
        pcntl_signal(SIGQUIT, SIG_IGN, true);
        $this->ioEvent->addSignal(SIGTERM, $childExitFn);
        $this->ioEvent->addSignal(SIGINT, $childExitFn);
        $this->ioEvent->addSignal(SIGQUIT, $childExitFn);
    }

    private function connectTask()
    {
        // 创建客户端 socket
        $pid = getmypid();
        $clientSocket = sprintf($this->setting['task']['client_socket'], $pid);
        @unlink($clientSocket);
        $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($socket, $clientSocket);
        $this->workClientSocket = $socket;
    }

    public function sendTask($data)
    {
        $serverSocket = sprintf($this->setting['task']['server_socket'], rand(0, $this->setting['task']['num'] - 1));
        $len = socket_sendto($this->workClientSocket, $data, strlen($data), 0, $serverSocket);
        record(RECORD_INFO, "send task msg len = %d", $len);
    }
}