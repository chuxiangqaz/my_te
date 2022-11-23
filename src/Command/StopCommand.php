<?php

namespace Te\Command;

class StopCommand extends Command
{

    public $signature = 'stop';

    public function exec()
    {
        if (!$this->masterExist()) {
            err("process is not run");
        }

        $pid = file_get_contents($this->config["pid"]);
        posix_kill($pid, SIGTERM);
    }
}