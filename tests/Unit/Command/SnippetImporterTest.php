<?php

namespace Codappix\tests\Unit\Command;

use App\Command\SnippetImporter;
use App\Service\DirectoryFinderService;
use App\Service\ImportManualHTMLService;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;

class SnippetImporterTest extends TestCase
{
    /**
     * @test
     */
    public function rootPathIsUsedFromConfiguration()
    {
        $importer = $this->prophesize(ImportManualHTMLService::class);
        $dispatcher = $this->prophesize(EventDispatcherInterface::class);
        $directoryFinder = $this->prophesize(DirectoryFinderService::class);

        $directoryFinder->getAllManualDirectories('_docsDefault')->shouldBeCalledTimes(1);

        $command = new SnippetImporter('_docsDefault', $importer->reveal(), $dispatcher->reveal(),
            $directoryFinder->reveal());
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * @test
     */
    public function rootPathCanBeDefinedViaOption()
    {
        $importer = $this->prophesize(ImportManualHTMLService::class);
        $dispatcher = $this->prophesize(EventDispatcherInterface::class);
        $directoryFinder = $this->prophesize(DirectoryFinderService::class);

        $directoryFinder->getAllManualDirectories('_docsCustom')->shouldBeCalledTimes(1);

        $command = new SnippetImporter('_docsDefault', $importer->reveal(), $dispatcher->reveal(),
            $directoryFinder->reveal());
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--rootPath' => '_docsCustom']);
    }

    /**
     * @test
     */
    public function callsImportProcedureManualForAllReturnedManuals()
    {
        $importer = $this->prophesize(ImportManualHTMLService::class);
        $dispatcher = $this->prophesize(EventDispatcherInterface::class);

        $folder = $this->prophesize(\SplFileInfo::class);
        $folder->getPathname()->willReturn('_docsFolder/c/typo3/manual-1/master/en-us');
        $folder->__toString()->willReturn('_docsFolder/c/typo3/manual-1/master/en-us');
        $folder2 = $this->prophesize(\SplFileInfo::class);
        $folder2->getPathname()->willReturn('_docsFolder/c/typo3/manual-2/master/en-us');
        $folder2->__toString()->willReturn('_docsFolder/c/typo3/manual-2/master/en-us');

        $finder = new Finder();
        $finder->Append([$folder->reveal(), $folder2->reveal()]);

        $importer->importManual(Argument::which('getTitle', 'typo3/manual-1'))->shouldBeCalledTimes(1);
        $importer->deleteManual(Argument::which('getTitle', 'typo3/manual-1'))->shouldBeCalledTimes(1);
        $importer->importManual(Argument::which('getTitle', 'typo3/manual-2'))->shouldBeCalledTimes(1);
        $importer->deleteManual(Argument::which('getTitle', 'typo3/manual-2'))->shouldBeCalledTimes(1);

        $directoryFinder = $this->prophesize(DirectoryFinderService::class);
        $directoryFinder->getAllManualDirectories(Argument::any())->willReturn($finder)->shouldBeCalledTimes(1);

        $command = new SnippetImporter('_docsDefault', $importer->reveal(), $dispatcher->reveal(),
            $directoryFinder->reveal());
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * @test
     */
    public function importsOnlyProvidedPackage()
    {
        $importer = $this->prophesize(ImportManualHTMLService::class);
        $dispatcher = $this->prophesize(EventDispatcherInterface::class);

        $folder = $this->prophesize(\SplFileInfo::class);
        $folder->getPathname()->willReturn('_docsFolder/c/typo3/cms-core/master/en-us');
        $folder->__toString()->willReturn('_docsFolder/c/typo3/cms-core/master/en-us');

        $finder = new Finder();
        $finder->Append([$folder->reveal()]);


        $directoryFinder = $this->prophesize(DirectoryFinderService::class);
        $directoryFinder->getDirectoriesByPath('_docsDefault',
            'c/typo3/cms-core/master/en-us')->willReturn($finder)->shouldBeCalledTimes(1);

        $importer->deleteManual(Argument::which('getTitle', 'typo3/cms-core'))->shouldBeCalledTimes(1);
        $importer->importManual(Argument::which('getTitle', 'typo3/cms-core'))->shouldBeCalledTimes(1);

        $command = new SnippetImporter('_docsDefault', $importer->reveal(), $dispatcher->reveal(),
            $directoryFinder->reveal());
        $commandTester = new CommandTester($command);
        $commandTester->execute(['packagePath' => 'c/typo3/cms-core/master/en-us']);
    }
}
