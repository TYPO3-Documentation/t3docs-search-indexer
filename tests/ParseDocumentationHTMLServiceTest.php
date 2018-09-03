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
     * @dataProvider documentationDataProvider
     * @param string $input
     * @param array $expectedResult
     */
    public function parserTest(string $input, array $expectedResult)
    {
        $subject = new ParseDocumentationHTMLService();
        $actualResult = $subject->parseContent($input);
        $this->assertSame($actualResult, $expectedResult);
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
                'expected' => [
                    [
                        'book_name' => 'blog',
                        'book-type' => 'Extension Manual',
                        'book-version' => '8.7.0',
                        'id' => 'first-section',
                        'headline' => 'Headline 1',
                        'content' => 'Content 1'
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
                'expected' => [
                    [
                        'book_name' => 'blog',
                        'book-type' => 'Extension Manual',
                        'book-version' => '8.7.0',
                        'id' => 'features-and-basic-concept',
                        'headline' => 'Features and Basic Concept',
                        'content' => 'The main goal for this blog extension was to use TYPO3s core concepts and elements to provide a full-blown blog that users of TYPO3 can instantly understand and use.'
                    ],
                    [
                        'book_name' => 'blog',
                        'book-type' => 'Extension Manual',
                        'book-version' => '8.7.0',
                        'id' => 'pages-as-blog-entries',
                        'headline' => 'Pages as blog entries',
                        'content' => 'Blog entries are simply pages with a special page type blog entry and can be created and edited via the well-known page module. Creating new entries is as simple as dragging a new entry into the page tree.'
                    ],
                    [
                        'book_name' => 'blog',
                        'book-type' => 'Extension Manual',
                        'book-version' => '8.7.0',
                        'id' => 'use-all-your-content-elements',
                        'headline' => 'Use all your content elements',
                        'content' => 'All your existing elements can be used on the blog pages - including backend layouts, custom content elements or plugins.'
                    ],
                    [
                        'book_name' => 'blog',
                        'book-type' => 'Extension Manual',
                        'book-version' => '8.7.0',
                        'id' => 'flexible-positioning',
                        'headline' => 'Flexible positioning',
                        'content' => 'All parts of your new blog are usable on their own, so you can just use the elements you want. The different elements include for example the comments and comment form, a sidebar or the list of blog posts. All these elements can be used as separate content elements and therefor be positioned and used wherever you want.'
                    ],
                    [
                        'book_name' => 'blog',
                        'book-type' => 'Extension Manual',
                        'book-version' => '8.7.0',
                        'id' => 'customizable-templates',
                        'headline' => 'Customizable Templates',
                        'content' => 'Templating is done via Fluid templates. If you want your blog to have a custom look and feel just replace the templates and styles with your own. If you just want a quick blog installation, use the templates provided by the extension and just add your stylesheets.'
                    ],
                    [
                        'book_name' => 'blog',
                        'book-type' => 'Extension Manual',
                        'book-version' => '8.7.0',
                        'id' => 'categorizing-and-tagging',
                        'headline' => 'Categorizing and Tagging',
                        'content' => 'Use categories and tags to add meta information to your blog posts. Let your users explore your posts based on their interests navigating via tags or categories to find similar entries. Add posts from the same category to your posts to get your readers to read even more.'
                    ],
                    [
                        'book_name' => 'blog',
                        'book-type' => 'Extension Manual',
                        'book-version' => '8.7.0',
                        'id' => 'be-social-share-your-posts',
                        'headline' => 'Be social - share your posts',
                        'content' => 'Enable sharing in the commonly used social networks by enabling a single checkbox.'
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
                'expected' => [
                    [
                        'book_name' => 'blog',
                        'book-type' => 'Extension Manual',
                        'book-version' => '8.7.0',
                        'id' => 'features-and-basic-concept',
                        'headline' => 'Features and Basic Concept',
                        'content' => 'The main goal for this blog extension was to use TYPO3s core concepts and elements to provide a full-blown blog that users of TYPO3 can instantly understand and use.'
                    ],
                    [
                        'book_name' => 'blog',
                        'book-type' => 'Extension Manual',
                        'book-version' => '8.7.0',
                        'id' => 'pages-as-blog-entries',
                        'headline' => 'Pages as blog entries',
                        'content' => 'Blog entries are simply pages with a special page type blog entry and can be created and edited via the well-known page module. Creating new entries is as simple as dragging a new entry into the page tree.'
                    ],
                ]
            ],
//            'indexMarkup' => [
//                'input' => '<div itemprop="articleBody" class="toBeIndexed">
//
//  <div class="section" id="typo3-blog-extension">
//<span id="start"></span><h1>TYPO3 Blog Extension<a class="headerlink" href="#typo3-blog-extension" title="Permalink to this headline">¶</a></h1>
//<table class="docutils field-list" frame="void" rules="none">
//<colgroup><col class="field-name">
//<col class="field-body">
//</colgroup><tbody valign="top">
//<tr class="field-odd field"><th class="field-name">Author:</th><td class="field-body">TYPO3 GmbH Team &lt;<a class="reference external" href="mailto:info%40typo3.com">info<span>@</span>typo3<span>.</span>com</a>&gt;</td>
//</tr>
//<tr class="field-even field"><th class="field-name">License:</th><td class="field-body">GPL 3.0</td>
//</tr>
//<tr class="field-odd field"><th class="field-name">Rendered:</th><td class="field-body">2017-09-04 14:17</td>
//</tr>
//<tr class="field-even field"><th class="field-name">Description:</th><td class="field-body">The blog extension for TYPO3 provides a blog based on TYPO3s core features - pages and content elements. Use all your
//favourite and well-known elements to create a full blown blog with ease.</td>
//</tr>
//</tbody>
//</table>
//<div class="toctree-wrapper compound">
//<ul>
//<li class="toctree-l1"><a class="reference internal" href="Basics/Index.html">Features and Basic Concept</a><ul>
//<li class="toctree-l2"><a class="reference internal" href="Basics/Index.html#pages-as-blog-entries">Pages as blog entries</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Basics/Index.html#use-all-your-content-elements">Use all your content elements</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Basics/Index.html#flexible-positioning">Flexible positioning</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Basics/Index.html#customizable-templates">Customizable Templates</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Basics/Index.html#categorizing-and-tagging">Categorizing and Tagging</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Basics/Index.html#be-social-share-your-posts">Be social - share your posts</a></li>
//</ul>
//</li>
//<li class="toctree-l1"><a class="reference internal" href="Administrators/Index.html">Blogging for Administrators</a><ul>
//<li class="toctree-l2"><a class="reference internal" href="Administrators/Index.html#installation">Installation</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Administrators/Index.html#latest-version-from-git">Latest version from git</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Administrators/Index.html#setup">Setup</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Administrators/Index.html#plugin-types">Plugin types</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Administrators/Index.html#creating-categories-and-tags">Creating Categories and Tags</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Administrators/Index.html#enable-sharing">Enable sharing</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Administrators/Index.html#avatarprovider">AvatarProvider</a></li>
//</ul>
//</li>
//<li class="toctree-l1"><a class="reference internal" href="Integrators/Index.html">Blogging for Integrators</a><ul>
//<li class="toctree-l2"><a class="reference internal" href="Integrators/Index.html#typoscript-reference">TypoScript Reference</a></li>
//</ul>
//</li>
//<li class="toctree-l1"><a class="reference internal" href="Editors/Index.html">Blogging for Editors</a><ul>
//<li class="toctree-l2"><a class="reference internal" href="Editors/Index.html#create-a-new-post">Create a new post</a></li>
//<li class="toctree-l2"><a class="reference internal" href="Editors/Index.html#add-content-to-your-post">Add content to your post</a></li>
//</ul>
//</li>
//<li class="toctree-l1"><a class="reference internal" href="FAQ/Index.html">Frequently Asked Questions</a><ul>
//<li class="toctree-l2"><a class="reference internal" href="FAQ/Index.html#where-to-report-bugs-or-improvements">Where to report bugs or improvements?</a></li>
//<li class="toctree-l2"><a class="reference internal" href="FAQ/Index.html#slack-channel">Slack channel</a></li>
//<li class="toctree-l2"><a class="reference internal" href="FAQ/Index.html#contributions">Contributions</a></li>
//<li class="toctree-l2"><a class="reference internal" href="FAQ/Index.html#clone-git-repo">Clone / git repo</a></li>
//</ul>
//</li>
//<li class="toctree-l1"><a class="reference internal" href="Changelog/Index.html">Changelog</a><ul>
//<li class="toctree-l2"><a class="reference internal" href="Changelog/1.2.0/Index.html">Changes in version 1.2.0</a></li>
//</ul>
//</li>
//</ul>
//</div>
//</div>
//
//
//           </div>',
//                'expected' => [
//                    [
//                        'book_name' => 'blog',
//                        'book-type' => 'Extension Manual',
//                        'book-version' => '8.7.0',
//                        'id' => 'first-section',
//                        'headline' => 'TYPO3 Blog Extension',
//                        'content' => 'Content 1'
//                    ]
//                ]
//            ],
        ];
    }
}
