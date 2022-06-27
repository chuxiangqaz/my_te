<?php

use Te\Client;
use Te\Protocols\Stream;

require "./vendor/autoload.php";

$client = new Te\Client("tcp://127.0.0.1:12345", new Stream());

$client->on(EVENT_CONNECT, function (Client $client) {
    fprintf(STDOUT, "成功连接上服务端\r\n");
    $client->write("hello word");
});

$client->on(EVENT_RECEIVE, function (Client $client, $header, $cmd, $load) {
    fprintf(STDOUT, "接收到服务端发送的数据header=%d,cmd=%s, load=%s\r\n", $header, $cmd, $load);
});


$client->on(EVENT_CLOSE, function (Client $client) {
    fprintf(STDOUT, "服务已经关闭\r\n");
});

$client->start();
