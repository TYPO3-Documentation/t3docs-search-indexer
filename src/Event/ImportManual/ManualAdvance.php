<?php

namespace App\Event\ImportManual;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Finder\Finder;

class ManualAdvance extends Event
{
    public const NAME = 'importManual.advance';
}
