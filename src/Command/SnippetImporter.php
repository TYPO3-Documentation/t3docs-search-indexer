<?php

namespace App\Command;

use App\Dto\Manual;
use App\Event\ImportManual\ManualAdvance;
use App\Event\ImportManual\ManualFinish;
use App\Event\ImportManual\ManualStart;
use App\Service\DirectoryFinderService;
use App\Service\ImportManualHTMLService;
use LogicException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\EventDispatcher\Event;

#[AsCommand(name: 'docsearch:import', description: 'Imports documentation')]
class SnippetImporter extends Command
{
    public function __construct(
        private readonly string $defaultRootPath,
        private readonly ImportManualHTMLService $importer,
        private readonly DirectoryFinderService $directoryFinder,
        EventDispatcherInterface $dispatcher
    ) {
        $dispatcher->addListener(ManualStart::NAME, $this->startProgress(...));
        $dispatcher->addListener(ManualAdvance::NAME, $this->advanceProgress(...));
        $dispatcher->addListener(ManualFinish::NAME, $this->finishProgress(...));

        parent::__construct();
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->addOption('rootPath', null, InputOption::VALUE_REQUIRED, 'Root Path', $this->defaultRootPath);
        $this->addArgument('packagePath', InputArgument::OPTIONAL, 'Package Path');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws LogicException
     * @throws RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timer = new Stopwatch();
        $timer->start('importer');

        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Starting import');

        $this->io->section('Looking for manuals to import');
        $manualsFolders = $this->getManuals($input, $output);

        $processed = 0;
        if ($manualsFolders->hasResults()) {
            foreach ($manualsFolders as $folder) {
                $manual = Manual::createFromFolder($folder);
                $this->importManual($manual, $input);
                $subManuals = $manual->getSubManuals();
                if (!empty($subManuals)) {
                    foreach ($subManuals as $subManual) {
                        $this->importManual($subManual, $input);
                        $processed++;
                    }
                }
                $processed++;
            }
        }

        $totalTime = $timer->stop('importer');
        $this->io->title('importing ' . $processed . ' manuals took ' . $this->formatMilliseconds($totalTime->getDuration()));
        return Command::SUCCESS;
    }

    protected function importManual($manual, $input)
    {
        $this->io->section('Importing ' . $this->makePathRelative(
            $input->getOption('rootPath'),
            $manual->getAbsolutePath()
        ) . ' - sit tight.');
        $this->importer->deleteManual($manual);

        $this->importer->importManual($manual);
    }

    /**
     * @return Finder
     */
    private function getManuals(InputInterface $input, OutputInterface $output): Finder
    {
        $rootPath = rtrim((string)$input->getOption('rootPath'), '/');
        $packagePath = rtrim((string)$input->getArgument('packagePath'), '/');

        if (empty($packagePath)) {
            $folders = $this->directoryFinder->getAllManualDirectories($rootPath);
        } else {
            $folders = $this->directoryFinder->getDirectoriesByPath($rootPath, $packagePath);
        }

        if (!$folders->hasResults()) {
            $message = '<error>Root path should contain at last one of the allowed directories. Please, check configuration</error>';
            $output->writeln($message);
        }
        return $folders;
    }

    private function makePathRelative(string $base, string $path)
    {
        return str_replace(rtrim($base, '/') . '/', '', $path);
    }

    private function formatMilliseconds(int $milliseconds): string
    {
        $t = round($milliseconds / 1000);
        return sprintf('%02d:%02d:%02d', (int)($t / 3600), (int)($t / 60) % 60, $t % 60);
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
