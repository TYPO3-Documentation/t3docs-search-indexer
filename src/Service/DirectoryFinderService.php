<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;

class DirectoryFinderService
{
    public function __construct(
        private readonly array $allowedPaths,
        private readonly array $excludedDirectories
    ) {}

    /**
     * Finds all directories containing documentation under rootPath (DOCS_ROOT_PATH)
     * taking into account 'allowed_paths' and 'excluded_directories'
     */
    public function getAllManualDirectories(string $rootPath): Finder
    {
        $allowedPathsRegexs = $this->wrapValuesWithPregDelimiters($this->allowedPaths);
        return $this->getDirectoriesByPath($rootPath)->path($allowedPathsRegexs);
    }

    /**
     * Finds all directories containing documentation under rootPath
     * taking into account 'excluded_directories'
     *
     * @throws \InvalidArgumentException
     */
    public function getDirectoriesByPath(string $docRootPath, string $packagePath = ''): Finder
    {
        $combinedPath = $docRootPath . ($packagePath ? '/' . $packagePath : '');

        $finder = new Finder();

        // If the path is a manual, use append; otherwise, set up the usual directory search
        if ($combinedPath !== $docRootPath && $this->objectsFileExists($combinedPath)) {
            $finder->append([$combinedPath]);
        } else {
            $finder->directories()
                ->in($combinedPath)
                ->exclude($this->excludedDirectories)
                ->filter($this->getFolderFilter());
        }

        return $finder;
    }

    private function getFolderFilter(): \Closure
    {
        $self = $this;
        return static function (\SplFileInfo $file) use ($self) {
            return $self->objectsFileExists($file->getPathname()) && $self->isNotIgnoredPath($file->getPathname());
        };
    }

    private function objectsFileExists(string $path): bool
    {
        return \file_exists($path . '/objects.inv') || \file_exists($path . '/objects.inv.json');
    }

    /**
     * For c/typo3/cms-core/* we want to exclude anything other than c/typo3/cms-core/main
     * Only manuals from `main` versions should be indexed as changelogs (according to the
     * documentation team's decision)
     */
    private function isNotIgnoredPath(string $path): bool
    {
        return !str_contains($path, '/c/typo3/cms-core/') || str_contains($path, 'c/typo3/cms-core/main');
    }

    /**
     * Wraps array values with regular expression delimiters
     */
    private function wrapValuesWithPregDelimiters(array $regexs): array
    {
        return array_map(static fn($value) => "#{$value}#", $regexs);
    }
}
