<?php

namespace Te;

class TcpConnection
{

    /**
     * 客户端地址信息
     *
     * @var string
     */
    private $address;

    /**
     * @var resource
     */
    private $fd;


    public function __construct($fd, $address)
    {
        $this->fd = $fd;
        $this->address = $address;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @return resource
     */
    public function getFd()
    {
        return $this->fd;
    }



}