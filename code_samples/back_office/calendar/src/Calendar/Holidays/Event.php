<?php

namespace App\Calendar\Holidays;

use DateTimeInterface;
use Ibexa\Contracts\Calendar\Event as CalendarEvent;

class Event extends CalendarEvent
{
    public function __construct(string $id, DateTimeInterface $dateTime, EventType $type)
    {
        parent::__construct($type, $id, $dateTime);
    }
}
