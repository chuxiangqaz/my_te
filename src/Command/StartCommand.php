<?php

namespace Te\Command;

use Te\Server;
use Te\TcpConnection;

class StartCommand extends Command
{

    public $signature = 'start';

    public function exec()
    {
        if ($this->masterExist()) {
            err("process is exist");
        }


        $server = new Server($this->config);

        $server->on(EVENT_CONNECT, function (Server $server, TcpConnection $connection) {
            record(RECORD_DEBUG, "客户端连接, ip=%s\n", $connection->getAddress());
        });

        $server->on(EVENT_RECEIVE, function (Server $server, TcpConnection $connection, $len, $message) {
            record(RECORD_DEBUG, "recvmsg: [%s]len=%d,message=%s\r\n", $connection->getAddress(), $len, $message);
            // 已经关闭了
            $connection->send(" i am server");
            $server->sendTask("task异步处理吧");
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
            record(RECORD_DEBUG, "task进程接受到的数据". $msg);
        });

        $server->on(EVENT_TASK_CLOSE, function () {
            record(RECORD_DEBUG, "task客户端断开连接");
        });

        file_put_contents($this->config["pid"], getmypid());
        $server->start();
    }


}