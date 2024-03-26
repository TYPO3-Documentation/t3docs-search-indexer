<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\SplFileInfo;

class ParseDocumentationHTMLService
{
    private bool $newRendering = true;

    public function checkIfMetaTagExistsInFile(SplFileInfo $file, string $name, string $content = null): bool
    {
        $fileContent = $file->getContents();

        $selector = sprintf('meta[name="%s"]', $name);

        if ($content !== null) {
            $selector .= sprintf('[content="%s"]', $content);
        }

        $crawler = new Crawler($fileContent);
        $metaTags = $crawler->filter($selector);

        return (bool) $metaTags->count();
    }

    public function getSectionsFromFile(SplFileInfo $file): array
    {
        $fileContents = $file->getContents();
        $crawler = new Crawler($fileContents);
        $this->newRendering = $crawler->filterXPath("//meta[@name='generator' and @content='phpdocumentor/guides']")->count();

        return $this->getSections($crawler);
    }

    private function getSections(Crawler $html): array
    {
        $sections = $html->filter($this->newRendering ? 'article' : 'div[itemprop="articleBody"]');

        return $sections->count() === 0 ? [] : $this->getAllSections($sections);
    }

    /**
     * When multiple sections are present, including nested sections,
     * the process iterates over each section to fetch its content snippet.
     * However, child sections are excluded from this content retrieval,
     * instead, they are treated as distinct sections individually.
     */
    private function getAllSections(Crawler $sections): array
    {
        $sectionPieces = [];
        foreach ($sections->filter($this->newRendering ? 'section' : 'div.section') as $section) {
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

        return $headline instanceof \DOMElement ? [
            'headlineText' => filter_var(htmlspecialchars($headline->textContent), FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH),
            'node' => $headline,
        ] : [];
    }

    private function stripSubSectionsIfAny(\DOMElement $section): \DOMElement
    {
        $crawler = new Crawler($section);
        $subSections = $crawler->filter($this->newRendering ? 'section section' : 'div.section div.section');
        if ($subSections->count() === 0) {
            return $section;
        }

        foreach ($subSections as $subSection) {
            try {
                $section->removeChild($subSection);
            } catch (\DOMException) {
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
            } catch (\DOMException) {
                continue;
            }
        }
        return $section;
    }

    private function sanitizeString(string $input): string
    {
        return trim(preg_replace('/\s\s+/', ' ', $input));
    }
}
