<?php

namespace App\Tests\Unit\Service;

use App\Config\ManualType;
use App\Dto\Manual;
use App\Event\ImportManual\ManualAdvance;
use App\Event\ImportManual\ManualFinish;
use App\Event\ImportManual\ManualStart;
use App\Repository\ElasticRepository;
use App\Service\ImportManualHTMLService;
use App\Service\ParseDocumentationHTMLService;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ImportManualHTMLServiceTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function allowsToDeleteManual(): void
    {
        $manual = $this->prophesize(Manual::class)->reveal();
        $repo = $this->prophesize(ElasticRepository::class);

        $subject = new ImportManualHTMLService(
            $repo->reveal(),
            $this->prophesize(ParseDocumentationHTMLService::class)->reveal(),
            $this->prophesize(EventDispatcherInterface::class)->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal(),
        );

        $repo->deleteByManual($manual)->shouldBeCalledTimes(1);

        $subject->deleteManual($manual);
    }

    /**
     * @test
     */
    public function allowsImportOfManual(): void
    {
        $finder = $this->prophesize(Finder::class);

        $vendor = 'typo3';
        $extensionName = 'cms-core';
        $package = implode('/', [$vendor, $extensionName]);

        $manual = $this->prophesize(Manual::class);
        $manual->getTitle()->willReturn('typo3/cms-core');
        $manual->getType()->willReturn('c');
        $manual->getVersion()->willReturn('main');
        $manual->getLanguage()->willReturn('en-us');
        $manual->isCore()->willReturn(true);
        $manual->isLastVersions()->willReturn(true);
        $manual->getVendor()->willReturn($vendor);
        $manual->getName()->willReturn($extensionName);
        $manual->getSlug()->willReturn('slug');
        $manual->getFilesWithSections()->willReturn($finder->reveal());
        $manual->getKeywords()->willReturn(['typo3', 'cms', 'core']);
        $manualRevealed = $manual->reveal();

        $repo = $this->prophesize(ElasticRepository::class);
        $parser = $this->prophesize(ParseDocumentationHTMLService::class);
        $logger = $this->prophesize(LoggerInterface::class);

        $file = $this->prophesize(SplFileInfo::class);
        $file->getRelativePathname()->willReturn('c/typo3/cms-core/main/en-us');
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

        $parser->checkIfMetaTagExistsInFile($fileRevealed, 'x-typo3-indexer', 'noindex')->willReturn(false);
        $parser->getSectionsFromFile($fileRevealed)->willReturn([$section1, $section2]);
        $finder->getIterator()->willReturn(new \ArrayIterator([$fileRevealed]));

        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher
            ->dispatch(Argument::type(ManualStart::class), ManualStart::NAME)
            ->shouldBeCalled()
            ->willReturn($this->prophesize(ManualStart::class)->reveal());
        $eventDispatcher
            ->dispatch(Argument::type(ManualAdvance::class), ManualAdvance::NAME)
            ->shouldBeCalled()
            ->willReturn($this->prophesize(ManualAdvance::class)->reveal());
        $eventDispatcher
            ->dispatch(Argument::type(ManualFinish::class), ManualFinish::NAME)
            ->shouldBeCalled()
            ->willReturn($this->prophesize(ManualFinish::class)->reveal());

        $subject = new ImportManualHTMLService($repo->reveal(), $parser->reveal(), $eventDispatcher->reveal(), $logger->reveal());

        $repo->addOrUpdateDocument([
            'fragment' => 'features-and-basic-concept',
            'snippet_title' => 'Features and Basic Concept',
            'snippet_content' => 'The main goal for this blog extension was to use TYPO3s core concepts and elements to provide a full-blown blog that users of TYPO3 can instantly understand and use.',
            'manual_title' => $package,
            'manual_vendor' => $vendor,
            'manual_extension' => $extensionName,
            'manual_package' => $package,
            'manual_type' => 'c',
            'manual_version' => 'main',
            'manual_language' => 'en-us',
            'manual_slug' => 'slug',
            'manual_keywords' => ['typo3', 'cms', 'core'],
            'relative_url' => 'c/typo3/cms-core/main/en-us',
            'content_hash' => '718ab540920b06f925f6ef7a34d6a5c7',
            'is_core' => true,
            'is_last_versions' => true,
        ])->shouldBeCalledTimes(1);
        $repo->addOrUpdateDocument([
            'fragment' => 'pages-as-blog-entries',
            'snippet_title' => 'Pages as blog entries',
            'snippet_content' => 'Blog entries are simply pages with a special page type blog entry and can be created and edited via the well-known page module. Creating new entries is as simple as dragging a new entry into the page tree.',
            'manual_title' => $package,
            'manual_vendor' => $vendor,
            'manual_extension' => $extensionName,
            'manual_package' => $package,
            'manual_type' => 'c',
            'manual_version' => 'main',
            'manual_language' => 'en-us',
            'manual_slug' => 'slug',
            'manual_keywords' => ['typo3', 'cms', 'core'],
            'relative_url' => 'c/typo3/cms-core/main/en-us',
            'content_hash' => 'a248b5d0798e30e7c9389b81b499c5d9',
            'is_core' => true,
            'is_last_versions' => true,
        ])->shouldBeCalledTimes(1);

        $subject->importManual($manualRevealed);
    }

    /**
     * @test
     */
    public function doNotImportManualWithNoIndexMetaTag(): void
    {
        $file = $this->prophesize(SplFileInfo::class);
        $file->getRelativePathname()->willReturn('c/typo3/cms-core/main/en-us');
        $fileRevealed = $file->reveal();

        $finder = $this->prophesize(Finder::class);
        $finder->getIterator()->willReturn(new \ArrayIterator([$fileRevealed]));

        $manual = $this->prophesize(Manual::class);
        $manual->getFilesWithSections()->willReturn($finder->reveal());
        $manualRevealed = $manual->reveal();

        $repo = $this->prophesize(ElasticRepository::class);
        $parser = $this->prophesize(ParseDocumentationHTMLService::class);
        $logger = $this->prophesize(LoggerInterface::class);

        $parser->checkIfMetaTagExistsInFile($fileRevealed, 'x-typo3-indexer', 'noindex')->willReturn(true);

        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher
            ->dispatch(Argument::type(ManualStart::class), ManualStart::NAME)
            ->shouldBeCalledTimes(1)
            ->willReturn($this->prophesize(ManualStart::class)->reveal());
        $eventDispatcher
            ->dispatch(Argument::type(ManualAdvance::class), ManualAdvance::NAME)
            ->shouldNotBeCalled();
        $eventDispatcher
            ->dispatch(Argument::type(ManualFinish::class), ManualFinish::NAME)
            ->shouldBeCalledTimes(1)
            ->willReturn($this->prophesize(ManualFinish::class)->reveal());

        $subject = new ImportManualHTMLService($repo->reveal(), $parser->reveal(), $eventDispatcher->reveal(), $logger->reveal());
        $subject->importManual($manualRevealed);
    }

    /**
     * @test
     */
    public function changelogFileIsTreatedAsASingleSnippet(): void
    {
        $finder = $this->prophesize(Finder::class);

        $vendor = 'typo3';
        $extensionName = 'cms-core';
        $package = implode('/', [$vendor, $extensionName]);

        $manual = $this->prophesize(Manual::class);
        $manual->getTitle()->willReturn('typo3/cms-core');
        $manual->getType()->willReturn(ManualType::CoreChangelog->value);
        $manual->getVersion()->willReturn('main');
        $manual->getLanguage()->willReturn('en-us');
        $manual->getSlug()->willReturn('slug');
        $manual->isCore()->willReturn(true);
        $manual->isLastVersions()->willReturn(true);
        $manual->getVendor()->willReturn($vendor);
        $manual->getName()->willReturn($extensionName);
        $manual->getFilesWithSections()->willReturn($finder->reveal());
        $manual->getKeywords()->willReturn(['typo3', 'cms', 'core']);
        $manualRevealed = $manual->reveal();

        $repo = $this->prophesize(ElasticRepository::class);
        $parser = $this->prophesize(ParseDocumentationHTMLService::class);
        $logger = $this->prophesize(LoggerInterface::class);

        $file = $this->prophesize(SplFileInfo::class);
        $file->getRelativePathname()->willReturn('c/typo3/cms-core/main/en-us');
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

        $parser->checkIfMetaTagExistsInFile($fileRevealed, 'x-typo3-indexer', 'noindex')->willReturn(false);
        $parser->getFileContentAsSingleSection($fileRevealed)->willReturn([
            'fragment' => $section1['fragment'],
            'snippet_title' => $section1['snippet_title'],
            'snippet_content' => $section1['snippet_content'] . "\n" . $section2['snippet_title'] . "\n" . $section2['snippet_content'],
        ]);
        $finder->getIterator()->willReturn(new \ArrayIterator([$fileRevealed]));

        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher
            ->dispatch(Argument::type(ManualStart::class), ManualStart::NAME)
            ->shouldBeCalledTimes(1)
            ->willReturn($this->prophesize(ManualStart::class)->reveal());
        $eventDispatcher
            ->dispatch(Argument::type(ManualAdvance::class), ManualAdvance::NAME)
            ->shouldBeCalledTimes(1)
            ->willReturn($this->prophesize(ManualAdvance::class)->reveal());
        $eventDispatcher
            ->dispatch(Argument::type(ManualFinish::class), ManualFinish::NAME)
            ->shouldBeCalledTimes(1)
            ->willReturn($this->prophesize(ManualFinish::class)->reveal());

        $subject = new ImportManualHTMLService($repo->reveal(), $parser->reveal(), $eventDispatcher->reveal(), $logger->reveal());

        $repo->addOrUpdateDocument([
            'fragment' => 'features-and-basic-concept',
            'snippet_title' => 'Features and Basic Concept',
            'manual_vendor' => $vendor,
            'manual_extension' => $extensionName,
            'manual_package' => $package,
            'snippet_content' => $section1['snippet_content'] . "\n" . $section2['snippet_title'] . "\n" . $section2['snippet_content'],
            'manual_title' => $package,
            'manual_type' => ManualType::CoreChangelog->value,
            'manual_version' => 'main',
            'manual_language' => 'en-us',
            'manual_slug' => 'slug',
            'manual_keywords' => ['typo3', 'cms', 'core'],
            'relative_url' => 'c/typo3/cms-core/main/en-us',
            'content_hash' => 'c03832e65c91c86548ca248379c885d6',
            'is_core' => true,
            'is_last_versions' => true,
        ])->shouldBeCalledTimes(1);

        $subject->importManual($manualRevealed);
    }
}
