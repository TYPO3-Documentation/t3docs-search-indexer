<?php

namespace App\Event\ImportManual;

use Symfony\Contracts\EventDispatcher\Event;

class ManualFinish extends Event
{
    public const NAME = 'importManual.finish';
}
