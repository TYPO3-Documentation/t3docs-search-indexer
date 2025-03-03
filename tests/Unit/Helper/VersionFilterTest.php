<?php

declare(strict_types=1);

namespace App\Tests\Unit\Helper;

use App\Helper\VersionFilter;
use PHPUnit\Framework\TestCase;

class VersionFilterTest extends TestCase
{
    /**
     * @test
     */
    public function emptyArray(): void
    {
        self::assertSame([], VersionFilter::filterVersions([]));
    }

    /**
     * @test
     */
    public function singleVersion(): void
    {
        self::assertSame(['12.1'], VersionFilter::filterVersions(['12.1']));
    }

    /**
     * @test
     */
    public function multipleVersionsOneMajor(): void
    {
        $versions = ['12.1', '12.4', '12.2'];
        $expected = ['12.4'];

        self::assertSame($expected, VersionFilter::filterVersions($versions));
    }

    /**
     * @test
     */
    public function multipleVersionsMultipleMajors(): void
    {
        $versions = ['12.1', '12.4', '11.3', '11.5', '10.1'];
        $expected = ['12.4', '11.5', '10.1'];

        self::assertSame($expected, VersionFilter::filterVersions($versions));
    }

    /**
     * @test
     */
    public function unorderedVersions(): void
    {
        $versions = ['11.3', '12.1', '11.5', '12.4', '10.1'];
        $expected = ['11.5', '12.4', '10.1'];

        self::assertSame($expected, VersionFilter::filterVersions($versions));
    }

    /**
     * @test
     */
    public function versionsWithSameMajorAndMinor(): void
    {
        $versions = ['12.0', '12.0', '11.0', '11.1'];
        $expected = ['12.0', '11.1'];

        self::assertSame($expected, VersionFilter::filterVersions($versions));
    }

    /**
     * @test
     */
    public function versionsWithOnlyMajor(): void
    {
        $versions = ['12', '12', '11', '10'];
        $expected = ['12', '11', '10'];

        self::assertSame($expected, VersionFilter::filterVersions($versions));
    }

    /**
     * @test
     */
    public function nonNumericVersions(): void
    {
        $versions = ['12.1', 'main', '11.2', 'master', '11.3'];
        $expected = ['12.1', '11.3', 'main', 'master'];

        $this->assertSame($expected, VersionFilter::filterVersions($versions));
    }

    /**
     * @test
     */
    public function onlyNonNumericVersions(): void
    {
        $versions = ['main', 'master'];
        $expected = ['main', 'master'];

        $this->assertSame($expected, VersionFilter::filterVersions($versions));
    }
}
