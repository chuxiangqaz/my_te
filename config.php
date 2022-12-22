<?php

return [
    "address" => "tcp://0.0.0.0:6379",
    "protocols" => \Te\Protocols\WS::class,
    "event" => \Te\Event\Epoll::class,
    "daemon" => false,
    "log" => 'php://stdout',
    "work" => 2,
    "task" => [
        "num" => 2,
        "client_socket" => "/tmp/te_client_%d.socket",
        "server_socket" => "/tmp/te_server_%d.socket",
    ],
    "pid" => "/tmp/te.pid",
    "http" => [
        "tmp_path" => "./doc/img/",
        "static" => [
            "root" => "./web/www"
        ]
    ]
];
