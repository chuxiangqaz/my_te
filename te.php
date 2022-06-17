<?php

require "./vendor/autoload.php";

$server = new \Te\Server("tcp://127.0.0.1:12345");

$server->listen();

$server->accept();
