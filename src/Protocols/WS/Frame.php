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

        $twoBin = ord($this->frame[1]);
        // 获取 umask 位
        $this->mask = ($twoBin & 0x80) === 0x80 ? 1 : 0;

        if ($this->mask === 0) {
            throw new \InvalidArgumentException("参数 umask 未设置为 1");
        }

        // 计算 payloadLen  长度
        $payloadLen = $twoBin & 0x7f;

        if ($payloadLen > 0 && $payloadLen <= 125) {
            $this->payloadLen = $payloadLen;
        } else if ($payloadLen === 126) {
            //
            $threeBin = ord($this->frame[2]);
            $fourBin = ord($this->frame[3]);
            $this->payloadLen = $this->mreageBin($threeBin, $fourBin);
        } else if ($payloadLen === 127) {
            for ($i = 2; $i < 10; $i++) {
                $bins[] = ord($this->frame[$i]);
            }

            $this->payloadLen = $this->mreageBin(...$bins);
        }



        dd($this);
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
}