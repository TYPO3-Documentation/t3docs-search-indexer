<?php

namespace App\Service;

use App\Dto\Manual;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;

class ParseDocumentationHTMLService
{
    /** @var KernelInterface */
    private $kernel;

    /**
     * ParseDocumentationHTMLService constructor.
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function findFolders(string $rootPath): Finder
    {
        $docSearchParameters = $this->kernel->getContainer()->getParameter('docsearch');
        $finder = new Finder();
        $finder->directories()->in($rootPath)->depth('== 4')->exclude($docSearchParameters['indexer']['excluded_directories']);

        return $finder;
    }

    public function createFromFolder(string $prefixFolder, SplFileInfo $folder): Manual
    {
        $prefixFolder = rtrim($prefixFolder, '/') . '/';
        $folderPath = (string) $folder;

        $relativeFolderPath = str_replace($prefixFolder, '', $folderPath);
        list($type, $vendor, $name, $version, $language) = explode('/', $relativeFolderPath);

        return new Manual(
            $folder,
            implode('/', [$vendor, $name]),
            $type,
            $version,
            $language,
            $relativeFolderPath
        );
    }

    public function getFilesWithSections(Manual $manual): Finder
    {
        $finder = new Finder();
        $finder
            ->files()
            ->in($manual->getAbsolutePath())
            ->name('*.html')
            ->notName('search.html')
            ->notName('genindex.html')
            ->notPath('_buildinfo')
            ->notPath('_static')
            ->notPath('singlehtml');

        return $finder;
    }

    public function getSectionsFromFile(SplFileInfo $file): array
    {
        return $this->getSections($file->getContents());
    }

    private function getSections(string $html): array
    {
        $crawler = new Crawler($html);
        $sections = $crawler->filter('div.toBeIndexed');

        if ($sections->count() === 0) {
            return [];
        }

        return $this->getAllSections($sections);
    }

    private function getAllSections(Crawler $sections): array
    {
        $sectionPieces = [];
        foreach ($sections->filter('div.section') as $section) {
            $foundHeadline = $this->findHeadline($section);
            if ($foundHeadline === []) {
                continue;
            }

            $sectionPiece = [
                'fragment' => $section->getAttribute('id'),
                'snippet_title' => $foundHeadline['headlineText'],
            ];

            $section->removeChild($foundHeadline['node']);
            $sectionPiece['snippet_content'] = $this->sanitizeString(
                $this->stripSubSectionsIfAny($section)
            );
            $sectionPieces[] = $sectionPiece;
        }

        return $sectionPieces;
    }

    private function findHeadline(\DOMElement $section): array
    {
        $crawler = new Crawler($section);
        $headline = $crawler->filter('h1, h2, h3, h4, h5, h6')->getNode(0);

        if (($headline instanceof \DOMElement) === false) {
            return [];
        }

        return [
            'headlineText' => filter_var($headline->textContent, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH),
            'node' => $headline,
        ];
    }

    private function stripSubSectionsIfAny(\DOMElement $section): string
    {
        $crawler = new Crawler($section);
        $subSections = $crawler->filter('div.section div.section');
        if ($subSections->count() === 0) {
            return $section->textContent;
        }

        foreach ($subSections as $subSection) {
            try {
                $section->removeChild($subSection);
            } catch (\DOMException $e) {
                continue;
            }
        }

        return $section->textContent;
    }

    private function sanitizeString(string $input): string
    {
        $pattern = [
            '/\s\s+/',
        ];
        $regexBuildName = preg_replace($pattern, ' ', $input);
        return trim($regexBuildName);
    }
}
