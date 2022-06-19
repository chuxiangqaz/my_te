<?php

use Te\Server;
use Te\TcpConnection;

require "./vendor/autoload.php";

echo 'pid=' . getmypid() . PHP_EOL;

$server = new Server("tcp://127.0.0.1:12345");

$server->on(EVENT_CONNECT, function (Server $server, TcpConnection $connection) {
    fprintf(STDOUT, "客户端连接, ip=%s\n", $connection->getAddress());
});

$server->on(EVENT_RECEIVE, function (Server $server, TcpConnection $connection, $data) {
    fprintf(STDOUT, "recvmsg: [%s]%s", $connection->getAddress(), $data);
    $connection->write($data);
});


$server->listen();

$server->eventLoop();

