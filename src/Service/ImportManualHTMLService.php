<?php

namespace App\Service;

use App\Dto\Manual;
use App\Event\ImportManual\ManualAdvance;
use App\Event\ImportManual\ManualFinish;
use App\Event\ImportManual\ManualStart;
use App\Repository\ElasticRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\SplFileInfo;

class ImportManualHTMLService
{
    /**
     * @var ElasticRepository
     */
    private $elasticRepository;

    /**
     * @var ParseDocumentationHTMLService
     */
    private $parser;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(
        ElasticRepository $elasticRepository,
        ParseDocumentationHTMLService $parser,
        EventDispatcherInterface $dispatcher
    ) {
        $this->elasticRepository = $elasticRepository;
        $this->parser = $parser;
        $this->dispatcher = $dispatcher;
    }

    public function findManuals(string $rootPath): array
    {
        $manuals = [];
        foreach ($this->parser->findFolders($rootPath) as $folder) {
            /* @var $folder SplFileInfo */
            $manuals[] = $this->parser->createFromFolder($rootPath, $folder);
        }
        return $manuals;
    }

    public function findManual(string $rootPath, string $manualPath): Manual
    {
        $folder = new SplFileInfo($rootPath . '/' . $manualPath, '', '');
        return $this->parser->createFromFolder($rootPath, $folder);
    }

    public function deleteManual(Manual $manual)
    {
        $this->elasticRepository->deleteByManual($manual);
    }

    public function importManual(Manual $manual)
    {
        $this->importSectionsFromManual($manual);
    }

    private function importSectionsFromManual(Manual $manual): void
    {
        $files = $this->parser->getFilesWithSections($manual);

        $this->dispatcher->dispatch(new ManualStart($files), ManualStart::NAME);

        foreach ($files as $file) {
            $this->importSectionsFromFile($file, $manual);
            $this->dispatcher->dispatch(new ManualAdvance(), ManualAdvance::NAME);
        }

        $this->dispatcher->dispatch(new ManualFinish(), ManualFinish::NAME);
    }

    private function importSectionsFromFile(SplFileInfo $file, Manual $manual)
    {
        foreach ($this->parser->getSectionsFromFile($file) as $section) {
            $section['manual_title'] = $manual->getTitle();
            $section['manual_type'] = $manual->getType();
            $section['manual_version'] = $manual->getVersion();
            $section['manual_language'] = $manual->getLanguage();
            $section['manual_slug'] = $manual->getSlug();
            $section['relative_url'] = $file->getRelativePathname();

            $this->elasticRepository->addOrUpdateDocument($section);
        }
    }
}
