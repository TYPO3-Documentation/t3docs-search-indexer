<?php

namespace App\Tests\Unit\Dto;

use App\Dto\Manual;
use PHPUnit\Framework\TestCase;

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
}
