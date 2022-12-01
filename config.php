<?php

return [
    "address" => "tcp://127.0.0.1:12345",
    "protocols" => \Te\Protocols\Text::class,
    "event" => \Te\Event\Epoll::class,
    "work" => 2,
    "task" => [
        "num" => 2,
        "client_socket" => "/tmp/te_client_%d.socket",
        "server_socket" => "/tmp/te_server_%d.socket",
    ],
    "pid" => "/tmp/te.pid"
];
