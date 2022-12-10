<?php

namespace Te\Command;

use Te\Protocols\HTTP\Request;
use Te\Protocols\HTTP\Response;
use Te\Server;
use Te\TcpConnection;

class StartCommand extends Command
{

    public $signature = 'start';

    public $desc = 'start te server';

    /**
     * @var Server
     */
    private $server;

    public function exec()
    {
        if ($this->masterExist()) {
            err("process is exist");
        }


        $server = new Server($this->config);
        $this->server = $server;
        $this->httpServer();
        file_put_contents($this->config["pid"], getmypid());
        $server->start();
    }

    public function httpServer()
    {
        $server = $this->server;
        $server->on(EVENT_HTTP_REQUEST, function (Request $request, Response $response) {
            $response->send();
        });
    }

    public function tcpServer()
    {
        $server = $this->server;
        $server->on(EVENT_CONNECT, function (Server $server, TcpConnection $connection) {
            record(RECORD_DEBUG, "客户端连接, ip=%s\n", $connection->getAddress());
        });

        $server->on(EVENT_RECEIVE, function (Server $server, TcpConnection $connection, $len, $message) {
            record(RECORD_DEBUG, "recvmsg: [%s]len=%d,message=%s\r\n", $connection->getAddress(), $len, $message);
            if (trim($message) == "hello") {
                $server->sendTask(function () use ($message) {
                    sleep(20);
                    record(RECORD_INFO, "我已经把异步任务处理完了");
                });
            } else {
                $server->sendTask($message);
            }
            // 已经关闭了
            $connection->send(" i am server");
        });

        $server->on(EVENT_CLOSE, function (Server $server, TcpConnection $connection) {
            record(RECORD_DEBUG, "客户端关闭了%s \n", $connection->getAddress());
        });

        $server->on(EVENT_READ_BUFFER_FULL, function (Server $server, TcpConnection $connection) {
            record(RECORD_DEBUG, "接受缓冲区满了\n");
        });

        $server->on(EVENT_WRITE_BUFFER_FULL, function (Server $server, TcpConnection $connection) {
            record(RECORD_DEBUG, "发送缓冲区满了\n");
        });

        $server->on(EVENT_TASK_RECEIVE, function ($msg, $clientAddress) {
            record(RECORD_DEBUG, "task进程接受到的数据:" . $msg);
        });

        $server->on(EVENT_TASK_CLOSE, function () {
            record(RECORD_DEBUG, "task客户端断开连接");
        });
    }


}