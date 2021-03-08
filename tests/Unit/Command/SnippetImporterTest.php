<?php

namespace Codappix\tests\Unit\Command;

use App\Command\SnippetImporter;
use App\Dto\Manual;
use App\Service\ImportManualHTMLService;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SnippetImporterTest extends TestCase
{
    /**
     * @test
     */
    public function rootPathIsUsedFromConfiguration()
    {
        $importer = $this->prophesize(ImportManualHTMLService::class);
        $dispatcher = $this->prophesize(EventDispatcherInterface::class);

        $importer->findManuals('_docsDefault')->shouldBeCalledTimes(1);

        $command = new SnippetImporter('_docsDefault', $importer->reveal(), $dispatcher->reveal());
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

        $importer->findManuals('_docsCustom')->shouldBeCalledTimes(1);

        $command = new SnippetImporter('_docsDefault', $importer->reveal(), $dispatcher->reveal());
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

        $manual1 = $this->prophesize(Manual::class);
        $manual1->getTitle()->willReturn('typo3/manual-1');
        $manual1->getAbsolutePath()->willReturn('');
        $manual2 = $this->prophesize(Manual::class);
        $manual2->getTitle()->willReturn('typo3/manual-2');
        $manual2->getAbsolutePath()->willReturn('');

        $importer->findManuals(Argument::any())->willReturn([
            $manual1->reveal(),
            $manual2->reveal(),
        ]);
        $importer->importManual(Argument::which('getTitle', 'typo3/manual-1'))->shouldBeCalledTimes(1);
        $importer->deleteManual(Argument::which('getTitle', 'typo3/manual-1'))->shouldBeCalledTimes(1);
        $importer->importManual(Argument::which('getTitle', 'typo3/manual-2'))->shouldBeCalledTimes(1);
        $importer->deleteManual(Argument::which('getTitle', 'typo3/manual-2'))->shouldBeCalledTimes(1);

        $command = new SnippetImporter('_docsDefault', $importer->reveal(), $dispatcher->reveal());
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

        $manual = $this->prophesize(Manual::class);
        $manual->getTitle()->willReturn('typo3/cms-core');
        $manual->getAbsolutePath()->willReturn('');

        $importer->findManual('_docsDefault', 'c/typo3/cms-core/master/en-us')
            ->willReturn($manual->reveal())
            ->shouldBeCalledTimes(1);
        $importer->deleteManual(Argument::which('getTitle', 'typo3/cms-core'))->shouldBeCalledTimes(1);
        $importer->importManual(Argument::which('getTitle', 'typo3/cms-core'))->shouldBeCalledTimes(1);

        $command = new SnippetImporter('_docsDefault', $importer->reveal(), $dispatcher->reveal());
        $commandTester = new CommandTester($command);
        $commandTester->execute(['packagePath' => 'c/typo3/cms-core/master/en-us']);
    }
}
