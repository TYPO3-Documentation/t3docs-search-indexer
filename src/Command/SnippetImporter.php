<?php

namespace App\Command;

use App\Dto\Manual;
use App\Event\ImportManual\ManualAdvance;
use App\Event\ImportManual\ManualFinish;
use App\Event\ImportManual\ManualStart;
use App\Service\ImportManualHTMLService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class SnippetImporter extends ContainerAwareCommand
{
    /**
     * @var ImportManualHTMLService
     */
    private $importer;

    public function __construct(
        ImportManualHTMLService $importer,
        EventDispatcherInterface $dispatcher
    ) {
        parent::__construct(null);

        $this->importer = $importer;

        $dispatcher = $dispatcher;
        $dispatcher->addListener(ManualStart::NAME, [$this, 'startProgress']);
        $dispatcher->addListener(ManualAdvance::NAME, [$this, 'advanceProgress']);
        $dispatcher->addListener(ManualFinish::NAME, [$this, 'finishProgress']);
    }

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('docsearch:import');
        $this->setDescription('Imports all documentation');
        $this->addArgument('package', InputArgument::OPTIONAL, 'Package Name');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \LogicException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rootPath = $this->getContainer()->get('kernel')->getProjectDir() . '/_docs';
        $timer = new Stopwatch();
        $timer->start('importer');

        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Starting import');
//        if (is_numeric($input->getArgument('package'))) {
//
//        }
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
