<?php

use Te\Protocols\Stream;
use Te\Server;
use Te\TcpConnection;

require "./vendor/autoload.php";

echo 'pid=' . getmypid() . PHP_EOL;

$server = new Server("tcp://127.0.0.1:12345", null);

$server->on(EVENT_CONNECT, function (Server $server, TcpConnection $connection) {
    fprintf(STDOUT, "客户端连接, ip=%s\n", $connection->getAddress());
});

$server->on(EVENT_RECEIVE, function (Server $server, TcpConnection $connection, $header, $cmd, $load) {
    fprintf(STDOUT, "recvmsg: [%s]header=%d,cmd=%d, load=%s\r\n", $connection->getAddress(), $header, $cmd, $load);
    $connection->send($load);
});

$server->on(EVENT_CLOSE, function (Server $server, TcpConnection $connection) {
    fprintf(STDOUT, "客户端关闭了%s " . PHP_EOL, $connection->getAddress());
});


$server->start();

