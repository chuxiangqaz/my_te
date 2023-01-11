<?php

namespace Te\Protocols\WS;

class Frame
{
    /**
     * @var string
     */
    private $frame;
    /**
     * @var int
     */
    private $fin;
    /**
     * @var int
     */
    private $rsv1;
    /**
     * @var int
     */
    private $rsv2;
    /**
     * @var int
     */
    private $rsv3;
    /**
     * @var int
     */
    private $opcode;
    /**
     * @var int
     */
    private $mask;
    /**
     * @var int
     */
    private $payloadLen;

    /**
     * @var string
     */
    private $payload;

    /**
     * @var string
     */
    private $maskingKey;

    /**
     * @var int
     */
    private $readIndex = 0;

    /**
     * 消息是否完整
     *
     * @var bool
     */
    private $integrity = false;

    public function __construct($frame)
    {
        $this->frame = $frame;
        $this->resolve();
    }

    /**
     * 解析包
     */
    public function resolve()
    {
        $firstBin = ord($this->frame[0]);

        // 获取 fin 位
        $this->fin = ($firstBin & 0x80) === 0x80 ? 1 : 0;
        $this->rsv1 = ($firstBin & 0x40) === 0x40 ? 1 : 0;
        $this->rsv2 = ($firstBin & 0x20) === 0x20 ? 1 : 0;
        $this->rsv3 = ($firstBin & 0120) === 0x10 ? 1 : 0;
        $this->opcode = $firstBin & 0x0f;

        // 标识读取到的字节位索引
        ++$this->readIndex;
        $twoBin = ord($this->frame[1]);
        // 获取 umask 位
        $this->mask = ($twoBin & 0x80) === 0x80 ? 1 : 0;

        if ($this->mask === 0) {
            throw new \InvalidArgumentException("参数 umask 未设置为 1");
        }

        // 解析负载长度
        $payloadLen = $twoBin & 0x7f;
        if ($payloadLen > 0 && $payloadLen <= 125) {
            $this->payloadLen = $payloadLen;
        } else if ($payloadLen === 126) {
            if (strlen($this->frame) < 4) {
                return $this;
            }

            $threeBin = ord($this->frame[2]);
            $fourBin = ord($this->frame[3]);
            $this->payloadLen = $this->mreageBin($threeBin, $fourBin);
            $this->readIndex += 2;
        } else if ($payloadLen === 127) {
            if (strlen($this->frame) < 10) {
                return $this;
            }

            for ($i = 2; $i < 10; $i++) {
                $bins[] = ord($this->frame[$i]);
            }

            $this->payloadLen = $this->mreageBin(...$bins);
            $this->readIndex += 8;
        }

        if (strlen($this->frame) < $this->readIndex + 4 + $this->payloadLen + 1) {
            return $this;
        }


        // 解析maskingKey
        // 客户端使用熵值足够高的随机数生成器随机生成 32 比特的 Masking-Key
        $this->maskingKey = substr($this->frame, $this->readIndex + 1, 4);
        $this->readIndex += 4;

        // 解析负载数据
        $payload = substr($this->frame, $this->readIndex + 1, $this->payloadLen);
        $this->readIndex += $this->payloadLen;
        $data = '';
        for ($i = 0; $i < $this->payloadLen; $i++) {
            $j = $i % 4;
            $data .= chr(ord($this->maskingKey[$j]) ^ ord($payload[$i]));

        }
        $this->payload = $data;
        $this->integrity = true;
        return $this;
    }

    private function mreageBin(...$bins)
    {
        $data = 0;
        $maxIndex = count($bins) - 1;
        foreach ($bins as $i => $bin) {
            $move = ($maxIndex - $i) * 8;
            $data |= ($bin << $move);
        }

        return $data;
    }

    /**
     * @return string
     */
    public function getFrame(): string
    {
        return $this->frame;
    }

    /**
     * @return int
     */
    public function getFin(): int
    {
        return $this->fin;
    }

    /**
     * @return int
     */
    public function getRsv1(): int
    {
        return $this->rsv1;
    }

    /**
     * @return int
     */
    public function getRsv2(): int
    {
        return $this->rsv2;
    }

    /**
     * @return int
     */
    public function getRsv3(): int
    {
        return $this->rsv3;
    }

    /**
     * @return int
     */
    public function getOpcode(): int
    {
        return $this->opcode;
    }

    /**
     * @return int
     */
    public function getMask(): int
    {
        return $this->mask;
    }

    /**
     * @return int
     */
    public function getPayloadLen(): int
    {
        return $this->payloadLen;
    }

    /**
     * @return string
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * @return string
     */
    public function getMaskingKey(): string
    {
        return $this->maskingKey;
    }

    /**
     * @return int
     */
    public function msgLen(): int
    {
        return $this->readIndex + 1;
    }

    /**
     * 消息是否完整
     *
     * @return bool
     */
    public function integrity(): bool
    {
        return $this->integrity;
    }
}