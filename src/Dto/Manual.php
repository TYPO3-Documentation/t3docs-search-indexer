<?php

namespace App\Dto;

use Symfony\Component\Finder\Finder;

class Manual
{
    /**
     * @var string
     */
    private $absolutePath;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $version;

    /**
     * @var string
     */
    private $language;

    /**
     * @var string
     */
    private $slug;

    public function __construct(
        string $absolutePath,
        string $title,
        string $type,
        string $version,
        string $language,
        string $slug
    ) {
        $this->absolutePath = $absolutePath;
        $this->title = $title;
        $this->type = $type;
        $this->version = $version;
        $this->language = $language;
        $this->slug = $slug;
    }

    public static function createFromFolder(\SplFileInfo $folder): Manual
    {
        $values = explode('/', $folder->getPathname());
        $values = array_slice($values, -5, 5);
        list($type, $vendor, $name, $version, $language) = $values;

         $map = [
            'c' => 'System extension',
            'p' => 'Community extension',
            'm' => 'TYPO3 manual'
        ];
        $type = $map[$type] ?? $type;

        return new Manual(
            $folder,
            implode('/', [$vendor, $name]),
            $type,
            $version,
            $language,
            implode('/', $values)
        );
    }

    public function getFilesWithSections(): Finder
    {
        $finder = new Finder();
        $finder
            ->files()
            ->in($this->getAbsolutePath())
            ->name('*.html')
            ->notName(['search.html', 'genindex.html', 'Targets.html', 'Quicklinks.html'])
            ->notPath(['_buildinfo', '_static', '_images', '_sources', 'singlehtml', 'Sitemap']);

        return $finder;
    }

    public function getAbsolutePath(): string
    {
        return $this->absolutePath;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}
