<?php

require "./vendor/autoload.php";

const ROOT_PATH = __DIR__;

$cmd = new \Te\Command\Invoker($argv);

$cmd->exec();

