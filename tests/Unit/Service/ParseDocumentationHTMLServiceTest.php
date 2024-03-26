<?php

namespace App\Tests\Unit\Service;

use App\Service\ParseDocumentationHTMLService;
use Exception;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Finder\SplFileInfo;

class ParseDocumentationHTMLServiceTest extends TestCase
{
    use ProphecyTrait;

    public function testMetaTagExistsByNameOnly(): void
    {
        $fileContent = '<meta name="x-typo3-indexer" content="index">';
        $file = $this->prophesize(SplFileInfo::class);

        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();
        $result = $subject->checkIfMetaTagExistsInFile($file->reveal(), 'x-typo3-indexer');

        $this->assertTrue($result);
    }

    public function testMetaTagExistsByNameAndContent(): void
    {
        $fileContent = '<meta name="x-typo3-indexer" content="noindex">';
        $file = $this->prophesize(SplFileInfo::class);

        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();
        $result = $subject->checkIfMetaTagExistsInFile($file->reveal(), 'x-typo3-indexer', 'noindex');

        $this->assertTrue($result);
    }

    public function testMetaTagDoesNotExistByName(): void
    {
        $fileContent = '<meta name="x-typo3-indexer" content="index">';
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);

        $subject = new ParseDocumentationHTMLService();
        $result = $subject->checkIfMetaTagExistsInFile($file->reveal(), 'x-typo3-version');

        $this->assertFalse($result);
    }

    public function testMetaTagDoesNotExistByContent(): void
    {
        $fileContent = '<meta name="x-typo3-indexer" content="index">';
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);

        $subject = new ParseDocumentationHTMLService();
        $result = $subject->checkIfMetaTagExistsInFile($file->reveal(), 'x-typo3-indexer', 'noindex');

        $this->assertFalse($result);
    }

    /**
     * @test
     * @throws Exception
     */
    public function returnsMultipleUniqueHeadlinesForLegacyStructure(): void
    {
        $fileContent = $this->getFixtureFileContents('ReturnsContentElements/LegacyStructure', 'MultipleUniqueHeadlines');
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();

        $receivedSection = $subject->getSectionsFromFile($file->reveal());

        self::assertEquals([
            [
                'fragment' => 'headline-1-section',
                'snippet_title' => 'Headline 1',
                'snippet_content' => '',
            ],
            [
                'fragment' => 'headline-2-section',
                'snippet_title' => 'Headline 2',
                'snippet_content' => '',
            ],
            [
                'fragment' => 'headline-3-section',
                'snippet_title' => 'Headline 3',
                'snippet_content' => '',
            ],
            [
                'fragment' => 'headline-4-section',
                'snippet_title' => 'Headline 4',
                'snippet_content' => '',
            ],
            [
                'fragment' => 'headline-5-section',
                'snippet_title' => 'Headline 5',
                'snippet_content' => '',
            ],
            [
                'fragment' => 'headline-6-section',
                'snippet_title' => 'Headline 6',
                'snippet_content' => '',
            ],
        ], $receivedSection);
    }

    /**
     * @test
     * @throws Exception
     */
    public function treatsAllHeadlinesInsideSectionAsSnippetContentExceptTheH1ForLegacyStructure(): void
    {
        $fileContent = $this->getFixtureFileContents('ReturnsContentElements/LegacyStructure', 'SingleSectionWithMultipleHeadlines');
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();

        $receivedSection = $subject->getSectionsFromFile($file->reveal());

        self::assertEquals([
            [
                'fragment' => 'single-section',
                'snippet_title' => 'Headline 1',
                'snippet_content' => 'Headline 2 Headline 3 Headline 4 Headline 5 Headline 6',
            ],
        ], $receivedSection);
    }

    /**
     * @test
     * @throws Exception
     */
    public function sectionsWithoutHeadlineAreOmittedForLegacyStructure(): void
    {
        $fileContent = $this->getFixtureFileContents('ReturnsContentElements/LegacyStructure', 'SectionsWithoutHeadlines');
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();

        $receivedSection = $subject->getSectionsFromFile($file->reveal());

        self::assertEquals([
            [
                'fragment' => 'section-3',
                'snippet_title' => 'Headline 3',
                'snippet_content' => 'Section 3 content',
            ],
            [
                'fragment' => 'section-4',
                'snippet_title' => 'Headline 4',
                'snippet_content' => 'Section 4 content',
            ],
        ], $receivedSection);
    }

    /**
     * @test
     * @throws Exception
     */
    public function sectionsWithHeadlinesNotAsFirstContentElementForLegacyStructure(): void
    {
        $fileContent = $this->getFixtureFileContents('ReturnsContentElements/LegacyStructure', 'SectionsWithHeadlinesNotAsFirstContentElement');
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();

        $receivedSection = $subject->getSectionsFromFile($file->reveal());

        self::assertEquals([
            [
                'fragment' => 'section-1',
                'snippet_title' => 'Section 1',
                'snippet_content' => 'Section 1 content',
            ],
            [
                'fragment' => 'section-2',
                'snippet_title' => 'Section 2',
                'snippet_content' => 'Section 2 content part 1 Section 2 content part 2',
            ],
            [
                'fragment' => 'section-3',
                'snippet_title' => 'Section 3',
                'snippet_content' => 'Section 3 content part 1 Section 3 content part 2',
            ],
            [
                'fragment' => 'section-4',
                'snippet_title' => 'Section 4',
                'snippet_content' => 'Section 4 content part 1 Section 4 content part 2 Section 4 content part 3 Headline 5',
            ],
        ], $receivedSection);
    }

    /**
     * @test
     * @throws Exception
     */
    public function returnsMultipleUniqueHeadlines(): void
    {
        $fileContent = $this->getFixtureFileContents('ReturnsContentElements', 'MultipleUniqueHeadlines');
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();

        $receivedSection = $subject->getSectionsFromFile($file->reveal());

        self::assertEquals([
            [
                'fragment' => 'headline-1-section',
                'snippet_title' => 'Headline 1',
                'snippet_content' => '',
            ],
            [
                'fragment' => 'headline-2-section',
                'snippet_title' => 'Headline 2',
                'snippet_content' => '',
            ],
            [
                'fragment' => 'headline-3-section',
                'snippet_title' => 'Headline 3',
                'snippet_content' => '',
            ],
            [
                'fragment' => 'headline-4-section',
                'snippet_title' => 'Headline 4',
                'snippet_content' => '',
            ],
            [
                'fragment' => 'headline-5-section',
                'snippet_title' => 'Headline 5',
                'snippet_content' => '',
            ],
            [
                'fragment' => 'headline-6-section',
                'snippet_title' => 'Headline 6',
                'snippet_content' => '',
            ],
        ], $receivedSection);
    }

    /**
     * @test
     * @throws Exception
     */
    public function treatsAllHeadlinesInsideSectionAsSnippetContentExceptTheH1(): void
    {
        $fileContent = $this->getFixtureFileContents('ReturnsContentElements', 'SingleSectionWithMultipleHeadlines');
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();

        $receivedSection = $subject->getSectionsFromFile($file->reveal());

        self::assertEquals([
            [
                'fragment' => 'single-section',
                'snippet_title' => 'Headline 1',
                'snippet_content' => 'Headline 2 Headline 3 Headline 4 Headline 5 Headline 6',
            ],
        ], $receivedSection);
    }

    /**
     * @test
     * @throws Exception
     */
    public function sectionsWithoutHeadlineAreOmitted(): void
    {
        $fileContent = $this->getFixtureFileContents('ReturnsContentElements', 'SectionsWithoutHeadlines');
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();

        $receivedSection = $subject->getSectionsFromFile($file->reveal());

        self::assertEquals([
            [
                'fragment' => 'section-3',
                'snippet_title' => 'Headline 3',
                'snippet_content' => 'Section 3 content',
            ],
            [
                'fragment' => 'section-4',
                'snippet_title' => 'Headline 4',
                'snippet_content' => 'Section 4 content',
            ],
        ], $receivedSection);
    }

    /**
     * @test
     * @throws Exception
     */
    public function sectionsWithHeadlinesNotAsFirstContentElement(): void
    {
        $fileContent = $this->getFixtureFileContents('ReturnsContentElements', 'SectionsWithHeadlinesNotAsFirstContentElement');
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();

        $receivedSection = $subject->getSectionsFromFile($file->reveal());

        self::assertEquals([
            [
                'fragment' => 'section-1',
                'snippet_title' => 'Section 1',
                'snippet_content' => 'Section 1 content',
            ],
            [
                'fragment' => 'section-2',
                'snippet_title' => 'Section 2',
                'snippet_content' => 'Section 2 content part 1 Section 2 content part 2',
            ],
            [
                'fragment' => 'section-3',
                'snippet_title' => 'Section 3',
                'snippet_content' => 'Section 3 content part 1 Section 3 content part 2',
            ],
            [
                'fragment' => 'section-4',
                'snippet_title' => 'Section 4',
                'snippet_content' => 'Section 4 content part 1 Section 4 content part 2 Section 4 content part 3 Headline 5',
            ],
        ], $receivedSection);
    }

    /**
     * @test
     * @dataProvider documentationDataProviderForLegacyStructure
     * @throws Exception
     */
    public function returnsSectionsFromFileForLegacyStructure(array $expectedResult): void
    {
        $fileContent = $this->getFixtureFileContents('ReturnsSectionsFromFile/LegacyStructure', ucfirst($this->dataName()));
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();
        $receivedSection = $subject->getSectionsFromFile($file->reveal());

        foreach ($expectedResult as $sectionIndex => $expectedSection) {
            self::assertSame($receivedSection[$sectionIndex], $expectedSection, 'Section with index ' . $sectionIndex . ' did not match.');
        }

        self::assertCount(count($expectedResult), $receivedSection, 'Did not receive expected number of sections.');
    }

    public function documentationDataProviderForLegacyStructure(): array
    {
        return [
            'simpleMarkup' => [
                'expectedResult' => [
                    [
                        'fragment' => 'first-section',
                        'snippet_title' => 'Headline 1',
                        'snippet_content' => 'Content 1',
                    ]
                ]
            ],
            'multiByteMarkupWithFullLayout' => [
                'expectedResult' => [
                    [
                        'fragment' => 'important-100658-drop-use-tsconfig-options-createfoldersineb-and-foldertree-hidecreatefolder',
                        'snippet_title' => 'Important: #100658 - Drop use TSconfig options createFoldersInEB and folderTree.hideCreateFolder',
                        'snippet_content' => 'See forge#100658',
                    ],
                    [
                        'fragment' => 'description',
                        'snippet_title' => 'Description',
                        'snippet_content' => 'The user TSconfig options createFoldersInEB and folderTree.hideCreateFolder were used in the past to control the existence of the "Create folder" form in Element Browser instances. With the migration of the "Create folder" view into a separate modal used in EXT:filelist, which is based on Element Browser as well, those options became useless and are therefore dropped.',
                    ],
                ],
            ],
            'markupWithSubSections' => [
                'expected' => [
                    [
                        'fragment' => 'deprecation-88839-cli-lowlevel-request-handlers',
                        'snippet_title' => 'Deprecation: #88839 - CLI lowlevel request handlers',
                        'snippet_content' => 'See Issue #88839'
                    ],
                    [
                        'fragment' => 'description',
                        'snippet_title' => 'Description',
                        'snippet_content' => implode("\n", [
                            'The interface \TYPO3\CMS\Core\Console\RequestHandlerInterface',
                            'and the class \TYPO3\CMS\Core\Console\CommandRequestHandler have been introduced in TYPO3 v7 to streamline',
                            'various entry points for CLI-related functionality. Back then, there were Extbase command requests and',
                            'CommandLineController entry points.',
                            'With TYPO3 v10, the only way to handle CLI commands is via the \TYPO3\CMS\Core\Console\CommandApplication class which is',
                            'a wrapper around Symfony Console. All logic is now located in the Application, and thus, the interface and',
                            'the class have been marked as deprecated.',
                        ]),
                    ],
                    [
                        'fragment' => 'impact',
                        'snippet_title' => 'Impact',
                        'snippet_content' => implode("\n", [
                            'When instantiating the CLI \TYPO3\CMS\Core\Console\RequestHandler class,',
                            'a PHP E_USER_DEPRECATED error will be triggered.',
                        ]),
                    ],
                    [
                        'fragment' => 'affected-installations',
                        'snippet_title' => 'Affected Installations',
                        'snippet_content' => implode("\n", [
                            'Any TYPO3 installation having custom CLI request handlers wrapped via the interface or extending the',
                            'CLI request handler class.',
                        ]),
                    ],
                    [
                        'fragment' => 'migration',
                        'snippet_title' => 'Migration',
                        'snippet_content' => 'Switch to a Symfony Command or provide a custom CLI entry point.',
                    ],
                ]
            ],
            'markupWithSubSectionsSmall' => [
                'expected' => [
                    [
                        'fragment' => 'features-and-basic-concept',
                        'snippet_title' => 'Features and Basic Concept',
                        'snippet_content' => 'The main goal for this blog extension was to use TYPO3s core concepts and elements to provide a full-blown blog that users of TYPO3 can instantly understand and use.'
                    ],
                    [
                        'fragment' => 'pages-as-blog-entries',
                        'snippet_title' => 'Pages as blog entries',
                        'snippet_content' => 'Blog entries are simply pages with a special page type blog entry and can be created and edited via the well-known page module. Creating new entries is as simple as dragging a new entry into the page tree.'
                    ],
                ]
            ],
            'markupWithCodeExamples' => [
                'expected' => [
                    [
                        'fragment' => 'rendering-page-trees',
                        'snippet_title' => 'Rendering Page Trees',
                        'snippet_content' => "In your backend modules you might like to show information or perform
processing for a part of the page tree. There is a whole family of
libraries in the core for making trees from records, static page trees
or page trees that can be browsed (open/close nodes).
This simple example demonstrates how to produce the HTML for a static
page tree. The result looks like: A static page tree in TYPO3 Backend The tree object itself is prepared this way (taken from
EXT:examples/Classes/Controller/DefaultController.php): At the top of the code we define the starting page and get the corresponding
page record using \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord().
Next we create an instance of \TYPO3\CMS\Backend\Tree\View\PageTreeView,
which we use for generating the tree. Notice how the BE_USER object is
called to get a SQL where clause that will ensure that only pages
that are accessible for the user will be shown in the tree!
As a next step we manually add the starting page to the page tree.
This is not done automatically because it is not always a desirable
behavior. Note the use of \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconForRecord()
to fetch the right icon for the page.
Finally we get the tree to prepare itself, up to a certain depth.
Internally this will - in particular - generate a HTML part containing
the tree elements and the page icon itself.
The rendered page tree is stored in a data array inside of the tree
object. We need to traverse the tree data to create the tree in HTML.
This gives us the chance to organize the tree in a table for instance.
It is this part that we pass on to the view. The result is rendered with a very simple Fluid template: We do a simple loop on the tree array of pages and display the relevant
elements."
                    ],
                ]
            ],
        ];
    }

    /**
     * @test
     * @dataProvider documentationDataProvider
     * @throws Exception
     */
    public function returnsSectionsFromFile(array $expectedResult): void
    {
        $fileContent = $this->getFixtureFileContents('ReturnsSectionsFromFile', ucfirst($this->dataName()));
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();
        $receivedSection = $subject->getSectionsFromFile($file->reveal());

        foreach ($expectedResult as $sectionIndex => $expectedSection) {
            self::assertSame($receivedSection[$sectionIndex], $expectedSection, 'Section with index ' . $sectionIndex . ' did not match.');
        }

        self::assertCount(count($expectedResult), $receivedSection, 'Did not receive expected number of sections.');
    }

    public function documentationDataProvider(): array
    {
        return [
            'simpleMarkup' => [
                'expectedResult' => [
                    [
                        'fragment' => 'first-section',
                        'snippet_title' => 'Headline 1',
                        'snippet_content' => 'Content 1',
                    ]
                ]
            ],
            'multiByteMarkupWithFullLayout' => [
                'expectedResult' => [
                    [
                        'fragment' => 'important-100658-drop-use-tsconfig-options-createfoldersineb-and-foldertree-hidecreatefolder',
                        'snippet_title' => 'Important: #100658 - Drop use TSconfig options createFoldersInEB and folderTree.hideCreateFolder',
                        'snippet_content' => 'See forge#100658',
                    ],
                    [
                        'fragment' => 'description',
                        'snippet_title' => 'Description',
                        'snippet_content' => 'The user TSconfig options createFoldersInEB and folderTree.hideCreateFolder were used in the past to control the existence of the "Create folder" form in Element Browser instances. With the migration of the "Create folder" view into a separate modal used in EXT:filelist, which is based on Element Browser as well, those options became useless and are therefore dropped.',
                    ],
                ],
            ],
            'markupWithSubSections' => [
                'expected' => [
                    [
                        'fragment' => 'deprecation-88839-cli-lowlevel-request-handlers',
                        'snippet_title' => 'Deprecation: #88839 - CLI lowlevel request handlers',
                        'snippet_content' => 'See Issue #88839'
                    ],
                    [
                        'fragment' => 'description',
                        'snippet_title' => 'Description',
                        'snippet_content' => implode("\n", [
                            'The interface \TYPO3\CMS\Core\Console\RequestHandlerInterface',
                            'and the class \TYPO3\CMS\Core\Console\CommandRequestHandler have been introduced in TYPO3 v7 to streamline',
                            'various entry points for CLI-related functionality. Back then, there were Extbase command requests and',
                            'CommandLineController entry points.',
                            'With TYPO3 v10, the only way to handle CLI commands is via the \TYPO3\CMS\Core\Console\CommandApplication class which is',
                            'a wrapper around Symfony Console. All logic is now located in the Application, and thus, the interface and',
                            'the class have been marked as deprecated.',
                        ]),
                    ],
                    [
                        'fragment' => 'impact',
                        'snippet_title' => 'Impact',
                        'snippet_content' => implode("\n", [
                            'When instantiating the CLI \TYPO3\CMS\Core\Console\RequestHandler class,',
                            'a PHP E_USER_DEPRECATED error will be triggered.',
                        ]),
                    ],
                    [
                        'fragment' => 'affected-installations',
                        'snippet_title' => 'Affected Installations',
                        'snippet_content' => implode("\n", [
                            'Any TYPO3 installation having custom CLI request handlers wrapped via the interface or extending the',
                            'CLI request handler class.',
                        ]),
                    ],
                    [
                        'fragment' => 'migration',
                        'snippet_title' => 'Migration',
                        'snippet_content' => 'Switch to a Symfony Command or provide a custom CLI entry point.',
                    ],
                ]
            ],
            'markupWithSubSectionsSmall' => [
                'expected' => [
                    [
                        'fragment' => 'features-and-basic-concept',
                        'snippet_title' => 'Features and Basic Concept',
                        'snippet_content' => 'The main goal for this blog extension was to use TYPO3s core concepts and elements to provide a full-blown blog that users of TYPO3 can instantly understand and use.'
                    ],
                    [
                        'fragment' => 'pages-as-blog-entries',
                        'snippet_title' => 'Pages as blog entries',
                        'snippet_content' => 'Blog entries are simply pages with a special page type blog entry and can be created and edited via the well-known page module. Creating new entries is as simple as dragging a new entry into the page tree.'
                    ],
                ]
            ],
            'markupWithCodeExamples' => [
                'expected' => [
                    [
                        'fragment' => 'rendering-page-trees',
                        'snippet_title' => 'Rendering Page Trees',
                        'snippet_content' => "In your backend modules you might like to show information or perform
processing for a part of the page tree. There is a whole family of
libraries in the core for making trees from records, static page trees
or page trees that can be browsed (open/close nodes).
This simple example demonstrates how to produce the HTML for a static
page tree. The result looks like: A static page tree in TYPO3 Backend The tree object itself is prepared this way (taken from
EXT:examples/Classes/Controller/DefaultController.php): At the top of the code we define the starting page and get the corresponding
page record using \TYPO3\CMS\Backend\Utility\BackendUtility::getRecord(). Next we create an instance of \TYPO3\CMS\Backend\Tree\View\PageTreeView,
which we use for generating the tree. Notice how the BE_USER object is
called to get a SQL where clause that will ensure that only pages
that are accessible for the user will be shown in the tree! As a next step we manually add the starting page to the page tree.
This is not done automatically because it is not always a desirable
behavior. Note the use of \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIconForRecord()
to fetch the right icon for the page. Finally we get the tree to prepare itself, up to a certain depth.
Internally this will - in particular - generate a HTML part containing
the tree elements and the page icon itself. The rendered page tree is stored in a data array inside of the tree
object. We need to traverse the tree data to create the tree in HTML.
This gives us the chance to organize the tree in a table for instance.
It is this part that we pass on to the view. The result is rendered with a very simple Fluid template: We do a simple loop on the tree array of pages and display the relevant
elements."
                    ],
                ]
            ],
        ];
    }

    /**
     * @throws Exception
     */
    private function getFixtureFileContents(string $folder, string $fileName): string
    {
        $fixtureFile = implode(DIRECTORY_SEPARATOR, [
            __DIR__,
            'Fixtures',
            'ParseDocumentationHTMLServiceTest',
            $folder,
            ucfirst($fileName) . '.html',
        ]);

        if (!file_exists($fixtureFile)) {
            throw new Exception("Fixture file not found: " . $fixtureFile);
        }

        return file_get_contents($fixtureFile);
    }
}
