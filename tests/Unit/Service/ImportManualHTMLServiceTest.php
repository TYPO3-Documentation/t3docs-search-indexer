<?php

namespace App\Tests\Unit\Service;

use App\Dto\Manual;
use App\Repository\ElasticRepository;
use App\Service\ImportManualHTMLService;
use App\Service\ParseDocumentationHTMLService;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ImportManualHTMLServiceTest extends TestCase
{
    /**
     * @test
     */
    public function findsManuals()
    {
        $parser = $this->prophesize(ParseDocumentationHTMLService::class);
        $subject = new ImportManualHTMLService(
            $this->prophesize(ElasticRepository::class)->reveal(),
            $parser->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal()
        );

        $folder1 = $this->prophesize(SplFileInfo::class);
        $folder2 = $this->prophesize(SplFileInfo::class);

        $finder = $this->prophesize(Finder::class);
        $finder->getIterator()->willReturn(new \ArrayObject([$folder1->reveal(), $folder2->reveal()]));

        $manual1 = $this->prophesize(Manual::class)->reveal();
        $manual2 = $this->prophesize(Manual::class)->reveal();

        $parser->findFolders('_docsFolder')->willReturn($finder->reveal())->shouldBeCalledTimes(1);
        $parser->createFromFolder('_docsFolder', Argument::is($folder1->reveal()))
               ->willReturn($manual1)
               ->shouldBeCalledTimes(1);
        $parser->createFromFolder('_docsFolder', Argument::is($folder2->reveal()))
               ->willReturn($manual2)
               ->shouldBeCalledTimes(1);

        $manuals = $subject->findManuals('_docsFolder');

        self::assertCount(2, $manuals);
        self::assertSame($manual1, $manuals[0]);
        self::assertSame($manual2, $manuals[1]);
    }

    /**
     * @test
     */
    public function findsManual()
    {
        $parser = $this->prophesize(ParseDocumentationHTMLService::class);
        $subject = new ImportManualHTMLService(
            $this->prophesize(ElasticRepository::class)->reveal(),
            $parser->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal()
        );

        $folder = $this->prophesize(SplFileInfo::class)->reveal();

        $finder = $this->prophesize(Finder::class);
        $finder->getIterator()->willReturn(new \ArrayObject([$folder]));

        $manual = $this->prophesize(Manual::class)->reveal();

        $parser->createFromFolder(
            '_docsFolder',
            Argument::which('__toString', '_docsFolder/c/typo3/cms-core/master/en-us')
        )->willReturn($manual);

        $returnedManual = $subject->findManual('_docsFolder', 'c/typo3/cms-core/master/en-us');

        self::assertInstanceOf(Manual::class, $manual);
        self::assertSame($manual, $returnedManual);
    }

    /**
     * @test
     */
    public function allowsToDeleteManual()
    {
        $manual = $this->prophesize(Manual::class)->reveal();
        $repo = $this->prophesize(ElasticRepository::class);

        $subject = new ImportManualHTMLService(
            $repo->reveal(),
            $this->prophesize(ParseDocumentationHTMLService::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal()
        );

        $repo->deleteByManual($manual)->shouldBeCalledTimes(1);

        $subject->deleteManual($manual);
    }

    /**
     * @test
     */
    public function allowsImportOfManual()
    {
        $manual = $this->prophesize(Manual::class);
        $manual->getTitle()->willReturn('typo3/cms-core');
        $manual->getType()->willReturn('c');
        $manual->getVersion()->willReturn('master');
        $manual->getLanguage()->willReturn('en-us');
        $manual->getSlug()->willReturn('slug');
        $manualRevealed = $manual->reveal();

        $repo = $this->prophesize(ElasticRepository::class);
        $parser = $this->prophesize(ParseDocumentationHTMLService::class);
        $finder = $this->prophesize(Finder::class);
        $file = $this->prophesize(SplFileInfo::class);
        $file->getRelativePathname()->willReturn('c/typo3/cms-core/master/en-us');
        $fileRevealed = $file->reveal();

        $section1 = [
            'fragment' => 'features-and-basic-concept',
            'snippet_title' => 'Features and Basic Concept',
            'snippet_content' => 'The main goal for this blog extension was to use TYPO3s core concepts and elements to provide a full-blown blog that users of TYPO3 can instantly understand and use.'
        ];
        $section2 = [
            'fragment' => 'pages-as-blog-entries',
            'snippet_title' => 'Pages as blog entries',
            'snippet_content' => 'Blog entries are simply pages with a special page type blog entry and can be created and edited via the well-known page module. Creating new entries is as simple as dragging a new entry into the page tree.'
        ];

        $parser->getFilesWithSections($manualRevealed)->willReturn($finder->reveal());
        $parser->getSectionsFromFile($fileRevealed)->willReturn([$section1, $section2]);
        $finder->getIterator()->willReturn(new \ArrayObject([$fileRevealed]));

        $subject = new ImportManualHTMLService($repo->reveal(), $parser->reveal(), $this->prophesize(EventDispatcherInterface::class)->reveal());

        $repo->addOrUpdateDocument([
            'manual_title' => 'typo3/cms-core',
            'manual_type' => 'c',
            'manual_version' => 'master',
            'manual_language' => 'en-us',
            'manual_slug' => 'slug',
            'relative_url' => 'c/typo3/cms-core/master/en-us',
            'fragment' => 'features-and-basic-concept',
            'snippet_title' => 'Features and Basic Concept',
            'snippet_content' => 'The main goal for this blog extension was to use TYPO3s core concepts and elements to provide a full-blown blog that users of TYPO3 can instantly understand and use.'
        ])->shouldBeCalledTimes(1);
        $repo->addOrUpdateDocument([
            'manual_title' => 'typo3/cms-core',
            'manual_type' => 'c',
            'manual_version' => 'master',
            'manual_language' => 'en-us',
            'manual_slug' => 'slug',
            'relative_url' => 'c/typo3/cms-core/master/en-us',
            'fragment' => 'pages-as-blog-entries',
            'snippet_title' => 'Pages as blog entries',
            'snippet_content' => 'Blog entries are simply pages with a special page type blog entry and can be created and edited via the well-known page module. Creating new entries is as simple as dragging a new entry into the page tree.'
        ])->shouldBeCalledTimes(1);

        $subject->importManual($manualRevealed);
    }
}
