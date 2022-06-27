<?php

namespace Te\Protocols;

interface Protocols
{
    public function len($data);

    public function encode($data = '');

    public function decode($data = '');
}