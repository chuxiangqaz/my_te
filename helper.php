<?php

const RECORD_INFO = 'info';
const RECORD_ERR = 'error';
const RECORD_DEBUG = 'debug';

// 事件注册容器枚举
const EVENT_CONNECT = 'connect';
const EVENT_RECEIVE = 'receive';
const EVENT_CLOSE = 'close';
const EVENT_READ_BUFFER_FULL = 'read_buffer_full';
const EVENT_WRITE_BUFFER_FULL = 'write_buffer_full';

function err($errMsg, $errCode = -1)
{
    throw new \Exception($errMsg, $errCode);
}


function record($level, $msg, ...$arg)
{
    $msg = sprintf($msg, ...$arg);
    $pid = getmypid();
    fprintf(STDOUT, "[$pid][$level]$msg" .PHP_EOL);
}
