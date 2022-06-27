<?php

require "./vendor/autoload.php";

$client = new Te\Client("tcp://127.0.0.1:12345");

$client->on(EVENT_CONNECT, function (\Te\Client $client) {
    fprintf(STDOUT, "成功连接上服务端\r\n");
    $client->write("hello word");
});

$client->on(EVENT_RECEIVE, function (\Te\Client $client, $data) {
    fprintf(STDOUT, "接收到服务端发送的数据data=%s,len=%d\r\n", $data, strlen($data));
});

$client->start();
