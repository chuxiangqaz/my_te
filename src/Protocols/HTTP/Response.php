<?php

namespace Te\Protocols\HTTP;

use Te\TcpConnection;

class Response
{

    const JSON = "application/json";

    const TEXT = "text/html";


    /**
     * @var TcpConnection
     */
    private $connection;

    /**
     * @var int
     */
    private $httpCode;

    /**
     * @var array
     */
    private $header;

    /**
     * @var string
     */
    private $body;

    public function __construct(TcpConnection $connection)
    {
        $this->connection = $connection;
    }

    public function code(int $code): Response
    {
        $this->httpCode = $code;
        return $this;
    }

    public function contentType($contentType): Response
    {
        return $this->setHeaers('content-type', $contentType);
    }

    public function heaers($heaers = []): Response
    {
        array_merge($this->header, $heaers);
        return $this;
    }

    public function setHeaers($k, $v): Response
    {
        $this->header[$k] = $v;
        return $this;
    }

    /**
     * @param array $body
     */
    public function sendJson($body)
    {
        $this->contentType(self::JSON);
        $this->body = json_encode($body);
        $this->send();
    }


    /**
     * @param string $body
     */
    public function sendText($body)
    {
        $this->contentType(self::TEXT);
        $this->body = $body;
        $this->send();
    }

    /**
     * @param string $body
     */
    public function sendFile($file)
    {
        $this->contentType(self::TEXT);
        $this->body = file_get_contents($file);
        mime_content_type($file);
        $this->send();
    }


    public function send()
    {
        $ctx = "HTTP/1.1 200 OK\r\nContent-Length:5\r\n\r\nhello";
        $this->connection->send($ctx);
    }
}