<?php

namespace Te\Event;

interface Event
{

    const READ_EVENT = "read";

    const WRITE_EVENT = "write";

    /**
     * 添加事件
     *
     * @return mixed
     */
    public function addEvent($fd, $eventType, callable $callback): void;


    /**
     * 删除
     *
     * @return mixed
     */
    public function delEvent($fd, $eventType): void;

    /**
     * 添加信号事件
     *
     * @param $signal
     * @param callable $callback
     * @return void
     */
    public function addSignal($signal, callable $callback): void;

    /**
     * 删除信号事件
     *
     * @param $signal
     */
    public function delSignal($signal): void;

    /**
     * 添加定时事件
     *
     * @param string $timerName
     * @param int $timer
     * @param callable $callback
     */
    public function addTimer(string $timerName, int $timer, callable $callback): void;

    /**
     * 删除定时事件
     *
     * @param string $timerName
     * @param int $timer
     * @param callable $callback
     */
    public function delTimer(string $timerName): void;

    /**
     * 进行事件循环的转发
     */
    public function eventLoop(): void;

    /**
     * 停止事件循环转发
     *
     * @return mixed
     */
    public function stop() : void;

}