<?php

namespace App\Event\ImportManual;

use Symfony\Component\Finder\Finder;
use Symfony\Contracts\EventDispatcher\Event;

class ManualStart extends Event
{
    public const NAME = 'importManual.start';

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
