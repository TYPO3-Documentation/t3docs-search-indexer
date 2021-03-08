<?php


namespace App\Command;

use App\Dto\Manual;
use App\Service\ImportManualHTMLService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Stopwatch\Stopwatch;

class SingleManualImporter extends Command
{
    private const INDEX_FOLDERS = ['c', 'm', 'p'];
    private const FOLDER_DEPTH = 4;
    /**
     * @var string $defaultRootPath
     */
    private $defaultRootPath;

    /**
     * @var string $appRootDir
     */
    private $appRootDir;

    /**
     * @var ImportManualHTMLService $importer
     */
    private $importer;

    /**
     * @var Finder $finder
     */
    private $finder;

    private $finderPath;

    private $pathToManual;

    private $indexFolder;

    private $incrementor;

    /**
     * SingleManualImporter constructor.
     * @param string $defaultRootPath
     * @param string $appRootDir
     * @param ImportManualHTMLService $importer
     */
    public function __construct(string $defaultRootPath, string $appRootDir, ImportManualHTMLService $importer)
    {
        $this->defaultRootPath = $defaultRootPath;
        $this->appRootDir = $appRootDir;
        $this->importer = $importer;
        $this->finder = new Finder();
        $this->incrementor = 0;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('docsearch:import:single-manual');
        $this->setDescription('Imports single manual');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->selectIndexFolder($input, $output)
            ->selectSubFolder($input, $output)
            ->importManuals($input, $output);
    }

    private function importManuals(InputInterface $input, OutputInterface $output)
    {
        /** @var Manual $manual */
        $manual = $this->importer->findManual($this->defaultRootPath, $this->pathToManual);
        $timer = new Stopwatch();
        $timer->start('importer');

        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Starting import');
        $this->io->writeln('Import manual ' . $manual->getTitle());

        $this->importer->deleteManual($manual);
        $this->importer->importManual($manual);

        $totalTime = $timer->stop('importer');
        $this->io->title('importing took ' . $this->formatMilliseconds($totalTime->getDuration()));
    }

    private function selectIndexFolder(InputInterface $input, OutputInterface $output): self
    {
        $finderPathFormat = $this->defaultRootPath . '/%s';

        $question = new ChoiceQuestion(
            'Select folder', self::INDEX_FOLDERS
        );
        $question->setErrorMessage('Can not index folder \'%s\'');
        $folder = $this->getQuestionHelper()->ask($input, $output, $question);

        $this->pathToManual = $folder;
        $this->finderPath = $this->appRootDir . DIRECTORY_SEPARATOR . sprintf($finderPathFormat, $folder);
        $this->indexFolder = $folder;

        return $this;
    }

    private function selectSubFolder(InputInterface $input, OutputInterface $output): self
    {
        $this->incrementor++;
        $finder = $this->finder->directories()->in($this->finderPath)->depth('== 0');
        $subcategories = [];
        $subcategoriesListOptions = [];
        $i = 0;

        /** @var SplFileInfo $folder */
        foreach ($finder->getIterator() as $folder) {
            $subcategories[$i]['dirname'] = $folder->getBasename();
            $subcategories[$i]['realPath'] = $folder->getRealPath();
            $i++;
        }

        foreach ($subcategories as $key => $value) {
            $subcategoriesListOptions[$key] = $value['dirname'];
        }

        $question = new ChoiceQuestion(
            'Select folder', $subcategoriesListOptions
        );

        $question->setErrorMessage('Can not find folder \'%s\'');
        $folder = $this->getQuestionHelper()->ask($input, $output, $question);

        $this->finderPath .= DIRECTORY_SEPARATOR . $folder;
        $this->pathToManual .= DIRECTORY_SEPARATOR . $folder;

        if ($this->incrementor < self::FOLDER_DEPTH) {
            $this->selectSubFolder($input, $output);
        }

        return $this;

    }

    /**
     * @return QuestionHelper
     */
    private function getQuestionHelper(): QuestionHelper
    {
        return $this->getHelper('question');
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

}