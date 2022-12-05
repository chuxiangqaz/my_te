<?php

namespace Te\Command;

class NoneCommand extends Command
{

    public function exec()
    {
        $cmdline = $this->args[1] ?? "";
        echo <<<EOF
php {$this->fileName}: option requires an argument -- '{$cmdline}'
Try 'php {$this->fileName} --help' for more information.

EOF;

    }
}