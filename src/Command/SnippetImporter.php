<?php

namespace App\Command;

use App\Dto\Manual;
use App\Event\ImportManual\ManualAdvance;
use App\Event\ImportManual\ManualFinish;
use App\Event\ImportManual\ManualStart;
use App\Service\DirectoryFinderService;
use App\Service\ImportManualHTMLService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\EventDispatcher\Event;

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

    private $kernel;

    private $finder;

    private $directoryFinder;

    public function __construct(
        string $defaultRootPath,
        ImportManualHTMLService $importer,
        EventDispatcherInterface $dispatcher,
        KernelInterface $kernel,
        DirectoryFinderService $directoryFinder
    ) {
        $this->defaultRootPath = $defaultRootPath;
        $this->importer = $importer;
        $this->kernel = $kernel;
        $this->finder = new Finder();
        $this->directoryFinder = $directoryFinder;

        $dispatcher->addListener(ManualStart::NAME, [$this, 'startProgress']);
        $dispatcher->addListener(ManualAdvance::NAME, [$this, 'advanceProgress']);
        $dispatcher->addListener(ManualFinish::NAME, [$this, 'finishProgress']);

        parent::__construct();
    }

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('docsearch:import');
        $this->setDescription('Imports documentation');
        $this->addOption('rootPath', null, InputOption::VALUE_REQUIRED, 'Root Path', $this->defaultRootPath);
        $this->addArgument('packagePath', InputArgument::OPTIONAL, 'Package Path');
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
        $timer = new Stopwatch();
        $timer->start('importer');

        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Starting import');

        $this->io->section('Looking for manuals to import');
        $manualsToImport = $this->getManuals($input, $output);
        $this->io->writeln('Found ' . count($manualsToImport) . ' manuals.');

        foreach ($manualsToImport as $manual) {
            /* @var Manual $manual */
            $this->io->section('Importing ' . $this->makePathRelative($input->getOption('rootPath'), $manual->getAbsolutePath())  . ' - sit tight.');
            $this->importer->deleteManual($manual);
            $this->importer->importManual($manual);
        }

        $totalTime = $timer->stop('importer');
        $this->io->title('importing took ' . $this->formatMilliseconds($totalTime->getDuration()));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array|int
     */
    private function getManuals(InputInterface $input, OutputInterface $output)
    {
        $rootPath = $input->getOption('rootPath');
        $directoryFinder = $this->directoryFinder->findAllowedDirectoriesForDocSearch($rootPath);
        $manuals = [];

        if (empty($directoryFinder['manualsPath'])) {
            $output->writeln($directoryFinder['message']);

            return 0;
        }

        $output->writeln($directoryFinder['message']);

        foreach ($directoryFinder['manualsPath'] as $manualPath) {
            $manuals = array_merge($manuals, $this->importer->findManuals($manualPath));
        }

        return $manuals;
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
