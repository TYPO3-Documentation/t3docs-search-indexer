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
    private $manualMetaData = [
        'manual_title' => 'TBD',
        'manual_type' => 'TBD',
        'manual_version' => 'TBD',
        'manual_language' => 'TBD',
        'manual_slug' => 'TBD'
    ];

    public function setManualName(string $manualName): void
    {
        $this->manualMetaData['manual_title'] = $manualName;
    }

    /**
     * @throws \InvalidArgumentException On unkown manual type.
     */
    public function setManualType(string $manualType): void
    {
        $this->manualMetaData['manual_type'] = $manualType;
    }

    public function setManualVersion(string $manualVersion): void
    {
        $this->manualMetaData['manual_version'] = $manualVersion;
    }

    public function setManualLanguage(string $manualLanguage): void
    {
        $this->manualMetaData['manual_language'] = $manualLanguage;
    }

    public function setManualSlug(string $manualSlug): void
    {
        $this->manualMetaData['manual_slug'] = $manualSlug;
    }

    /**
     * @param string $content
     * @param string $relativeFileName
     * @return array
     */
    public function parseContent(string $content, string $relativeFileName):array
    {
        // Set currentFileName
        $this->manualMetaData['relative_url'] = $relativeFileName;
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
        $manualMetaData = $this->manualMetaData;

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
                $sectionPiece = $manualMetaData;
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
