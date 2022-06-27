<?php

namespace Te\Protocols;

interface Protocols
{
    /**
     * 判断数据是否完整
     *
     * @param $data
     * @return bool
     */
    public function integrity($data): bool;

    /**
     * 编码数据
     *
     * @param string $data
     * @return mixed
     */
    public function encode($data = '');

    /**
     * 解码数据
     *
     * @param string $data
     * @return array
     */
    public function decode($data = ''): array;
}