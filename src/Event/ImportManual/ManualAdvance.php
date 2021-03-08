<?php

namespace App\Event\ImportManual;

use Symfony\Contracts\EventDispatcher\Event;

class ManualAdvance extends Event
{
    public const NAME = 'importManual.advance';
}
