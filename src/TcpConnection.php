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
     * 接受客户端数据
     */
    public function recv()
    {
        $data = fread($this->fd, 1024);
        fprintf(STDOUT, "recvmsg: [%s]%s", $this->address, $data);
        // 所以业务处理这块必须是多线程
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