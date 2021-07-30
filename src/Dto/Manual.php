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

    public static function createFromFolder(\SplFileInfo $folder, $changelog = false): Manual
    {
        $pathArray = explode('/', $folder->getPathname());
        if ($changelog) {
            // e.g. c/typo3/cms-core/master/en-us/Changelog/9.4
            $values = array_slice($pathArray, -7, 7);
            list($_, $vendor, $name, $__, $language, $type, $version) = $values;
            $type = \strtolower($type);
            $name .= '-' . $type;
        } else {
            // e.g. "c/typo3/cms-workspaces/9.5/en-us"
            $values = array_slice($pathArray, -5, 5);
            list($type, $vendor, $name, $version, $language) = $values;
        }

         $map = [
            'c' => 'System extension',
            'p' => 'Community extension',
            'm' => 'TYPO3 manual',
            'changelog' => 'Core changelog',
            'h' => 'Docs Home Page',
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

        if ($this->getTitle() === 'typo3/cms-core') {
            $finder->notPath('Changelog');
        }
        return $finder;
    }

    /**
     * TYPO3 Core Changelogs are treated as submanuals from typo3/cms-core manual
     *
     * @return array<Manual>
     */
    public function getSubManuals(): array
    {
        if ($this->getTitle() !== 'typo3/cms-core') {
            return [];
        }
        if ($this->getVersion() !== 'master') {
            return [];
        }
        $finder = new Finder();
        $finder
            ->directories()
            ->in($this->getAbsolutePath() . '/Changelog')
            ->depth(0);

        $subManuals = [];
        foreach ($finder as $changelogFolder) {
            $subManuals[] = self::createFromFolder($changelogFolder, true);
        }
        return $subManuals;
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
