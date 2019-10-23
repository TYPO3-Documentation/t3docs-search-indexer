<?php

namespace Codappix\tests\Unit\Command;

use App\Command\SnippetImporter;
use App\Dto\Manual;
use App\Service\ImportManualHTMLService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SnippetImporterTest extends TestCase
{
    /**
     * @test
     */
    public function rootPathIsUsedFromConfiguration()
    {
        $importer = $this->getMockBuilder(ImportManualHTMLService::class)->disableOriginalConstructor()->getMock();
        $dispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $importer->expects($this->once())->method('findManuals')->with('_docsDefault');

        $command = new SnippetImporter('_docsDefault', $importer, $dispatcher);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * @test
     */
    public function rootPathCanBeDefinedViaOption()
    {
        $importer = $this->getMockBuilder(ImportManualHTMLService::class)->disableOriginalConstructor()->getMock();
        $dispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $importer->expects($this->once())->method('findManuals')->with('_docsCustom');

        $command = new SnippetImporter('_docsDefault', $importer, $dispatcher);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--rootPath' => '_docsCustom']);
    }

    /**
     * @test
     */
    public function callsImportManualForAllReturnedManuals()
    {
        $importer = $this->getMockBuilder(ImportManualHTMLService::class)->disableOriginalConstructor()->getMock();
        $dispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $manualMock1 = $this->getMockBuilder(Manual::class)->disableOriginalConstructor()->getMock();
        $manualMock1->expects($this->any())->method('getTitle')->willReturn('typo3/manual-1');
        $manualMock2 = $this->getMockBuilder(Manual::class)->disableOriginalConstructor()->getMock();
        $manualMock2->expects($this->any())->method('getTitle')->willReturn('typo3/manual-2');

        $importer->expects($this->once())->method('findManuals')->willReturn([
            $manualMock1,
            $manualMock2,
        ]);
        $importer->expects($this->exactly(2))->method('importManual')->withConsecutive(
            $this->callback(function (Manual $manual) {
                return $manual->getTitle() === 'typo3/manual-1';
            }),
            $this->callback(function (Manual $manual) {
                return $manual->getTitle() === 'typo3/manual-2';
            })
        );

        $command = new SnippetImporter('_docsDefault', $importer, $dispatcher);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * @test
     */
    public function importsOnlyProvidedPackage()
    {
        $importer = $this->getMockBuilder(ImportManualHTMLService::class)->disableOriginalConstructor()->getMock();
        $dispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();

        $manualMock = $this->getMockBuilder(Manual::class)->disableOriginalConstructor()->getMock();
        $manualMock->expects($this->any())->method('getTitle')->willReturn('typo3/manual-1');

        $importer->expects($this->once())->method('findManual')
            ->with('_docsDefault', 'c/typo3/cms-core/master/en-us')
            ->willReturn($manualMock);
        $importer->expects($this->once())->method('importManual')->withConsecutive(
            $this->callback(function (Manual $manual) {
                return $manual->getTitle() === 'typo3/cms-core';
            })
        );

        $command = new SnippetImporter('_docsDefault', $importer, $dispatcher);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['packagePath' => 'c/typo3/cms-core/master/en-us']);
    }
}
