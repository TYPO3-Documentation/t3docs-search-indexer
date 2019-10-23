<?php

namespace App\Event\ImportManual;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Finder\Finder;

class ManualFinish extends Event
{
    const NAME = 'importManual.finish';
}
