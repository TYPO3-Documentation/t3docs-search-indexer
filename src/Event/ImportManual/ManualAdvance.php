<?php

namespace App\Event\ImportManual;

use Symfony\Contracts\EventDispatcher\Event;

class ManualAdvance extends Event
{
    final public const NAME = 'importManual.advance';
}
