<?php

use Te\Client;

require "./vendor/autoload.php";

$client = new Te\Client("tcp://127.0.0.1:12345", new Te\Protocols\Text());

$client->on(EVENT_CONNECT, function (Client $client) {
    fprintf(STDOUT, "成功连接上服务端\r\n");
    $client->send("i am client");
});

$client->on(EVENT_RECEIVE, function (Client $client, $len, $message) {
    fprintf(STDOUT, "接收到服务端发送的数据len=%d,message=%s\r\n", $len, $message);
    $client->send("i am client");
});

$client->on(EVENT_WRITE_BUFFER_FULL, function (Client $client) {
    fprintf(STDOUT, "发送缓冲区满了\r\n");
});

$client->on(EVENT_READ_BUFFER_FULL, function (Client $client) {
    fprintf(STDOUT, "接受缓冲区满了\r\n");
});

$client->on(EVENT_CLOSE, function (Client $client) {
    fprintf(STDOUT, "服务已经关闭\r\n");
});

$client->start();

