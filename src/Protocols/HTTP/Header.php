<?php

namespace Te\Protocols\HTTP;

trait Header
{
    /**
     * @var array
     */
    private $header = [];

    public function setHeader($key, $value): void
    {
        $this->header[strTitle($key)] = $value;
    }

    public function getHeader($key, $default = null): string
    {
        return $this->header[strTitle($key)] ?? $default;
    }

    public function hasHeader($key): bool
    {
        return isset($this->header[strTitle($key)]);
    }

    public function setHeaders($headers = []): void
    {
        foreach ($headers as $k => $v) {
            $this->setHeader($k, $v);
        }
    }

    public function headers(): array
    {
        return $this->header;
    }
}