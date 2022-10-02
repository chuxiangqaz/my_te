<?php

namespace Te\Event;

use \EventBase;

class Epoll implements Event
{

    public const EVENT_TYPE_MAP = [
        self::READ_EVENT => \Event::READ | \Event::PERSIST,
        self::WRITE_EVENT => \Event::WRITE | \Event::PERSIST,
    ];

    protected $events = [];

    /**
     * @var EventBase
     */
    private $eventBase;

    public function __construct()
    {
        $this->eventBase = new EventBase();

    }

    public function addEvent($fd, $eventType, callable $callback)
    {
        $event = new \Event($this->eventBase, $fd, self::EVENT_TYPE_MAP[$eventType], $callback);
        $event->add();
        $this->events[(int)$fd][$eventType] = $event;
    }


    public function delEvent($fd, $eventType)
    {
        if (!isset($this->events[$fd][$eventType])) {
            return;
        }

        $this->events[(int)$fd][$eventType]->del();
    }

    public function eventLoop()
    {
        $this->eventBase->loop();
    }
}