<?php

namespace App\Tests\Unit\Service;

use App\Dto\Manual;
use App\Service\ParseDocumentationHTMLService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use org\bovigo\vfs\vfsStream;

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

        $this->assertCount(6, $subject->findFolders($rootDir->url()));
    }

    /**
     * @test
     */
    public function createsManualFromFolder()
    {
        $subject = new ParseDocumentationHTMLService();

        $folder = $this->getMockBuilder(SplFileInfo::class)->disableOriginalConstructor()->getMock();

        $folder->expects($this->any())
            ->method('__toString')
            ->willReturn('_docsFolder/c/typo3/cms-core/master/en-us/');

        $manual = $subject->createFromFolder('_docsFolder', $folder);

        $this->assertSame('_docsFolder/c/typo3/cms-core/master/en-us/', $manual->getAbsolutePath());
        $this->assertSame('typo3/cms-core', $manual->getTitle());
        $this->assertSame('c', $manual->getType());
        $this->assertSame('master', $manual->getVersion());
        $this->assertSame('en-us', $manual->getLanguage());
        $this->assertSame('c/typo3/cms-core/master/en-us/', $manual->getSlug());
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
        $manual = $this->getMockBuilder(Manual::class)->disableOriginalConstructor()->getMock();
        $manual->expects($this->any())->method('getAbsolutePath')->willReturn($filesRoot);

        $subject = new ParseDocumentationHTMLService();
        $files = $subject->getFilesWithSections($manual);

        $this->assertCount(3, $files);
        $expectedFiles = [
            $filesRoot . '/index.html',
            $filesRoot . '/another.html',
            $filesRoot . '/additional/index.html',
        ];
        foreach ($files as $file) {
            /* @var $file SplFileInfo */
            $this->assertTrue(in_array((string) $file, $expectedFiles), 'Unexpected file: ' . $file);
        }
    }

    /**
     * @test
     * @dataProvider documentationDataProvider
     */
    public function returnsSectionsFromFile(string $fileContent, string $relativeFileName, array $expectedResult, bool $incomplete = false)
    {
        if ($incomplete) {
            $this->markTestIncomplete('Need to fix encoding issue');
        }

        $file = $this->getMockBuilder(SplFileInfo::class)->disableOriginalConstructor()->getMock();
        $file->expects($this->any())->method('getContents')->willReturn($fileContent);
        $subject = new ParseDocumentationHTMLService();
        $receivedSection = $subject->getSectionsFromFile($file);

        foreach ($expectedResult as $sectionIndex => $expectedSection) {
            $this->assertSame($receivedSection[$sectionIndex], $expectedSection, 'Section with index ' . $sectionIndex . ' did not match.');
        }

        $this->assertCount(count($expectedResult), $receivedSection, 'Did not receive expected number of sections.');
    }

    public function documentationDataProvider()
    {
        return [
            'simpleMarkup' => [
                'fileContent' => '
                    <html>
                        <div class="toBeIndexed">
                            <div id="first-section" class="section">
                                <h1>Headline 1</h1>
                                <p>Content 1</p>
                            </div>
                        </div>
                    </html>
                ',
                'relativeFileName' => 'p/docsearch/blog/8.7/en-us',
                'expectedResult' => [
                    [
                        'fragment' => 'first-section',
                        'snippet_title' => 'Headline 1',
                        'snippet_content' => 'Content 1',
                    ]
                ]
            ],
            'multi_byte_markup' => [
                'fileContent' => '
                    <html>
                        <div class="toBeIndexed">
                            <div id="first-section" class="section">
                                <h1>Headline 1</h1>
                                <p>This creates a new page titled “The page title” as the first page inside page id 45:</p>
                            </div>
                        </div>
                    </html>
                ',
                'relativeFileName' => 'p/docsearch/blog/8.7/en-us',
                'expectedResult' => [
                    [
                        'fragment' => 'first-section',
                        'snippet_title' => 'Headline 1',
                        'snippet_content' => 'This creates a new page titled “The page title” as the first page inside page id 45:',
                    ]
                ],
                'incomplete' => true,
            ],
            'markupWithSubSections' => [
                'fileContent' => '<div itemprop="articleBody" class="toBeIndexed">

  <div class="section" id="deprecation-88839-cli-lowlevel-request-handlers">
<h1>Deprecation: #88839 - CLI lowlevel request handlers<a class="headerlink" href="#deprecation-88839-cli-lowlevel-request-handlers" title="Permalink to this headline">¶</a></h1>
<p>See <a class="reference external" href="https://forge.typo3.org/issues/88839">Issue #88839</a></p>
<div class="section" id="description">
<h2>Description<a class="headerlink" href="#description" title="Permalink to this headline">¶</a></h2>
<p>The interface <code class="code php docutils literal notranslate"><span class="pre">\TYPO3\CMS\Core\Console\RequestHandlerInterface</span></code>
and the class <code class="code php docutils literal notranslate"><span class="pre">\TYPO3\CMS\Core\Console\CommandRequestHandler</span></code> have been introduced in TYPO3 v7 to streamline
various entry points for CLI-related functionality. Back then, there were Extbase command requests and
<code class="code docutils literal notranslate"><span class="pre">CommandLineController</span></code> entry points.</p>
<p>With TYPO3 v10, the only way to handle CLI commands is via the <code class="code php docutils literal notranslate"><span class="pre">\TYPO3\CMS\Core\Console\CommandApplication</span></code> class which is
a wrapper around Symfony Console. All logic is now located in the Application, and thus, the interface and
the class have been marked as deprecated.</p>
</div>
<div class="section" id="impact">
<h2>Impact<a class="headerlink" href="#impact" title="Permalink to this headline">¶</a></h2>
<p>When instantiating the CLI <code class="code php docutils literal notranslate"><span class="pre">\TYPO3\CMS\Core\Console\RequestHandler</span></code> class,
a PHP <code class="code php docutils literal notranslate"><span class="pre">E_USER_DEPRECATED</span></code> error will be triggered.</p>
</div>
<div class="section" id="affected-installations">
<h2>Affected Installations<a class="headerlink" href="#affected-installations" title="Permalink to this headline">¶</a></h2>
<p>Any TYPO3 installation having custom CLI request handlers wrapped via the interface or extending the
CLI request handler class.</p>
</div>
<div class="section" id="migration">
<h2>Migration<a class="headerlink" href="#migration" title="Permalink to this headline">¶</a></h2>
<p>Switch to a Symfony Command or provide a custom CLI entry point.</p>
<span class="target" id="index-0"></span></div>
</div>


           </div>',
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
                'fileContent' => '<div itemprop="articleBody" class="toBeIndexed">

                        <div class="section" id="features-and-basic-concept">
                            <h1>Features and Basic Concept<a class="headerlink" href="#features-and-basic-concept"
                                                             title="Permalink to this headline">¶</a></h1>
                            <p>The main goal for this blog extension was to use TYPO3s core concepts and elements to
                                provide a
                                full-blown blog that
                                users of TYPO3 can instantly understand and use.</p>
                            <div class="section" id="pages-as-blog-entries">
                                <h2>Pages as blog entries<a class="headerlink" href="#pages-as-blog-entries"
                                                            title="Permalink to this headline">¶</a></h2>
                                <p>Blog entries are simply pages with a special page type blog entry and can be created
                                    and
                                    edited via the well-known page
                                    module. Creating new entries is as simple as dragging a new entry into the page
                                    tree.</p>
                            </div>
                        </div>


                    </div>',
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
