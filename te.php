<?php

require "./vendor/autoload.php";

echo 'pid='. getmypid().PHP_EOL;

$server = new \Te\Server("tcp://127.0.0.1:12345");

$server->listen();

$server->eventLoop();

