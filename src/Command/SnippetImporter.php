<?php
namespace App\Command;

use App\Repository\ElasticRepository;
use App\Service\ParseDocumentationHTMLService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Stopwatch\Stopwatch;

class SnippetImporter extends ContainerAwareCommand
{
    private $rootPath = '';

    /**
     * @var ElasticRepository
     */
    private $elasticRepository;

    /**
     *
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure():void
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
        $this->rootPath = $this->getContainer()->get('kernel')->getProjectDir() .'/_docs';
        $this->elasticRepository = new ElasticRepository();
        $timer = new Stopwatch();
        $timer->start('importer');

        $io = new SymfonyStyle($input, $output);
        $io->title('Starting import');
//        if (is_numeric($input->getArgument('package'))) {
//
//        }
        $io->section('Looking for books to import');
        $booksToImport = $this->findDocumentationFolders();
        $io->writeln('Found ' . $booksToImport->count()  . ' books.');
        foreach ($booksToImport as $bookFolder) {
            $io->section('Importing ' . $bookFolder->getRelativePathname()  . ' - sit tight.');
            $metaData = $this->getBookMetaData($bookFolder->getRelativePathname());
            $this->elasticRepository->deleteByBookAnVersion($metaData['bookType'], $metaData['bookName'], $metaData['bookVersion']);
            $parser = new ParseDocumentationHTMLService();
            $parser->setBookType($metaData['bookType']);
            $parser->setBookName($metaData['bookName']);
            $parser->setBookVersion($metaData['bookVersion']);
            $parser->setBookSlug($bookFolder->getRelativePathname());
            $this->parseFolder($this->rootPath . '/' . $bookFolder->getRelativePathname() . '', $io, $parser);
        }
        $totalTime = $timer->stop('importer');
        $io->title('importing took '. $this->formatMilliseconds($totalTime->getDuration()));
    }

    /**
     * @param string $folder
     * @param SymfonyStyle $io
     * @param ParseDocumentationHTMLService $parser
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function parseFolder(string $folder, SymfonyStyle $io, ParseDocumentationHTMLService $parser)
    {
        $filesToProcess = $this->findAllHTMLFiles($folder);
        $io->progressStart($filesToProcess->count());
        foreach ($filesToProcess as $fileToProcess) {
            $sectionsInFile = $parser->parseContent($fileToProcess->getContents(), $fileToProcess->getRelativePathname());
            /** @var array $sectionsInFile */
            foreach ($sectionsInFile as $item) {
                $this->elasticRepository->addOrUpdateDocument($item);
            }
            /** @noinspection DisconnectedForeachInstructionInspection */
            $io->progressAdvance();
        }
        $io->progressFinish();
    }

    /**
     * @param string $folder
     * @return Finder
     * @throws \InvalidArgumentException
     */
    private function findAllHTMLFiles(string $folder): Finder
    {
        $finder = new Finder();
        $finder
            ->files()
            ->in($folder)
            ->name('*.html')
            ->notName('search.html')
            ->notName('genindex.html')
            ->notPath('_static')
            ->notPath('singlehtml');
        return $finder;

    }

    /**
     * @return Finder
     * @throws \InvalidArgumentException
     */
    private function findDocumentationFolders(): Finder
    {
        $finder = new Finder();
        $finder->directories()->in($this->rootPath)->depth('== 4');
        return $finder;
    }

    private function getBookMetaData(string $folderName): array
    {
        list($bookType, $vendor, $name, $version, $language) = explode('/', $folderName);

        return [
            'bookName' => implode('/', [$vendor, $name]),
            'bookType' => $bookType ,
            'bookVersion' => $version,
        ];
    }

    private function formatMilliseconds(int $milliseconds): string
    {
        $t = round($milliseconds / 1000);
        return sprintf('%02d:%02d:%02d', ($t/3600),($t/60%60), $t%60);
    }
}
