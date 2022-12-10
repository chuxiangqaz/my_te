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
const EVENT_TASK_RECEIVE = 'task_receive';
const EVENT_TASK_CLOSE = 'task_close';

function err($errMsg, $errCode = -1)
{
    record(RECORD_ERR, $errMsg);
    exit(1);
}

function record($level, $msg, ...$arg)
{
    $msg = sprintf($msg, ...$arg);
    $pid = getmypid();
    fprintf(STDOUT, "[$pid][$level]$msg" .PHP_EOL);
}

function strAfter($subject, $search)
{
    return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
}

function strRandom($length = 16): string
{
    $string = '';

    while (($len = strlen($string)) < $length) {
        $size = $length - $len;

        $bytes = random_bytes($size);

        $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
    }

    return $string;
}
