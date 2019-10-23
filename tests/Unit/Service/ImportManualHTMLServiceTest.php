<?php

namespace App\Tests\Unit\Service;

use App\Dto\Manual;
use App\Repository\ElasticRepository;
use App\Service\ImportManualHTMLService;
use App\Service\ParseDocumentationHTMLService;
use PHPUnit\Framework\TestCase;
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
        $parser = $this->getMockBuilder(ParseDocumentationHTMLService::class)->getMock();
        $subject = new ImportManualHTMLService(
            $this->getMockBuilder(ElasticRepository::class)->getMock(),
            $parser,
            $this->getMockBuilder(EventDispatcherInterface::class)->getMock()
        );

        $folder1 = $this->getMockBuilder(SplFileInfo::class)->disableOriginalConstructor()->getMock();
        $folder2 = $this->getMockBuilder(SplFileInfo::class)->disableOriginalConstructor()->getMock();

        $finder = $this->getMockBuilder(Finder::class)->getMock();
        $finder->expects($this->any())
            ->method('getIterator')
            ->willReturn(new \ArrayObject([$folder1, $folder2]));

        $manual1 = $this->getMockBuilder(Manual::class)->disableOriginalConstructor()->getMock();
        $manual2 = $this->getMockBuilder(Manual::class)->disableOriginalConstructor()->getMock();

        $parser->expects($this->once())
            ->method('findFolders')
            ->willReturn($finder);
        $parser->expects($this->exactly(2))
            ->method('createFromFolder')
            ->will($this->onConsecutiveCalls($manual1, $manual2));

        $manuals = $subject->findManuals('_docsFolder');

        $this->assertCount(2, $manuals);
        $this->assertSame($manual1, $manuals[0]);
        $this->assertSame($manual2, $manuals[1]);
    }

    /**
     * @test
     */
    public function existingManualIsDeletedByMetaDataduringImport()
    {
        $manual = $this->getMockBuilder(Manual::class)->disableOriginalConstructor()->getMock();
        $repoMock = $this->getMockBuilder(ElasticRepository::class)->getMock();
        $parserMock = $this->getMockBuilder(ParseDocumentationHTMLService::class)->getMock();
        $finderMock = $this->getMockBuilder(Finder::class)->getMock();

        $parserMock->expects($this->any())->method('getFilesWithSections')->willReturn($finderMock);
        $finderMock->expects($this->any())->method('getIterator')->willReturn(new \ArrayObject());

        $subject = new ImportManualHTMLService($repoMock, $parserMock, $this->getMockBuilder(EventDispatcherInterface::class)->getMock());

        $repoMock->expects($this->once())
            ->method('deleteByManual')
            ->with($manual);

        $subject->importManual($manual);
    }

    /**
     * @test
     */
    public function sectionsAreSendToElasticsearch()
    {
        $manual = $this->getMockBuilder(Manual::class)->disableOriginalConstructor()->getMock();
        $manual->expects($this->any())->method('getTitle')->willReturn('typo3/cms-core');
        $manual->expects($this->any())->method('getType')->willReturn('c');
        $manual->expects($this->any())->method('getVersion')->willReturn('master');
        $manual->expects($this->any())->method('getLanguage')->willReturn('en-us');
        $manual->expects($this->any())->method('getSlug')->willReturn('slug');
        $repoMock = $this->getMockBuilder(ElasticRepository::class)->getMock();
        $parserMock = $this->getMockBuilder(ParseDocumentationHTMLService::class)->getMock();
        $finderMock = $this->getMockBuilder(Finder::class)->getMock();
        $fileMock = $this->getMockBuilder(SplFileInfo::class)->disableOriginalConstructor()->getMock();
        $fileMock->expects($this->any())->method('getRelativePathname')->willReturn('c/typo3/cms-core/master/en-us');

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

        $parserMock->expects($this->any())->method('getFilesWithSections')->willReturn($finderMock);
        $parserMock->expects($this->any())->method('getSectionsFromFile')->willReturn([$section1, $section2]);
        $finderMock->expects($this->any())->method('getIterator')->willReturn(new \ArrayObject([$fileMock]));

        $subject = new ImportManualHTMLService($repoMock, $parserMock, $this->getMockBuilder(EventDispatcherInterface::class)->getMock());

        $repoMock->expects($this->exactly(2))
            ->method('addOrUpdateDocument')
            ->withConsecutive([
                [
                    'manual_title' => 'typo3/cms-core',
                    'manual_type' => 'c',
                    'manual_version' => 'master',
                    'manual_language' => 'en-us',
                    'manual_slug' => 'slug',
                    'relative_url' => 'c/typo3/cms-core/master/en-us',
                    'fragment' => 'features-and-basic-concept',
                    'snippet_title' => 'Features and Basic Concept',
                    'snippet_content' => 'The main goal for this blog extension was to use TYPO3s core concepts and elements to provide a full-blown blog that users of TYPO3 can instantly understand and use.'
                ]
            ], [
                [
                    'manual_title' => 'typo3/cms-core',
                    'manual_type' => 'c',
                    'manual_version' => 'master',
                    'manual_language' => 'en-us',
                    'manual_slug' => 'slug',
                    'relative_url' => 'c/typo3/cms-core/master/en-us',
                    'fragment' => 'pages-as-blog-entries',
                    'snippet_title' => 'Pages as blog entries',
                    'snippet_content' => 'Blog entries are simply pages with a special page type blog entry and can be created and edited via the well-known page module. Creating new entries is as simple as dragging a new entry into the page tree.'
                ]
            ]);

        $subject->importManual($manual);
    }
}
