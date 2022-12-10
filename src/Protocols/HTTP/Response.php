<?php

namespace Te\Protocols\HTTP;

use Te\TcpConnection;

class Response
{

    const JSON = "application/json";

    const TEXT = "text/html";

    const MSG_LIST = [
        200 => "OK",
        201 => "Created",
    ];

    /**
     * @var TcpConnection
     */
    private $connection;

    /**
     * @var int
     */
    private $httpCode = 200;

    /**
     * @var array
     */
    private $header;

    /**
     * @var string
     */
    private $body;

    /**
     * @var string
     */
    private static $rootPath = '';


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
        $filePath = $this->staticFile($file);
        if (!is_file($filePath)) {
            $this->code(404);
            $filePath = $this->staticFile("404.html");
        } else {
            $filePath = realpath($filePath);
            if (!strAfter($filePath, self::$rootPath)) {
                $this->code(403);
                $filePath = $this->staticFile("403.html");
            }
        }

        $this->body = file_get_contents($filePath);
        $contentType = mime_content_type($filePath);
        $this->contentType($contentType);
        $this->send();
    }


    public function send()
    {
        $this->setDefaultHeader();
        $resContent = sprintf("HTTP/1.1 %d %s\r\n", $this->httpCode, self::MSG_LIST[$this->httpCode] ?? '');
        foreach ($this->header as $k => $v) {
            $resContent .= sprintf("%s: %s\r\n", $k, $v);
        }

        $resContent .= "\r\n";
        $resContent .= $this->body;
        $this->connection->send($resContent);
    }

    /**
     * 静态文件地址
     *
     * @param string $file
     * @return string
     */
    public function staticFile($file): string
    {
        return rtrim(self::$rootPath, '/') . '/' . $file;
    }

    /**
     * 设置默认的相应头
     *
     * @return void
     */
    private function setDefaultHeader()
    {
        $this->header['server'] = "Te";
        $this->header['date'] = date(DATE_RFC2822);
        $this->header['content-length'] = strlen($this->body);
    }

    /**
     * @return string
     */
    public static function getRootPath(): string
    {
        return self::$rootPath;
    }

    /**
     * @param string $rootPath
     */
    public static function setRootPath(string $rootPath): void
    {
        self::$rootPath = $rootPath;
    }
}