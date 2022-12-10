<?php

namespace Te\Protocols\HTTP;


class Request
{
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
     * @var array
     */
    private $header;

    /**
     * 是否是完整数据
     *
     * @var bool
     */
    private $integrity;

    /**
     * 消息长度
     *
     * @var int
     */
    private $msgLen;

    /**
     * 头部长度
     *
     * @var int
     */
    private $headerLen;

    /**
     * 请求正文
     * @var string
     */
    private $requestBodyLine;

    /**
     * 请求正文的参数解析
     *
     * @var array
     */
    private $requestBody = [];


    /**
     * @param string $package
     */
    public function __construct(string $package)
    {
        $this->package = $package;
    }

    public function resolve()
    {
        $this->integrity = true;
        // 查找到请求头的位置
        $headLen = stripos($this->package, "\r\n\r\n", 0);
        $this->headerLen = $headLen;
        if ($headLen === false) {
            $this->integrity = false;
            return $this;
        }

        // 解析请求行
        $requestLineLen = stripos($this->package, "\r\n", 0);
        $requestLine = substr($this->package, 0, $requestLineLen);
        $requestLineArr = explode(" ", $requestLine);
        $this->method = $requestLineArr[0];
        $this->url = $requestLineArr[1];
        $this->version = $requestLineArr[2];

        // 解析请求头
        $requestHeader = substr($this->package, $requestLineLen + 2, $headLen - $requestLineLen - 2);
        $requestHeaderArr = explode("\r\n", $requestHeader);
        foreach ($requestHeaderArr as $lineHeaderText) {
            if (empty($lineHeaderText)) {
                continue;
            }

            $headerKV = explode(":", $lineHeaderText);
            $this->header[strtolower($headerKV[0])] = trim($headerKV[1] ?? '');
        }

        // 解析消息长度
        $this->msgLen = $headLen + 4;
        if (isset($this->header['content-length'])) {
            $this->msgLen += $this->header['content-length'];
        }

        // 判断消息是否完整
        $this->integrity = strlen($this->package) >= $this->msgLen;

        if ($this->integrity) {
            $this->resolveRequestBody();
        }

        return $this;
    }

    public function isIntegrity(): bool
    {
        return $this->integrity;
    }

    private function resolveRequestBody()
    {
        if (!$this->integrity) {
            return;
        }

        if (!isset($this->header['content-length']) || $this->header['content-length'] == 0) {
            return;
        }

        $this->requestBodyLine = substr($this->package, $this->headerLen + 4, $this->header['content-length']);
        switch (true) {
            case !isset($this->header['content-type']):
                break;
            case $this->header['content-type'] === 'application/json':
                $this->requestBody = json_decode($this->requestBodyLine, true);
                break;
            case $this->header['content-type'] === 'application/x-www-form-urlencoded';
                parse_str($this->requestBodyLine, $this->requestBody);
            case str_starts_with($this->header['content-type'], 'multipart/form-data'):
                $boundary = '--' . strAfter($this->header['content-type'], "boundary=");
                $formArr = explode($boundary, $this->requestBodyLine);
                unset($formArr[count($formArr) - 1], $formArr[0]);
                foreach ($formArr as $i => $formItem) {
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
    public function getPackage(): string
    {
        return $this->package;
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
    public function getUrl(): string
    {
        return $this->url;
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
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * @return int
     */
    public function getMsgLen(): int
    {
        return $this->msgLen;
    }

    /**
     * @return int
     */
    public function getHeaderLen(): int
    {
        return $this->headerLen;
    }

    /**
     * @return array
     */
    public function getRequestBody(): array
    {
        return $this->requestBody;
    }


}