<?php

namespace Te;

use Te\Protocols\Protocols;

class TcpConnection
{

    /**
     * 客户端地址信息
     *
     * @var string
     */
    private $address;

    /**
     * 连接 socket
     *
     * @var resource
     */
    private $fd;

    /**
     * @var Server
     */
    private $server;

    /**
     * 表示当前连接接受缓冲区的大小
     *
     * @var int
     */
    private $recvBufferSize = 1024 * 100;


    /**
     * 表示当前连接目前接收的字节数大小
     *
     * @var int
     */
    private $recvLen = 0;

    /**
     * 表示当前连接缓冲区已满次数
     *
     * @var int
     */
    private $recvBufferFull = 0;

    /**
     * 表示从缓冲区中拿到的数据
     *
     * @var string
     */
    private $bufferData = '';

    /**
     *  接受数据的边界
     *
     * @var int
     */
    private $readBufferSize = 1024;

    /**
     * @var Protocols
     */
    private $protocols;


    public function __construct($fd, $address, $server, Protocols $protocols)
    {
        $this->fd = $fd;
        $this->address = $address;
        $this->server = $server;
        $this->protocols = $protocols;
    }

    /**
     * 接受客户端数据
     */
    public function recv()
    {
        if ($this->recvLen < $this->recvBufferSize) {
            $data = fread($this->fd, $this->readBufferSize);
            if ($data === "" || $data === false) {
                if (feof($this->fd) || !is_resource($this->fd)) {
                    // 客户端关闭
                    $this->server->closeClient($this->fd);
                }
                return;

            }

            $this->recvLen += strlen($data);
            $this->bufferData .= $data;

        } else {
            $this->recvBufferFull++;
        }

        // 判断数据是否完整
        while ($this->protocols->integrity($this->bufferData)) {

            // 解码数据
            [$header, $cmd, $load] = $this->protocols->decode($this->bufferData);
            $this->bufferData = substr($this->bufferData, $header);
            $this->recvLen -=$header;

            // 接受客户端数据
            $this->server->runEvent(EVENT_RECEIVE, $this->server, $this, $header, $cmd, $load);
        }
    }

    /**
     * 给客户端发生数据
     *
     * @param string $data
     * @return void
     */
    public function write($data)
    {
        $package = $this->protocols->encode($data);
        $len = stream_socket_sendto($this->fd, $package, 0);
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