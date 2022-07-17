<?php

use Te\Protocols\Stream;
use Te\Server;
use Te\TcpConnection;

require "./vendor/autoload.php";

echo 'pid=' . getmypid() . PHP_EOL;

$server = new Server("tcp://127.0.0.1:12345", new Stream());

$server->on(EVENT_CONNECT, function (Server $server, TcpConnection $connection) {
    fprintf(STDOUT, "客户端连接, ip=%s\n", $connection->getAddress());
});

$server->on(EVENT_RECEIVE, function (Server $server, TcpConnection $connection, $len, $message) {
    //fprintf(STDOUT, "recvmsg: [%s]len=%d,message=%s\r\n", $connection->getAddress(), $len, $message);
    $connection->send("i am server!");
});

$server->on(EVENT_CLOSE, function (Server $server, TcpConnection $connection) {
    fprintf(STDOUT, "客户端关闭了%s " . PHP_EOL, $connection->getAddress());
});

$server->on(EVENT_BUFFER_FULL, function (Server $server, TcpConnection $connection) {
    fprintf(STDOUT, "接受缓冲区满了");
});


$server->start();

