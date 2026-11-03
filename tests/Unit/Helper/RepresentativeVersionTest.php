<?php

namespace App\Tests\Unit\Helper;

use App\Helper\RepresentativeVersion;
use PHPUnit\Framework\TestCase;
use T3Docs\VersionHandling\Typo3VersionMapping;

class RepresentativeVersionTest extends TestCase
{
    /**
     * @test
     * @dataProvider selectDataProvider
     */
    public function select(array $versions, bool $isCore, ?string $expected): void
    {
        self::assertSame($expected, RepresentativeVersion::select($versions, $isCore));
    }

    public function selectDataProvider(): array
    {
        $stable = Typo3VersionMapping::Stable->getVersion();
        $oldStable = Typo3VersionMapping::OldStable->getVersion();

        return [
            'core: stable wins over main' => [[$stable, 'main'], true, $stable],
            'core: stable wins over main and older versions' => [['main', $oldStable, $stable], true, $stable],
            'core: without stable, the newest (main) wins' => [[$oldStable, 'main'], true, 'main'],
            'core: without stable and main, the newest release wins' => [['12.4', $oldStable], true, $oldStable],
            'core: main only' => [['main'], true, 'main'],
            'extension: newest non-main release, compared as versions' => [['main', '0.25', '0.3'], false, '0.25'],
            'extension: main only' => [['main'], false, 'main'],
            'extension: a version equal to the TYPO3 stable gets no special treatment' => [[$stable, '15.0', 'main'], false, '15.0'],
            'no versions' => [[], true, null],
        ];
    }
}
