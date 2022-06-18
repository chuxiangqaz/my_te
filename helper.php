<?php

const RECORD_INFO = 'info';
const RECORD_ERR = 'error';
const RECORD_DEBUG = 'debug';

function err($errMsg, $errCode = -1)
{
    throw new \Exception($errMsg, $errCode);
}


function record($level, $msg)
{
    sprintf(STDOUT, "[$level]$msg");
}
