<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;

class DirectoryFinderService
{
    public function __construct(private readonly array $allowedPaths, private readonly array $excludedDirectories)
    {
    }

    /**
     * Finds all directories containing documentation under rootPath (DOCS_ROOT_PATH)
     * taking into account 'allowed_paths' and 'excluded_directories'
     *
     * @return Finder
     */
    public function getAllManualDirectories(string $rootPath): Finder
    {
        $allowedPathsRegexs = $this->wrapValuesWithPregDelimiters($this->allowedPaths);

        $finder = $this->getDirectoriesByPath($rootPath);
        return $finder->path($allowedPathsRegexs);
    }

    /**
     * Finds all directories containing documentation under rootPath
     * taking into account 'excluded_directories'
     *
     * @throws \InvalidArgumentException
     */
    public function getDirectoriesByPath(string $docRootPath, string $packagePath=''): Finder
    {
        $combinedPath = $docRootPath . ($packagePath ?  '/' . $packagePath : '');

        $finder = new Finder();

        // checks if given path is already a manual, as finder only checks subfolders
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

    private function getFolderFilter()
    {
        $self = $this;
        return static function (\SplFileInfo $file) use ($self) {
            return $self->objectsFileExists($file->getPathname());
        };
    }

    private function objectsFileExists(string $path): bool
    {
        return \file_exists($path . '/objects.inv') || \file_exists($path . '/objects.inv.json');
    }

    /**
     * Wraps array values with regular expression delimiters
     *
     * @return array
     */
    private function wrapValuesWithPregDelimiters(array $regexs): array
    {
        array_walk($regexs, function (&$value, $key) {
            $value = '#' . $value . '#';
        });
        return $regexs;
    }
}
