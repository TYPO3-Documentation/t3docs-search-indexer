<?php

namespace App\Command;

use App\Dto\Manual;
use App\Event\ImportManual\ManualAdvance;
use App\Event\ImportManual\ManualFinish;
use App\Event\ImportManual\ManualStart;
use App\Service\ImportManualHTMLService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class SnippetImporter extends Command
{
    /**
     * @var string
     */
    private $defaultRootPath;

    /**
     * @var ImportManualHTMLService
     */
    private $importer;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(
        string $defaultRootPath,
        ImportManualHTMLService $importer,
        EventDispatcherInterface $dispatcher
    ) {
        $this->defaultRootPath = $defaultRootPath;
        $this->importer = $importer;

        $dispatcher = $dispatcher;
        $dispatcher->addListener(ManualStart::NAME, [$this, 'startProgress']);
        $dispatcher->addListener(ManualAdvance::NAME, [$this, 'advanceProgress']);
        $dispatcher->addListener(ManualFinish::NAME, [$this, 'finishProgress']);

        parent::__construct(null);
    }

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('docsearch:import');
        $this->setDescription('Imports documentation');
        $this->addOption('rootPath', null, InputOption::VALUE_REQUIRED, 'Root Path', $this->defaultRootPath);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \LogicException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rootPath = $input->getOption('rootPath');
        $timer = new Stopwatch();
        $timer->start('importer');

        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Starting import');

        $this->io->section('Looking for manuals to import');
        $manualsToImport = $this->importer->findManuals($rootPath);
        $this->io->writeln('Found ' . count($manualsToImport) . ' manuals.');

        foreach ($manualsToImport as $manual) {
            /* @var Manual $manual */
            $this->io->section('Importing ' . $this->makePathRelative($rootPath, $manual->getAbsolutePath())  . ' - sit tight.');
            $this->importer->importManual($manual);
        }

        $totalTime = $timer->stop('importer');
        $this->io->title('importing took ' . $this->formatMilliseconds($totalTime->getDuration()));
    }

    private function makePathRelative(string $base, string $path)
    {
        return str_replace(rtrim($base, '/') . '/', '', $path);
    }

    private function formatMilliseconds(int $milliseconds): string
    {
        $t = round($milliseconds / 1000);
        return sprintf('%02d:%02d:%02d', ($t / 3600), ($t / 60 % 60), $t % 60);
    }

    public function startProgress(Event $event)
    {
        $this->io->progressStart($event->getFiles()->count());
    }

    public function advanceProgress(Event $event)
    {
         $this->io->progressAdvance();
    }

    public function finishProgress(Event $event)
    {
        $this->io->progressFinish();
    }
}
