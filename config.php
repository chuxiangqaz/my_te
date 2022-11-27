<?php

return [
    "address" => "tcp://127.0.0.1:12345",
    "protocols" => \Te\Protocols\Text::class,
    "event" => \Te\Event\Epoll::class,
    "work" => 2,
    "task" => 1,
    "pid" => "/tmp/te.pid"
];
