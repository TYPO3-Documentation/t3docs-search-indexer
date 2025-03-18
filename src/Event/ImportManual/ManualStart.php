<?php

namespace App\Event\ImportManual;

use Symfony\Component\Finder\Finder;
use Symfony\Contracts\EventDispatcher\Event;

class ManualStart extends Event
{
    final public const NAME = 'importManual.start';

    public function __construct(private readonly Finder $files) {}

    public function getFiles(): Finder
    {
        return $this->files;
    }
}
