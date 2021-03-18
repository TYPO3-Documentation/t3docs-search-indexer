<?php


namespace App\Service;


use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

class DirectoryFinderService
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
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
        $docSearchParameters = $this->kernel->getContainer()->getParameter('docsearch');
        $allowedPaths = $docSearchParameters['indexer']['allowed_paths'];
        $allowedPathsRegexs = $this->wrapValuesWithPregDelimiters($allowedPaths);

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
    public function getDirectoriesByPath(string $rootPath): Finder
    {
        $docRootPath = $this->kernel->getContainer()->getParameter('docs_root_path');

        // checks if given path is already a manual, as finder only checks subfolders
        if ($rootPath !== $docRootPath && \file_exists($rootPath . '/objects.inv.json')) {
            $finder = new Finder();
            $finder->append([$rootPath]);
            return $finder;
        }
        $docSearchParameters = $this->kernel->getContainer()->getParameter('docsearch');
        $finder = new Finder();
        $finder->directories()
            ->in($rootPath)
            ->exclude($docSearchParameters['indexer']['excluded_directories'])
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
