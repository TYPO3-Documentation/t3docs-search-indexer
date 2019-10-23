<?php
/**
 * Created by PhpStorm.
 * User: mathiasschreiber
 * Date: 18.01.18
 * Time: 14:33
 */

namespace App\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

class ConsistencyCheck extends ContainerAwareCommand
{
    private $rootPath = '';

    /**
     *
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure():void
    {
        $this->setName('docsearch:consistency');
        $this->setDescription('Checks documentation for consistency');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \LogicException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->rootPath = $this->getContainer()->get('kernel')->getProjectDir() .'/_docs';

        $io = new SymfonyStyle($input, $output);
        $io->title('Starting import');
//        if (is_numeric($input->getArgument('package'))) {
//
//        }
        $io->section('Looking for manuals to import');
        $manualsToImport = $this->findDocumentationFolders();
        $io->writeln('Found ' . $manualsToImport->count()  . ' manuals.');
        foreach ($manualsToImport as $manualFolder) {
            $io->section('Importing ' . $manualFolder->getRelativePathname()  . ' - sit tight.');
        }
    }

    /**
     * @return Finder
     * @throws \InvalidArgumentException
     */
    private function findDocumentationFolders(): Finder
    {
        $finder = new Finder();
        $finder->directories()->in($this->rootPath)->depth(0);
        return $finder;
    }
}
