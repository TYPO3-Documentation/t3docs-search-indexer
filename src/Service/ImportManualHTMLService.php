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
    public function __construct(
        private readonly ElasticRepository $elasticRepository,
        private readonly ParseDocumentationHTMLService $parser,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function deleteManual(Manual $manual): void
    {
        $this->elasticRepository->deleteByManual($manual);
    }

    public function importManual(Manual $manual): void
    {
        $this->importSectionsFromManual($manual);
    }

    private function importSectionsFromManual(Manual $manual): void
    {
        $files = $manual->getFilesWithSections();

        $this->dispatcher->dispatch(new ManualStart($files), ManualStart::NAME);

        foreach ($files as $file) {
            if ($this->parser->checkIfMetaTagExistsInFile($file, 'x-typo3-indexer', 'noindex')) {
                continue;
            }
            $this->importSectionsFromFile($file, $manual);
            $this->dispatcher->dispatch(new ManualAdvance(), ManualAdvance::NAME);
        }

        $this->dispatcher->dispatch(new ManualFinish(), ManualFinish::NAME);
    }

    private function importSectionsFromFile(SplFileInfo $file, Manual $manual): void
    {
        foreach ($this->parser->getSectionsFromFile($file) as $section) {
            $section['manual_title'] = $manual->getTitle();
            $section['manual_type'] = $manual->getType();
            $section['manual_version'] = $manual->getVersion();
            $section['manual_language'] = $manual->getLanguage();
            $section['manual_slug'] = $manual->getSlug();
            $section['manual_keywords'] = $manual->getKeywords();
            $section['relative_url'] = $file->getRelativePathname();
            $section['content_hash'] = md5($section['snippet_title'] . $section['snippet_content']);

            $this->elasticRepository->addOrUpdateDocument($section);
        }
    }
}
