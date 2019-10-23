<?php

namespace App\Event\ImportManual;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Finder\Finder;

class ManualStart extends Event
{
    const NAME = 'importManual.start';

    /**
     * @var Finder
     */
    private $files;

    public function __construct(Finder $files)
    {
        $this->files = $files;
    }

    public function getFiles(): Finder
    {
        return $this->files;
    }
}
