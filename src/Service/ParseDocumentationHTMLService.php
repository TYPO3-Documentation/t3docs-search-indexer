<?php
/**
 * Created by PhpStorm.
 * User: mathiasschreiber
 * Date: 15.01.18
 * Time: 19:34
 */

namespace App\Service;


use Symfony\Component\CssSelector\CssSelectorConverter;

class ParseDocumentationHTMLService
{
    private $bookMetaData = [
        'book_title' => 'TBD',
        'book_type' => 'TBD',
        'book_version' => 'TBD',
        'book_slug' => 'TBD'
    ];

    public function setBookName(string $bookName): void
    {
        $this->bookMetaData['book_title'] = $bookName;
    }

    public function setBookType(string $bookType): void
    {
        $this->bookMetaData['book_type'] = $bookType;
    }

    public function setBookVersion(string $bookVersion): void
    {
        $this->bookMetaData['book_version'] = $bookVersion;
    }

    public function setBookSlug(string $bookSlug): void
    {
        $this->bookMetaData['book_slug'] = $bookSlug;
    }

    /**
     * @param string $content
     * @param string $relativeFileName
     * @return array
     */
    public function parseContent(string $content, string $relativeFileName):array
    {
        // Set currentFileName
        $this->bookMetaData['relative_url'] = $relativeFileName;
        libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML($content);
        $xpath = new \DOMXPath($document);
        $converter = new CssSelectorConverter();
        $mainContentQuery = $converter->toXPath('div.toBeIndexed');
        $query = $xpath->query($mainContentQuery);
        if ($query->length > 0) {
            $mainSection = $query->item(0)->C14N();
            return $this->getAllSections($mainSection);
        }
        return [];
    }

    private function getAllSections(string $markup): array
    {
        $bookMetaData = $this->bookMetaData;

        $sectionPieces = [];
        $document = new \DOMDocument();
        $document->loadHTML($markup);
        $xpath = new \DOMXPath($document);
        $converter = new CssSelectorConverter();
        $sections = $xpath->query($converter->toXPath('div.section'));
        /**
         * @var int $index
         * @var \DOMElement $section
         */
        foreach ($sections as $index => $section) {
            $foundHeadline = $this->findHeadline($section, $xpath);
            if ($foundHeadline !== []) {
                $sectionPiece = $bookMetaData;
                $sectionPiece['fragment'] = $section->getAttribute('id');
                $sectionPiece['snippet_title'] = $foundHeadline['headlineText'];
                $section->removeChild($foundHeadline['node']);
                $sectionPiece['snippet_content'] = $this->sanitizeString($this->stripSubSectionsIfAny($section, $xpath));
                $sectionPieces[] = $sectionPiece;
            }

        }
        return $sectionPieces;
    }

    private function stripSubSectionsIfAny(\DOMElement $section, \DOMXPath $xpath): string
    {
        $converter = new CssSelectorConverter();
        $subSections = $xpath->query($converter->toXPath('div.section div.section'), $section);
        if ($subSections->length === 0) {
            return $section->textContent;
        }
        foreach ($subSections as $index => $subSection) {
            try {
                $section->removeChild($subSection);
            } catch (\Exception $e) {
            }
        }
        return $section->C14N();
    }

    private function findHeadline(\DOMElement $section, \DOMXPath $xpath): array
    {
        $result = $xpath->query('*[starts-with(name(), \'h\')]', $section);
        $element = $result->item(0);
        try {
            return [
                'headlineText' => filter_var($element->textContent, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH),
                'node' => $element
            ];
        } catch (\Exception $e) {
            $foo = '';
        }
        return [];
    }

    private function sanitizeString(string $input): string
    {
        $pattern = [
            '/\s\s+/',
        ];
        $regexBuildName = preg_replace($pattern, ' ', strip_tags($input));
        return trim($regexBuildName);
    }
}