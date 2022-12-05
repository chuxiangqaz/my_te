<?php

namespace Te\Command;

class HelpCommand extends Command
{
    public $signature = 'help';

    public $desc = 'ls all command';

    public function exec()
    {
        $result = <<<EOF
Usage: php te.php [OPTION]
一个纯 PHP 网络引擎框架。


EOF;
        $invoker = new Invoker([]);
        foreach ($invoker->command as $cmdLine) {
            $cmd = new $cmdLine([]);
            if ($cmd->signature === '') {
                continue;
            }

            $result .= <<<EOF
 php te.php {$cmd->signature}\t\t{$cmd->desc}

EOF;
        }
        echo $result;
    }


}