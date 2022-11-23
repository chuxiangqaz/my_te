<?php

namespace Te\Command;

class Invoker
{
    /**
     * @var string[]
     */
    public $command = [
        StartCommand::class,
        StopCommand::class,
    ];

    /**
     * @var Command
     */
    private $cmd = null;


    public function __construct($args)
    {
        foreach ($this->command as $cmdName) {
            $class = new $cmdName($args);
            if ($class->signature == $args[1]) {
               $this->cmd = $class;
               return;
            }
        }

        $this->cmd = new NoneCommand($args);

    }

    public function exec()
    {
        $this->cmd->exec();
    }


}