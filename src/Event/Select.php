<?php

namespace Te\Event;

class Select implements Event
{

    private $events;

    private $exit = false;

    public function addEvent($fd, $eventType, callable $callback): void
    {
        $this->events["io"][$eventType][(int)$fd] = ['fd' => $fd, 'callback' => $callback];
    }

    public function delEvent($fd, $eventType): void
    {
        unset($this->events["io"][$eventType][(int)$fd]);
    }

    public function addSignal($signal, callable $callback): void
    {
        pcntl_signal($signal, $callback);
        $this->events["signal"][$signal] = $callback;
    }

    public function delSignal($signal): void
    {
        pcntl_signal($signal, SIG_DFL);
        unset($this->events["signal"][$signal]);
    }

    public function addTimer(string $timerName, int $timer, callable $callback): void
    {
        $this->events["timer"][$timerName] = ['time' => $timer, 'callback' => $callback, 'next' => time() + $timer];
    }

    public function delTimer(string $timerName): void
    {
        unset($this->events["signal"][$timerName]);
    }

    public function eventLoop(): void
    {
        while (1) {
            if ($this->exit) {
                break;
            }

            if (!empty($this->events["signal"])) {
                pcntl_signal_dispatch();
            }

            if (!empty($this->events["timer"])) {
                foreach ($this->events["timer"] as &$runInfo) {
                    if ($runInfo['next'] <= time()) {
                        $runInfo['callback']();
                        $runInfo['next'] = time() + $runInfo['time'];
                    }
                }
            }

            if (!empty($this->events["io"])) {
                $read = [];
                $write = [];
                $exp = [];
                foreach ($this->events["io"] as $eventType => $data) {
                    $listenFd = [];
                    foreach ($data as $fdInfo) {
                        $listenFd[] = $fdInfo['fd'];
                    }

                    if ($eventType === self::READ_EVENT) {
                        $read = $listenFd;
                    }

                    if ($eventType === self::WRITE_EVENT) {
                        $write = $listenFd;
                    }
                }

                $numChange = stream_select($read, $write, $exp, 0, 0);

                if ($numChange == 0) {
                    continue;
                }


                if ($read) {
                    foreach ($read as $fd) {
                        $this->events["io"][self::READ_EVENT][(int)$fd]['callback']();
                    }
                }

                if ($write) {
                    foreach ($write as $fd) {
                        $this->events["io"][self::WRITE_EVENT][(int)$fd]['callback']();
                    }
                }
            }
        }
    }

    public function stop(): void
    {
       foreach ($this->events as $type => &$events) {
           $events = [];
           record(RECORD_INFO, "删除了事件监听,type=%s", $type);
       }

       $this->exit = true;
        record(RECORD_INFO, "停止了事件循环");
    }
}