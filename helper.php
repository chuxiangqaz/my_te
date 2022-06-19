<?php

const RECORD_INFO = 'info';
const RECORD_ERR = 'error';
const RECORD_DEBUG = 'debug';

// 事件注册容器枚举
const EVENT_CONNECT = 'connect';
const EVENT_RECEIVE = 'receive';

function err($errMsg, $errCode = -1)
{
    throw new \Exception($errMsg, $errCode);
}


function record($level, $msg)
{
    sprintf(STDOUT, "[$level]$msg");
}
