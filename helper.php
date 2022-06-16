<?php

function err($errMsg, $errCode = -1)
{
    throw new \Exception($errMsg, $errCode);
}
