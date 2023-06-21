<?php

namespace App\Tests\Unit\Dto;

use App\Dto\Manual;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;

class ManualTest extends TestCase
{
    /**
     * @test
     */
    public function createFromFolder()
    {
        $folder = $this->prophesize(\SplFileInfo::class);
        $folder->getPathname()->willReturn('_docsFolder/c/typo3/cms-core/main/en-us');
        $folder->__toString()->willReturn('_docsFolder/c/typo3/cms-core/main/en-us');
        $returnedManual = Manual::createFromFolder($folder->reveal());

        self::assertInstanceOf(Manual::class, $returnedManual);
        self::assertSame('_docsFolder/c/typo3/cms-core/main/en-us', $returnedManual->getAbsolutePath());
        self::assertSame('typo3/cms-core', $returnedManual->getTitle());
        self::assertSame('System extension', $returnedManual->getType());
        self::assertSame('en-us', $returnedManual->getLanguage());
        self::assertSame('c/typo3/cms-core/main/en-us', $returnedManual->getSlug());
        self::assertSame('main', $returnedManual->getVersion());
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

        $manual = new Manual($filesRoot, 'title', 'type', 'main', 'en_us', 'slug');
        $files = $manual->getFilesWithSections();
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
}
