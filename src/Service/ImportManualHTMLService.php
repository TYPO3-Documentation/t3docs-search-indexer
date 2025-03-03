<?php

namespace App\Service;

use App\Config\ManualType;
use App\Dto\Manual;
use App\Event\ImportManual\ManualAdvance;
use App\Event\ImportManual\ManualFinish;
use App\Event\ImportManual\ManualStart;
use App\Repository\ElasticRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\SplFileInfo;

class ImportManualHTMLService
{
    public function __construct(
        private ElasticRepository $elasticRepository,
        private ParseDocumentationHTMLService $parser,
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger
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
        // for the core changelog, we need to treat the whole file as a single section
        $sections = ($manual->getType() === ManualType::CoreChangelog->value)
            ? [$this->parser->getFileContentAsSingleSection($file)]
            : $this->parser->getSectionsFromFile($file);

        foreach ($sections as $section) {
            // if for some reason the documentation file does not contain a title or content, skip it
            if (!isset($section['snippet_title']) && !isset($section['snippet_content'])) {
                $this->logger->warning('Skipping section without title or content', [
                    'manual' => $manual->getTitle(),
                    'file' => $file->getPathname(),
                ]);
                continue;
            }

            $section['manual_title'] = $manual->getTitle();
            $section['manual_vendor'] = $manual->getVendor();
            $section['manual_extension'] = $manual->getName();
            $section['manual_package'] = implode('/', [$manual->getVendor(), $manual->getName()]);
            $section['manual_type'] = $manual->getType();
            $section['manual_version'] = $manual->getVersion();
            $section['manual_language'] = $manual->getLanguage();
            $section['manual_slug'] = $manual->getSlug();
            $section['manual_keywords'] = $manual->getKeywords();
            $section['relative_url'] = $file->getRelativePathname();
            $section['content_hash'] = md5($section['snippet_title'] . $section['snippet_content']);
            $section['is_core'] = $manual->isCore();
            $section['is_last_versions'] = $manual->isLastVersions();

            $this->elasticRepository->addOrUpdateDocument($section);
        }
    }
}
