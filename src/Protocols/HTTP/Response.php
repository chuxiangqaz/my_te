<?php

namespace Te\Protocols\HTTP;

use Te\TcpConnection;

class Response
{

    /**
     * @var TcpConnection
     */
    private $connection;

    public function __construct(TcpConnection $connection)
    {
        $this->connection = $connection;
    }

    public function send()
    {
        $ctx = "HTTP/1.1 200 OK\r\nContent-Length:5\r\n\r\nhello";
        $this->connection->send($ctx);
    }
}