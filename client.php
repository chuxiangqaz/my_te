<?php

use Te\Client;
use Te\Protocols\Stream;

require "./vendor/autoload.php";
/*

当超过1024个客户端的时候就会报错
PHP Warning:  stream_select(): You MUST recompile PHP with a larger value of FD_SETSIZE.
It is set to 1024, but you have descriptors numbered at least as high as 1024.
--enable-fd-setsize=2048 is recommended, but you may want to set it
to equal the maximum number of open files supported by your system,
in order to avoid seeing this error again at a later date. in /data/my_te/src/Client.php on line 233
*/

$clientNum = $argv[1] ?? 1;

/** @var Client[] $clients */
$clients = [];

for ($i = 0; $i < $clientNum; $i++) {
    $client = new Te\Client("tcp://127.0.0.1:12345", new Stream());

    $client->on(EVENT_CONNECT, function (Client $client) {
        fprintf(STDOUT, "成功连接上服务端\r\n");
    });

    $client->on(EVENT_RECEIVE, function (Client $client, $len, $message) {
        fprintf(STDOUT, "接收到服务端发送的数据len=%d,message=%s\r\n", $len, $message);
        //$client->send("i am client");
    });

    $client->on(EVENT_BUFFER_FULL, function (Client $client) {
        fprintf(STDOUT, "发送缓冲区满了\r\n");
    });


    $client->on(EVENT_CLOSE, function (Client $client) {
        fprintf(STDOUT, "服务已经关闭\r\n");
    });

    $client->start();

    $clients[] = $client;
}

while (1) {
    if (empty($clients)) {
        break;
    }

    foreach ($clients as $i => $client) {
        // $client->statistics();
        for ($j = 0; $j < 3; $j++) {
            if ($client->send("hello,i am client") === false) {
                unset($clients[$i]);
                break;
            }
        }


        if (!$client->eventLoop()) {
            unset($clients[$i]);
            break;
        }
    }

}