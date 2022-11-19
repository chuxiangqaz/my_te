<?php

namespace Te\Event;

use Event as EpollEvent;
use EventBase;

class Epoll implements Event
{

    public const EVENT_TYPE_MAP = [
        self::READ_EVENT => EpollEvent::READ | EpollEvent::PERSIST,
        self::WRITE_EVENT => EpollEvent::WRITE | EpollEvent::PERSIST,
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

    public function addEvent($fd, $eventType, callable $callback): void
    {
        $event = new EpollEvent($this->eventBase, $fd, self::EVENT_TYPE_MAP[$eventType], $callback);
        $event->add();
        $this->events["io"][(int)$fd][$eventType] = $event;
    }


    public function delEvent($fd, $eventType): void
    {
        if (!isset($this->events["io"][(int)$fd][$eventType])) {
            return;
        }

        $this->events["io"][(int)$fd][$eventType]->del();
        unset($this->events["io"][(int)$fd][$eventType]);
    }

    public function eventLoop(): void
    {
        $this->eventBase->loop();
    }

    public function addSignal($signal, callable $callback): void
    {
        $event = new EpollEvent($this->eventBase, $signal, EpollEvent::SIGNAL | EpollEvent::PERSIST, $callback);
        $event->add();
        $this->events["signal"][$signal] = $event;
    }

    public function delSignal($signal): void
    {
        if (!isset($this->events["signal"][$signal])) {
            return;
        }

        $this->events["signal"][$signal]->del();
        unset($this->events["signal"][$signal]);
    }

    public function addTimer(string $timerName, int $timer, callable $callback): void
    {
        $event = new EpollEvent($this->eventBase, -1, EpollEvent::TIMEOUT | EpollEvent::PERSIST, $callback);
        $event->add($timer);
        $this->events["timer"][$timerName] = $event;
    }

    public function delTimer(string $timerName): void
    {
        if (!isset($this->events["timer"][$timerName])) {
            return;
        }

        $this->events["timer"][$timerName]->del();
        unset($this->events["timer"][$timerName]);
    }

}