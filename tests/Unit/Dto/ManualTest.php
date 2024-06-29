<?php

namespace App\Tests\Unit\Dto;

use App\Config\ManualType;
use App\Dto\Manual;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Finder\SplFileInfo;

class ManualTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function createFromFolderWithChangelog(): void
    {
        $folder = $this->prophesize(\SplFileInfo::class);
        $folder->willBeConstructedWith(['dummy_filename']);
        $folder->getPathname()->willReturn('_docsFolder/c/typo3/cms-core/main/en-us/Changelog/5.14');
        $folder->__toString()->willReturn('_docsFolder/c/typo3/cms-core/main/en-us/Changelog/5.14');

        $manual = Manual::createFromFolder($folder->reveal(), true);

        $this->assertSame('_docsFolder/c/typo3/cms-core/main/en-us/Changelog/5.14', $manual->getAbsolutePath());
        $this->assertSame('typo3/cms-core-changelog', $manual->getTitle());
        $this->assertSame(ManualType::CoreChangelog->value, $manual->getType());
        $this->assertSame('5.14', $manual->getVersion());
        $this->assertSame('en-us', $manual->getLanguage());
        $this->assertSame('c/typo3/cms-core/main/en-us/Changelog/5.14', $manual->getSlug());
        $this->assertSame([], $manual->getKeywords());
    }

    /**
     * @test
     */
    public function createFromFolderWithoutChangelog(): void
    {
        $folder = $this->prophesize(\SplFileInfo::class);
        $folder->willBeConstructedWith(['dummy_filename']);
        $folder->getPathname()->willReturn('_docsFolder/c/typo3/cms-core/12.4/en-us');
        $folder->__toString()->willReturn('_docsFolder/c/typo3/cms-core/12.4/en-us');

        $manual = Manual::createFromFolder($folder->reveal(), false);

        $this->assertSame('_docsFolder/c/typo3/cms-core/12.4/en-us', $manual->getAbsolutePath());
        $this->assertSame('typo3/cms-core', $manual->getTitle());
        $this->assertSame(ManualType::SystemExtension->value, $manual->getType());
        $this->assertSame('12.4', $manual->getVersion());
        $this->assertSame('en-us', $manual->getLanguage());
        $this->assertSame('c/typo3/cms-core/12.4/en-us', $manual->getSlug());
        $this->assertSame(['cms-core'], $manual->getKeywords());
    }

    /**
     * @test
     */
    public function createFromFolderWithInvalidPath(): void
    {
        $folder = $this->prophesize(\SplFileInfo::class);
        $folder->willBeConstructedWith(['dummy_filename']);
        $folder->getPathname()->willReturn('invalid/path');
        $folder->__toString()->willReturn('invalid/path');

        try {
            $manual = Manual::createFromFolder($folder->reveal());
        } catch (\Throwable $e) {
            $this->assertSame('Undefined array key 2', $e->getMessage());
        }
    }

    public function  createFromFolderWithDifferentPathTypesDataProvider(): array
    {
        return [
            ['_docsFolder/c/typo3/cms-core/main/en-us', ManualType::SystemExtension->value, false],
            ['_docsFolder/p/vendor/package/1.0/pl-pl', ManualType::CommunityExtension->value, false],
            ['_docsFolder/m/typo3/reference-coreapi/12.4/en-us', ManualType::Typo3Manual->value, false],
            ['_docsFolder/c/typo3/cms-core/main/en-us/Changelog/9.4', ManualType::CoreChangelog->value, true],
            ['_docsFolder/h/typo3/docs-homepage/main/en-us', ManualType::DocsHomePage->value, false]
        ];
    }

    /**
     * @test
     * @dataProvider createFromFolderWithDifferentPathTypesDataProvider
     */
    public function createFromFolderWithDifferentPathTypes(string $path, string $expectedType, bool $changelog = false): void
    {
        $folder = $this->prophesize(\SplFileInfo::class);
        $folder->willBeConstructedWith(['dummy_filename']);
        $folder->getPathname()->willReturn($path);
        $folder->__toString()->willReturn($path);

        $manual = Manual::createFromFolder($folder->reveal(), $changelog);

        $this->assertSame($expectedType, $manual->getType());
    }

    /**
     * @test
     */
    public function createFromFolderWithUnmappedType(): void
    {
        $folder = $this->prophesize(\SplFileInfo::class);
        $folder->willBeConstructedWith(['dummy_filename']);
        $folder->getPathname()->willReturn('_docsFolder/x/typo3/cms-core/main/en-us');
        $folder->__toString()->willReturn('_docsFolder/x/typo3/cms-core/main/en-us');

        $manual = Manual::createFromFolder($folder->reveal());

        $this->assertSame('x', $manual->getType());
    }

    /**
     * @test
     */
    public function returnsFilesWithSectionsForManual(): void
    {
        $filesRoot = implode(DIRECTORY_SEPARATOR, [
            __DIR__,
            'Fixtures',
            'ManualTest',
            'ReturnsFilesWithSectionsForManual',
            'manual',
        ]);

        $manual = new Manual($filesRoot, 'title', 'type', 'main', 'en_us', 'slug', []);
        $files = $manual->getFilesWithSections();
        self::assertCount(3, $files);
        $expectedFiles = [
            $filesRoot . '/index.html',
            $filesRoot . '/another.html',
            $filesRoot . '/additional/index.html',
        ];
        foreach ($files as $file) {
            /* @var $file SplFileInfo */
            self::assertContains((string)$file, $expectedFiles, 'Unexpected file: ' . $file);
        }
    }

    /**
     * @test
     */
    public function subManualsAreNotReturnForNonTypo3CmsCoreExtensions(): void
    {
        $manual = new Manual('/path', 'Title', 'type', 'main', 'en-us', 'slug', []);

        $result = $manual->getSubManuals();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * @test
     */
    public function subManualsAreNotReturnForNonMainVersion(): void
    {
        $manual = new Manual('/path', 'typo3/cms-core', 'type', '1.0', 'en-us', 'slug', []);

        $result = $manual->getSubManuals();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * @test
     */
    public function returnsSubManuals(): void
    {
        $rootPath = vfsStream::setup('_docsFolder', null, [
            'c' => [
                'typo3' => [
                    'cms-core' => [
                        'main' => [
                            'en-us' => [
                                'Changelog' => [
                                    '9.4' => [
                                        'index.html' => '',
                                    ],
                                    '10.4' => [
                                        'index.html' => '',
                                    ],
                                ],
                                'Editor' => [
                                    'index.html' => '',
                                ],
                                'index.html' => '',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $folder = $this->prophesize(\SplFileInfo::class);
        $folder->willBeConstructedWith(['dummy_filename']);
        $folder->getPathname()->willReturn('_docsFolder/c/typo3/cms-core/main/en-us');
        $folder->__toString()->willReturn('_docsFolder/c/typo3/cms-core/main/en-us');

        $manual = new Manual(
            $rootPath->url() . '/c/typo3/cms-core/main/en-us',
            'typo3/cms-core',
            ManualType::SystemExtension->value,
            'main',
            'en-us',
            'c/typo3/cms-core/main/en-us',
            ['keyword 1', 'keyword 2']
        );
        $subManuals = $manual->getSubManuals();

        self::assertCount(2, $subManuals);

        self::assertSame('vfs://_docsFolder/c/typo3/cms-core/main/en-us/Changelog/9.4', $subManuals[0]->getAbsolutePath());
        self::assertSame('typo3/cms-core-changelog', $subManuals[0]->getTitle());
        self::assertSame(ManualType::CoreChangelog->value, $subManuals[0]->getType());
        self::assertSame('9.4', $subManuals[0]->getVersion());
        self::assertSame('en-us', $subManuals[0]->getLanguage());
        self::assertSame('c/typo3/cms-core/main/en-us/Changelog/9.4', $subManuals[0]->getSlug());

        self::assertSame('vfs://_docsFolder/c/typo3/cms-core/main/en-us/Changelog/10.4', $subManuals[1]->getAbsolutePath());
        self::assertSame('typo3/cms-core-changelog', $subManuals[1]->getTitle());
        self::assertSame(ManualType::CoreChangelog->value, $subManuals[1]->getType());
        self::assertSame('10.4', $subManuals[1]->getVersion());
        self::assertSame('en-us', $subManuals[1]->getLanguage());
        self::assertSame('c/typo3/cms-core/main/en-us/Changelog/10.4', $subManuals[1]->getSlug());
    }

    /**
     * @test
     */
    public function returnsAbsolutePath(): void
    {
        $manual = new Manual('/path', 'Title', 'type', 'main', 'en-us', 'slug', []);
        $this->assertEquals('/path', $manual->getAbsolutePath());
    }

    /**
     * @test
     */
    public function returnsTitle(): void
    {
        $manual = new Manual('/path', 'Title', 'type', 'main', 'en-us', 'slug', []);
        $this->assertEquals('Title', $manual->getTitle());
    }

    /**
     * @test
     */
    public function returnsType(): void
    {
        $manual = new Manual('/path', 'Title', 'type', 'main', 'en-us', 'slug', []);
        $this->assertEquals('type', $manual->getType());
    }

    /**
     * @test
     */
    public function returnsVersion(): void
    {
        $manual = new Manual('/path', 'Title', 'type', 'main', 'en-us', 'slug', []);
        $this->assertEquals('main', $manual->getVersion());
    }

    /**
     * @test
     */
    public function returnsLanguage(): void
    {
        $manual = new Manual('SomePath', 'SomeTitle', 'SomeType', 'SomeVersion', 'SomeLanguage', 'SomeSlug', []);
        $this->assertEquals('SomeLanguage', $manual->getLanguage());
    }

    /**
     * @test
     */
    public function returnsSlug(): void
    {
        $manual = new Manual('/path', 'Title', 'type', 'main', 'en-us', 'slug', []);
        $this->assertEquals('slug', $manual->getSlug());
    }

    /**
     * @test
     */
    public function returnsKeywords(): void
    {
        $manual = new Manual('/path', 'Title', 'type', 'main', 'en-us', 'slug', ['keyword 1', 'keyword 2']);
        $this->assertSame(['keyword 1', 'keyword 2'], $manual->getKeywords());
    }
}
