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
        $this->wsServer();
        $server->start();
    }

    public function wsServer()
    {
        $server = $this->server;
        $server->on(EVENT_WS_HANDSHAKE_SUCCESS, function ($msg) {
            record(RECORD_DEBUG, "websocket 握手成功");
        });
        $server->on(EVENT_WS_HANDSHAKE_FAIL, function ($msg) {
            record(RECORD_DEBUG, "websocket 握手失败");
        });
    }

    public function httpServer()
    {
        $server = $this->server;
        $server->on(EVENT_CONNECT, function (Server $server, TcpConnection $connection) {
            record(RECORD_DEBUG, "客户端连接, ip=%s\n", $connection->getAddress());
        });
        $server->on(EVENT_HTTP_REQUEST, function (Request $request, Response $response) {
            $response->sendText('s');
            //$response->sendFile(trim($request->getPath(), "/"));
//            $response->getConnection()->send("HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nTransfer-Encoding: Chunked\r\n\r\n");
//            $response->getConnection()->send("1\r\na\r\n");
//            sleep(3);
//            $response->getConnection()->send("2\r\nbc\r\n");
//            sleep(3);
//            $response->getConnection()->send("3\r\ndef\r\n");
//            $response->getConnection()->send("0\r\n\r\n");
            //$response->sendFile(trim($request->getPath(), "/"));
            //$response->sendJson(["name" => 'cx', 'path' => $request->getPath()]);
//            $response->chunk("hello");
//            sleep(4);
//            $response->chunk("cx");
//            sleep(4);
//            $response->chunk("!");
//            $response->end();


        });
        $server->on(EVENT_CLOSE, function (Server $server, TcpConnection $connection) {
            record(RECORD_DEBUG, "客户端关闭了%s \n", $connection->getAddress());
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