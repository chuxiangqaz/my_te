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

    /**
     * @var Server
     */
    private $server;

    /**
     * @var int
     */
    private $readBufferSize = 1024;


    public function __construct($fd, $address, $server)
    {
        $this->fd = $fd;
        $this->address = $address;
        $this->server = $server;
    }

    /**
     * 接受客户端数据
     */
    public function recv()
    {
        $data = fread($this->fd, $this->readBufferSize);
        if ($data == "" ||  $data === false) {
            if (feof($this->fd) || !is_resource($data)) {
                // 客户端关闭
                $this->server->closeClient($this->fd);
            }
            return;

        }

        // 接受客户端数据
        $this->server->runEvent(EVENT_RECEIVE, $this->server, $this, $data);
    }

    /**
     * 给客户端发生数据
     *
     * @param string $data
     * @return void
     */
    public function write($data)
    {
        $len = stream_socket_sendto($this->fd, $data, 0);
        fprintf(STDOUT, "send msg len=%d\n", $len);
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