<?php

namespace Te;

class Server
{
    /**
     * @var resource
     */
    private $mainSocket;

    /**
     * @var string
     */
    private $address;

    /**
     * @throws \Exception
     */
    public function __construct($address)
    {
        $this->address = $address;
    }

    public function listen()
    {
        $protocol = substr( $this->address, 0, 3);
        $flag = null;
        switch (strtolower($protocol)) {
            case 'tcp':
                $flag = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
                break;
            case 'udp':
                $flag = STREAM_SERVER_BIND;
                break;
            default:
                err("not support $protocol server");
        }

        // socket, bind, listen
        $socket = stream_socket_server( $this->address, $errCode, $errMsg, $flag);
        if (!is_resource($socket)) {
            err("create server err" . $errMsg, $errCode);
        }

        $this->mainSocket = $socket;
    }


    public function accept()
    {
        $fd = stream_socket_accept($this->mainSocket);
        if (is_resource($fd)) {
            echo "客户端连接:". $fd;
        }
    }
}