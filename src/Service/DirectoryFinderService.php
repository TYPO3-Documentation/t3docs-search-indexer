<?php


namespace App\Service;


use Symfony\Component\Finder\Finder;

class DirectoryFinderService
{
    private $allowedPaths;

    private $excludedDirectories;

    public function __construct(array $allowedPaths, array $excludedDirectories)
    {
        $this->allowedPaths = $allowedPaths;
        $this->excludedDirectories = $excludedDirectories;
    }

    /**
     * Finds all directories containing documentation under rootPath (DOCS_ROOT_PATH)
     * taking into account 'allowed_paths' and 'excluded_directories'
     *
     * @param string $rootPath
     * @return Finder
     */
    public function getAllManualDirectories(string $rootPath): Finder
    {
        $allowedPathsRegexs = $this->wrapValuesWithPregDelimiters($this->allowedPaths);

        $finder = $this->getDirectoriesByPath($rootPath);
        $folders = $finder->path($allowedPathsRegexs);

        return $folders;
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

        // checks if given path is already a manual, as finder only checks subfolders
        if ($combinedPath !== $docRootPath && \file_exists($combinedPath . '/objects.inv.json')) {
            $finder = new Finder();
            $finder->append([$combinedPath]);
            return $finder;
        }
        $finder = new Finder();
        $finder->directories()
            ->in($combinedPath)
            ->exclude($this->excludedDirectories)
            ->filter($this->getFolderFilter());

        return $finder;
    }

    private function getFolderFilter()
    {
        return function (\SplFileInfo $file) {
            if (\file_exists($file->getPathname() . '/objects.inv.json')) {
                return true;
            }
            return false;
        };
    }

    /**
     * Wraps array values with regular expression delimiters
     *
     * @param array $regexs
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
