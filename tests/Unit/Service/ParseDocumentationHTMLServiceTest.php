<?php

namespace App\Tests\Unit\Service;

use App\Dto\Manual;
use App\Service\ParseDocumentationHTMLService;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;

class ParseDocumentationHTMLServiceTest extends TestCase
{
    /**
     * @test
     */
    public function returnsManualsFromFolder()
    {
        $subject = new ParseDocumentationHTMLService();

        $rootDir = vfsStream::setup('_docsFolder', null, [
            'c' => [
                'typo3' => [
                    'cms-core' => [
                        'master' => [
                            'en-us' => [
                            ],
                        ],
                    ],
                    'cms-form' => [
                        'master' => [
                            'en-us' => [
                            ],
                        ],
                    ],
                ],
            ],
            'm' => [
                'typo3' => [
                    'book-extbasefluid' => [
                        '9.5' => [
                            'en-us' => [
                            ],
                        ],
                        'master' => [
                            'en-us' => [
                            ],
                        ],
                    ],
                    'reference-coreapi' => [
                        '9.5' => [
                            'en-us' => [
                            ],
                        ],
                        'master' => [
                            'en-us' => [
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertCount(6, $subject->findFolders($rootDir->url()));
    }

    /**
     * @test
     */
    public function createsManualFromFolder()
    {
        $subject = new ParseDocumentationHTMLService();

        $folder = $this->prophesize(SplFileInfo::class);
        $folder->__toString()->willReturn('_docsFolder/c/typo3/cms-core/master/en-us/');

        $manual = $subject->createFromFolder('_docsFolder', $folder->reveal());

        self::assertSame('_docsFolder/c/typo3/cms-core/master/en-us/', $manual->getAbsolutePath());
        self::assertSame('typo3/cms-core', $manual->getTitle());
        self::assertSame('c', $manual->getType());
        self::assertSame('master', $manual->getVersion());
        self::assertSame('en-us', $manual->getLanguage());
        self::assertSame('c/typo3/cms-core/master/en-us/', $manual->getSlug());
    }

    /**
     * @test
     */
    public function returnsFilesWithSectionsForManual()
    {
        $filesRoot = implode(DIRECTORY_SEPARATOR, [
            __DIR__,
            'Fixtures',
            'ParseDocumentationHTMLServiceTest',
            'ReturnsFilesWithSectionsForManual',
            'manual',
        ]);
        $manual = $this->prophesize(Manual::class);
        $manual->getAbsolutePath()->willReturn($filesRoot);

        $subject = new ParseDocumentationHTMLService();
        $files = $subject->getFilesWithSections($manual->reveal());

        self::assertCount(3, $files);
        $expectedFiles = [
            $filesRoot . '/index.html',
            $filesRoot . '/another.html',
            $filesRoot . '/additional/index.html',
        ];
        foreach ($files as $file) {
            /* @var $file SplFileInfo */
            self::assertTrue(in_array((string)$file, $expectedFiles), 'Unexpected file: ' . $file);
        }
    }

    /**
     * @test
     * @dataProvider documentationDataProvider
     */
    public function returnsSectionsFromFile(string $relativeFileName, array $expectedResult)
    {
        $fixtureFile = implode(DIRECTORY_SEPARATOR, [
            __DIR__,
            'Fixtures',
            'ParseDocumentationHTMLServiceTest',
            'ReturnsSectionsFromFile',
            ucfirst($this->dataName()) . '.html',
        ]);
        $fileContent = file_get_contents($fixtureFile);
        $file = $this->prophesize(SplFileInfo::class);
        $file->getContents()->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();
        $receivedSection = $subject->getSectionsFromFile($file->reveal());

        foreach ($expectedResult as $sectionIndex => $expectedSection) {
            self::assertSame($receivedSection[$sectionIndex], $expectedSection, 'Section with index ' . $sectionIndex . ' did not match.');
        }

        self::assertCount(count($expectedResult), $receivedSection, 'Did not receive expected number of sections.');
    }

    public function documentationDataProvider()
    {
        return [
            'simpleMarkup' => [
                'relativeFileName' => 'p/docsearch/blog/8.7/en-us',
                'expectedResult' => [
                    [
                        'fragment' => 'first-section',
                        'snippet_title' => 'Headline 1',
                        'snippet_content' => 'Content 1',
                    ]
                ]
            ],
            'multiByteMarkupWithFullLayout' => [
                'relativeFileName' => 'p/docsearch/blog/8.7/en-us',
                'expectedResult' => [
                    [
                        'fragment' => 'feature-69572-page-module-notice-content-is-also-shown-on',
                        'snippet_title' => 'Feature: #69572 - Page module Notice Content is also shown on:',
                        'snippet_content' => 'See Issue #69572',
                    ],
                    [
                        'fragment' => 'description',
                        'snippet_title' => 'Description',
                        'snippet_content' => implode("\n", [
                            'When page content is inherited from a different page via “Show content from page” there is a notice displayed on the page that is pulling in content from a different page.',
                            'As of now, the page whose content is used on other pages gets an info box that indicates which other pages use these contents.',
                        ]),
                    ],
                    [
                        'fragment' => 'impact',
                        'snippet_title' => 'Impact',
                        'snippet_content' => 'On pages that are inherited elsewhere you see a notice which links to the pages where the content is inherited.',
                    ],
                ],
            ],
            'markupWithSubSections' => [
                'relativeFileName' => 'p/docsearch/blog/8.7/en-us',
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
                'relativeFileName' => 'p/docsearch/blog/8.7/en-us',
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
        ];
    }
}
