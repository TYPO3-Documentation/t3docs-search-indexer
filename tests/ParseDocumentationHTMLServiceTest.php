<?php
/**
 * Created by PhpStorm.
 * User: mathiasschreiber
 * Date: 15.01.18
 * Time: 19:39
 */

namespace App\Tests;

use App\Service\ParseDocumentationHTMLService;
use PHPUnit\Framework\TestCase;

class ParseDocumentationHTMLServiceTest extends TestCase
{
    /**
     * @test
     * @dataProvider documentationFilePathDataProvider
     */
    public function filePathParsing(
        string $relativeFileName,
        string $expectedTitle,
        string $expectedType,
        string $expectedVersion,
        string $expectedLanguage,
        string $expectedSlug
    ) {
        $subject = new ParseDocumentationHTMLService();
        $subject->setMetaDataByFileName($relativeFileName);

        $this->assertSame($subject->getTitle(), $expectedTitle);
        $this->assertSame($subject->getType(), $expectedType);
        $this->assertSame($subject->getVersion(), $expectedVersion);
        $this->assertSame($subject->getLanguage(), $expectedLanguage);
        $this->assertSame($subject->getSlug(), $expectedSlug);
    }

    public function documentationFilePathDataProvider(): array
    {
        return [
            'core-extension-master' => [
                'relativeFileName' => 'c/typo3/cms-core/master/en-us',
                'expectedTitle' => 'typo3/cms-core',
                'expectedType' => 'c',
                'expectedVersion' => 'master',
                'expectedLanguage' => 'en-us',
                'expectedSlug' => 'c/typo3/cms-core/master/en-us',
            ],
            'core-extension-9.5' => [
                'relativeFileName' => 'c/typo3/cms-core/9.5/en-us',
                'expectedTitle' => 'typo3/cms-core',
                'expectedType' => 'c',
                'expectedVersion' => '9.5',
                'expectedLanguage' => 'en-us',
                'expectedSlug' => 'c/typo3/cms-core/9.5/en-us',
            ],
            '3rd-party-extension-draft' => [
                'relativeFileName' => 'p/vendor/package-name/draft/en-us',
                'expectedTitle' => 'vendor/package-name',
                'expectedType' => 'p',
                'expectedVersion' => 'draft',
                'expectedLanguage' => 'en-us',
                'expectedSlug' => 'p/vendor/package-name/draft/en-us',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider documentationDataProvider
     */
    public function contentParsing(string $input, string $relativeFileName, array $expectedResult)
    {
        $subject = new ParseDocumentationHTMLService();
        $receivedSection = $subject->getSections($input, $relativeFileName);

        foreach ($expectedResult as $sectionIndex => $expectedSection) {
            $this->assertSame($receivedSection[$sectionIndex], $expectedSection, 'Section with index ' . $sectionIndex . ' did not match.');
        }
    }

    public function documentationDataProvider()
    {
        return [
            'simpleMarkup' => [
                'input' => '
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
                        'manual_title' => 'TBD',
                        'manual_type' => 'TBD',
                        'manual_version' => 'TBD',
                        'manual_language' => 'TBD',
                        'manual_slug' => 'TBD',
                        'relative_url' => 'p/docsearch/blog/8.7/en-us',
                        'fragment' => 'first-section',
                        'snippet_title' => 'Headline 1',
                        'snippet_content' => 'Content 1',
                    ]
                ]
            ],
            'markupWithSubSections' => [
                'input' => '<div itemprop="articleBody" class="toBeIndexed">

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
                            <div class="section" id="use-all-your-content-elements">
                                <h2>Use all your content elements<a class="headerlink"
                                                                    href="#use-all-your-content-elements"
                                                                    title="Permalink to this headline">¶</a></h2>
                                <p>All your existing elements can be used on the blog pages - including backend layouts,
                                    custom content elements or
                                    plugins.</p>
                            </div>
                            <div class="section" id="flexible-positioning">
                                <h2>Flexible positioning<a class="headerlink" href="#flexible-positioning"
                                                           title="Permalink to this headline">¶</a></h2>
                                <p>All parts of your new blog are usable on their own, so you can just use the elements
                                    you want. The different elements include
                                    for example the comments and comment form, a sidebar or the list of blog posts. All
                                    these elements can be used as separate
                                    content elements and therefor be positioned and used wherever you want.</p>
                            </div>
                            <div class="section" id="customizable-templates">
                                <h2>Customizable Templates<a class="headerlink" href="#customizable-templates"
                                                             title="Permalink to this headline">¶</a></h2>
                                <p>Templating is done via Fluid templates. If you want your blog to have a custom look
                                    and feel just replace the templates and
                                    styles with your own. If you just want a quick blog installation, use the templates
                                    provided by the extension and just add
                                    your stylesheets.</p>
                            </div>
                            <div class="section" id="categorizing-and-tagging">
                                <h2>Categorizing and Tagging<a class="headerlink" href="#categorizing-and-tagging"
                                                               title="Permalink to this headline">¶</a></h2>
                                <p>Use categories and tags to add meta information to your blog posts. Let your users
                                    explore your posts based on their interests
                                    navigating via tags or categories to find similar entries. Add posts from the same
                                    category to your posts to get your readers
                                    to read even more.</p>
                            </div>
                            <div class="section" id="be-social-share-your-posts">
                                <h2>Be social - share your posts<a class="headerlink" href="#be-social-share-your-posts"
                                                                   title="Permalink to this headline">¶</a></h2>
                                <p>Enable sharing in the commonly used social networks by enabling a single
                                    checkbox.</p>
                            </div>
                        </div>


                    </div>',
                'relativeFileName' => 'p/docsearch/blog/8.7/en-us',
                'expected' => [
                    [
                        'manual_title' => 'TBD',
                        'manual_type' => 'TBD',
                        'manual_version' => 'TBD',
                        'manual_language' => 'TBD',
                        'manual_slug' => 'TBD',
                        'relative_url' => 'p/docsearch/blog/8.7/en-us',
                        'fragment' => 'features-and-basic-concept',
                        'snippet_title' => 'Features and Basic Concept',
                        'snippet_content' => 'The main goal for this blog extension was to use TYPO3s core concepts and elements to provide a full-blown blog that users of TYPO3 can instantly understand and use.'
                    ],
                    [
                        'manual_title' => 'TBD',
                        'manual_type' => 'TBD',
                        'manual_version' => 'TBD',
                        'manual_language' => 'TBD',
                        'manual_slug' => 'TBD',
                        'relative_url' => 'p/docsearch/blog/8.7/en-us',
                        'fragment' => 'pages-as-blog-entries',
                        'snippet_title' => 'Pages as blog entries',
                        'snippet_content' => 'Blog entries are simply pages with a special page type blog entry and can be created and edited via the well-known page module. Creating new entries is as simple as dragging a new entry into the page tree.'
                    ],
                    [
                        'manual_title' => 'TBD',
                        'manual_type' => 'TBD',
                        'manual_version' => 'TBD',
                        'manual_language' => 'TBD',
                        'manual_slug' => 'TBD',
                        'relative_url' => 'p/docsearch/blog/8.7/en-us',
                        'fragment' => 'use-all-your-content-elements',
                        'snippet_title' => 'Use all your content elements',
                        'snippet_content' => 'All your existing elements can be used on the blog pages - including backend layouts, custom content elements or plugins.'
                    ],
                    [
                        'manual_title' => 'TBD',
                        'manual_type' => 'TBD',
                        'manual_version' => 'TBD',
                        'manual_language' => 'TBD',
                        'manual_slug' => 'TBD',
                        'relative_url' => 'p/docsearch/blog/8.7/en-us',
                        'fragment' => 'flexible-positioning',
                        'snippet_title' => 'Flexible positioning',
                        'snippet_content' => 'All parts of your new blog are usable on their own, so you can just use the elements you want. The different elements include for example the comments and comment form, a sidebar or the list of blog posts. All these elements can be used as separate content elements and therefor be positioned and used wherever you want.'
                    ],
                    [
                        'manual_title' => 'TBD',
                        'manual_type' => 'TBD',
                        'manual_version' => 'TBD',
                        'manual_language' => 'TBD',
                        'manual_slug' => 'TBD',
                        'relative_url' => 'p/docsearch/blog/8.7/en-us',
                        'fragment' => 'customizable-templates',
                        'snippet_title' => 'Customizable Templates',
                        'snippet_content' => 'Templating is done via Fluid templates. If you want your blog to have a custom look and feel just replace the templates and styles with your own. If you just want a quick blog installation, use the templates provided by the extension and just add your stylesheets.'
                    ],
                    [
                        'manual_title' => 'TBD',
                        'manual_type' => 'TBD',
                        'manual_version' => 'TBD',
                        'manual_language' => 'TBD',
                        'manual_slug' => 'TBD',
                        'relative_url' => 'p/docsearch/blog/8.7/en-us',
                        'fragment' => 'categorizing-and-tagging',
                        'snippet_title' => 'Categorizing and Tagging',
                        'snippet_content' => 'Use categories and tags to add meta information to your blog posts. Let your users explore your posts based on their interests navigating via tags or categories to find similar entries. Add posts from the same category to your posts to get your readers to read even more.'
                    ],
                    [
                        'manual_title' => 'TBD',
                        'manual_type' => 'TBD',
                        'manual_version' => 'TBD',
                        'manual_language' => 'TBD',
                        'manual_slug' => 'TBD',
                        'relative_url' => 'p/docsearch/blog/8.7/en-us',
                        'fragment' => 'be-social-share-your-posts',
                        'snippet_title' => 'Be social - share your posts',
                        'snippet_content' => 'Enable sharing in the commonly used social networks by enabling a single checkbox.'
                    ],
                ]
            ],
            'markupWithSubSectionsSmall' => [
                'input' => '<div itemprop="articleBody" class="toBeIndexed">

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
                        'manual_title' => 'TBD',
                        'manual_type' => 'TBD',
                        'manual_version' => 'TBD',
                        'manual_language' => 'TBD',
                        'manual_slug' => 'TBD',
                        'relative_url' => 'p/docsearch/blog/8.7/en-us',
                        'fragment' => 'features-and-basic-concept',
                        'snippet_title' => 'Features and Basic Concept',
                        'snippet_content' => 'The main goal for this blog extension was to use TYPO3s core concepts and elements to provide a full-blown blog that users of TYPO3 can instantly understand and use.'
                    ],
                    [
                        'manual_title' => 'TBD',
                        'manual_type' => 'TBD',
                        'manual_version' => 'TBD',
                        'manual_language' => 'TBD',
                        'manual_slug' => 'TBD',
                        'relative_url' => 'p/docsearch/blog/8.7/en-us',
                        'fragment' => 'pages-as-blog-entries',
                        'snippet_title' => 'Pages as blog entries',
                        'snippet_content' => 'Blog entries are simply pages with a special page type blog entry and can be created and edited via the well-known page module. Creating new entries is as simple as dragging a new entry into the page tree.'
                    ],
                ]
            ],
        ];
    }
}
