<?php


namespace App\Service;


use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;

class DirectoryFinderService
{
    /** @var Finder */
    private $finder;
    /** @var KernelInterface */
    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->finder = new Finder();
        $this->kernel = $kernel;
    }

    /**
     * @param string $rootPath
     * @return array
     */
    public function findAllowedDirectoriesForDocSearch(string $rootPath): array
    {
        return $this->getAllowedDirectories($rootPath);
    }

    /**
     * @return Finder
     */
    public function getFinder(): Finder
    {
        return $this->finder;
    }

    /**
     * @return KernelInterface
     */
    public function getKernel(): KernelInterface
    {
        return $this->kernel;
    }

    /**
     * @param $rootPath
     * @return array
     */
    private function getAllowedDirectories($rootPath): array
    {
        $projectDir = $this->kernel->getProjectDir();
        $docSearchParameters = $this->kernel->getContainer()->getParameter('docsearch');
        $indexerParameters = $docSearchParameters['indexer'];
        $allowedDirectories = $indexerParameters['allowed_directories'];
        $foundDirectories = [];
        $manualsPathCollection = [];
        $response['message'] = '';
        $response['manualsPath'] = [];

        $folders = $this->finder
            ->directories()
            ->name($allowedDirectories)
            ->in($projectDir . DIRECTORY_SEPARATOR . $rootPath)
        ;

        /** @var SplFileInfo $folder */
        foreach ($folders->getIterator() as $folder) {
            $foundDirectories[] = $folder->getBasename();
        }

        $missingDirectories = array_diff($allowedDirectories, $foundDirectories);

        if (count($missingDirectories) === count($allowedDirectories)) {
            $messagePattern = '<error>Directories %s was not found in root directory %s. Please, check configuration</error>';
            $message = sprintf($messagePattern, implode(', ', $missingDirectories), $rootPath);

            return $this->createResponse($message, $manualsPathCollection);
        }

        if (count($missingDirectories) < count($allowedDirectories)) {
            $messagePattern = '<info>Directories %s was not found in root directory %s.</info>';
            $message = sprintf($messagePattern, implode(', ', $missingDirectories), $rootPath);

            foreach ($foundDirectories as $foundDirectory) {
                $manualsPathCollection[] = $rootPath . DIRECTORY_SEPARATOR . $foundDirectory;
            }

            return $this->createResponse($message, $manualsPathCollection);
        }

        $messagePattern = '<info>All directories %s was not found in root directory %s.</info>';
        $message = sprintf($messagePattern, implode(', ', $foundDirectories), $rootPath);

        foreach ($foundDirectories as $foundDirectory) {
            $manualsPathCollection[] = $rootPath . DIRECTORY_SEPARATOR . $foundDirectory;
        }

        return $this->createResponse($message, $manualsPathCollection);
    }

    /**
     * @param string $message
     * @param array $manualLinks
     * @return array
     */
    private function createResponse(string $message, array $manualLinks): array
    {
        return [
            'message' => $message,
            'manualsPath' => $manualLinks
        ];
    }
}