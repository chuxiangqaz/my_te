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
    public function addEvent($fd, $eventType, callable $callback);


    /**
     * 删除
     *
     * @return mixed
     */
    public function delEvent($fd, $eventType);


    public  function eventLoop();

}