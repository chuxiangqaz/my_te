<?php

namespace Te\Protocols\HTTP;


class Request
{
    use Header;

    /**
     * 报文内容
     *
     * @var string
     */
    private $package;

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $version;


    /**
     * 请求正文的参数解析
     *
     * @var array
     */
    private $requestBody = [];

    /**
     * 请求头参数
     *
     * @var array
     */
    private $query = [];

    /**
     * @var string
     */
    private $path;


    /**
     * @param string $package
     */
    public function __construct(string $package)
    {
        $this->package = $package;
    }

    /**
     * 检测消息是否完成
     */
    public function checkIntegrity(): bool
    {
        $headLen = $this->headerLenByPackage();
        if ($headLen === 0) {
            return false;
        }

        $bodyLen = $this->bodyLenByPackage();

        return strlen($this->package) >= $bodyLen + $headLen;
    }

    /**
     * 解析请求头长度
     *
     * @return int
     */
    private function headerLenByPackage(): int
    {
        $headLen = stripos($this->package, "\r\n\r\n", 0);
        if ($headLen === false) {
            return 0;
        }
        return $headLen + 4;
    }

    /**
     * 解析请求正文长度
     *
     * @return int
     */
    private function bodyLenByPackage(): int
    {
        $bodyLen = 0;
        preg_match('/Content-Length: (.*?)\r\n/', $this->package, $contentLen);
        if (isset($contentLen[1])) {
            $bodyLen = $contentLen[1];
        }

        return (int)$bodyLen;
    }

    /**
     * 消息长度
     *
     * @return int
     */
    public function msgLen(): int
    {
        return $this->headerLenByPackage() + $this->bodyLenByPackage();
    }

    /**
     * 解析请求行和请求头和请求参数
     *
     * @return $this
     */
    public function resolve()
    {
        $headLen = $this->headerLenByPackage();

        // 解析请求行
        $requestLineLen = stripos($this->package, "\r\n", 0);
        $requestLine = substr($this->package, 0, $requestLineLen);
        $requestLineArr = explode(" ", $requestLine);
        $this->method = $requestLineArr[0];
        $this->resolveQueyry($requestLineArr[1]);
        $this->version = $requestLineArr[2];

        // 解析请求头
        $requestHeader = substr($this->package, $requestLineLen + 2, $headLen - $requestLineLen - 2);
        $requestHeaderArr = explode("\r\n", $requestHeader);
        foreach ($requestHeaderArr as $lineHeaderText) {
            if (empty($lineHeaderText)) {
                continue;
            }

            $headerKV = explode(": ", $lineHeaderText);
            $this->setHeader($headerKV[0], $headerKV[1]);
        }


        if ($this->hasHeader('Content-Length') && $this->getHeader('Content-Length') > 0) {
            $requestBodyLine = substr($this->package, $headLen, $this->getHeader('Content-Length'));
            $this->resolveRequestBody($requestBodyLine);
        }

        return $this;
    }

    private function resolveQueyry($url)
    {
        $pathInfo = parse_url($url);
        $this->path = urldecode($pathInfo['path']);
        isset($pathInfo['query']) && parse_str($pathInfo['query'], $this->query);
    }

    /**
     * 解析请求正文
     *
     * @param string $requestBodyLine
     */
    private function resolveRequestBody(string $requestBodyLine)
    {

        $contentType = $this->getHeader('Content-Type');
        switch (true) {
            case $contentType === null:
                break;
            case $contentType === 'application/json':
                $this->requestBody = json_decode($requestBodyLine, true);
                break;
            case $contentType === 'application/x-www-form-urlencoded';
                parse_str($requestBodyLine, $this->requestBody);
            case str_starts_with($contentType, 'multipart/form-data'):
                $boundary = '--' . strAfter($contentType, "boundary=");
                $formArr = explode($boundary, $requestBodyLine);
                unset($formArr[count($formArr) - 1], $formArr[0]);
                foreach ($formArr as $formItem) {
                    $itemInfo = explode("\r\n\r\n", $formItem);
                    preg_match('/name="(.*?)"/', $itemInfo[0], $matches);
                    $key = $matches[1];
                    $value = trim($itemInfo[1], "\r\n");

                    // 判断是否是文件
                    preg_match('/filename="(.*?)"/', $itemInfo[0], $fileMatches);
                    if (empty($fileMatches)) {
                        // 文本传输
                        $this->requestBody[$key] = $value;
                    } else {
                        $conTentType = ltrim(strstr($itemInfo[0], "Content-Type: "), "Content-Type: ");
                        // 组装文件
                        $file = new File($conTentType, $fileMatches[1], $value);
                        $this->requestBody[$key] = $file;
                    }
                }
        }
    }


    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }


    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return array
     */
    public function getRequestBody(): array
    {
        return $this->requestBody;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

}