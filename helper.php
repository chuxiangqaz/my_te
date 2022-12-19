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

const EVENT_HTTP_REQUEST = 'http_request';

function err($errMsg, $errCode = -1)
{
    record(RECORD_ERR, $errMsg);
    exit(1);
}

function record($level, $msg, ...$arg)
{
    $msg = sprintf($msg, ...$arg);
    $pid = getmypid();

    static $config;
    if ($config === null) {
        $log = fopen(config()['log'], "a+");
    }
    fprintf($log, "[$pid][$level]$msg" . PHP_EOL);
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

function absPath($path)
{
    if ($path === '') {
        return '';
    }

    if ($path[0] === '/') {
        return $path;
    }

    return realpath(ROOT_PATH . '/' . $path);
}

function strTitle($value)
{
    return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
}

/**
 * 返回配置
 *
 * @return array
 */
function config(): array
{
    static $config;
    if ($config === null) {
        $config = require "./config.php";
    }

    return $config;
}
