<?php

require "./vendor/autoload.php";

$cmd = new \Te\Command\Invoker($argv);

$cmd->exec();

