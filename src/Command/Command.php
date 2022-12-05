<?php

namespace Te\Command;

abstract class Command
{
    // 命令模式
    public $signature = '';

    // 描述
    public $desc = '';

    protected $args;

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var array
     */
    protected $config;

    public function __construct($args)
    {
        $this->args = $args;
        $this->fileName = $this->args[0] ?? '';
        $this->config = require "config.php";
    }

    protected function masterExist(): bool
    {
        if (is_file($this->config["pid"]) && !empty($pid = file_get_contents($this->config["pid"]))) {
            if (posix_kill($pid, 0)) {
                return true;
            }
        }

        return false;
    }

    abstract public function exec();
}