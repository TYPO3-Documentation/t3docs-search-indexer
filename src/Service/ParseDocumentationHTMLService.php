<?php
/**
 * Created by PhpStorm.
 * User: mathiasschreiber
 * Date: 15.01.18
 * Time: 19:34
 */

namespace App\Service;

use App\Dto\Manual;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ParseDocumentationHTMLService
{
    /**
     * @throws \InvalidArgumentException
     */
    public function findFolders(string $rootPath): Finder
    {
        $finder = new Finder();
        $finder->directories()->in($rootPath)->depth('== 4');

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

    private function getSections(string $content): array
    {
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
                $sectionPiece = [
                    'fragment' => $section->getAttribute('id'),
                    'snippet_title' => $foundHeadline['headlineText'],
                ];

                $section->removeChild($foundHeadline['node']);
                $sectionPiece['snippet_content'] = $this->sanitizeString(
                    $this->stripSubSectionsIfAny($section, $xpath)
                );
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
