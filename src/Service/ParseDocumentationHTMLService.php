<?php

namespace App\Service;

use App\Dto\Manual;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ParseDocumentationHTMLService
{
    public function createFromFolder(\SplFileInfo $folder): Manual
    {
        $values = explode('/', $folder->getPathname());
        $values = array_slice($values, -6, 5);
        list($type, $vendor, $name, $version, $language) = $values;

        return new Manual(
            $folder,
            implode('/', [$vendor, $name]),
            $type,
            $version,
            $language,
            implode('/', $values)
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
        $sections = $crawler->filter('div[itemprop="articleBody"]');

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
            $section = $this->stripSubSectionsIfAny($section);
            $section = $this->stripCodeExamples($section);

            $sectionPiece['snippet_content'] = $this->sanitizeString(
                $section->textContent
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

    private function stripSubSectionsIfAny(\DOMElement $section): \DOMElement
    {
        $crawler = new Crawler($section);
        $subSections = $crawler->filter('div.section div.section');
        if ($subSections->count() === 0) {
            return $section;
        }

        foreach ($subSections as $subSection) {
            try {
                $section->removeChild($subSection);
            } catch (\DOMException $e) {
                continue;
            }
        }
        return $section;
    }

    private function stripCodeExamples(\DOMElement $section): \DOMElement
    {
        $crawler = new Crawler($section);

        $preTags = $crawler->filter('pre');
        if ($preTags->count() === 0) {
            return $section;
        }

        foreach ($preTags as $tag) {
            try {
                $tag->parentNode->removeChild($tag);
            } catch (\DOMException $e) {
                continue;
            }
        }
        return $section;
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
