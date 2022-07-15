<?php

use Te\Client;
use Te\Protocols\Stream;

require "./vendor/autoload.php";

$client = new Te\Client("tcp://127.0.0.1:12345", new Stream());

$client->on(EVENT_CONNECT, function (Client $client) {
    fprintf(STDOUT, "成功连接上服务端\r\n");
//    $client->send("hello word1");
//    $client->send("hello word2");
//    $client->send("hello word3");
});

$client->on(EVENT_RECEIVE, function (Client $client, $len,  $message) {
//    fprintf(STDOUT, "接收到服务端发送的数据len=%d,message=%s\r\n", $len, $message);
//    $client->send("i am client");
});


$client->on(EVENT_CLOSE, function (Client $client) {
    fprintf(STDOUT, "服务已经关闭\r\n");
});

$client->start();

while (1) {
    $client->statistics();
    if ($client->send("hello,i am client") === false) {
        break;
    }

    if (!$client->eventLoop()) {
        break;
    }

}