<?php

namespace App\Tests\Unit\Helper;

use App\Helper\VersionSorter;
use PHPUnit\Framework\TestCase;

class VersionSorterTest extends TestCase
{

    /**
     * @test
     * @dataProvider sortVersionsDataProvider
     */
    public function sortVersions($versions, $direction, $expected)
    {
        $actual = VersionSorter::sortVersions($versions, $direction);
        self::assertEquals($expected, $actual);
    }

    public function sortVersionsDataProvider()
    {
        return [
            [
                ['10', '8.7', '11.0', '9.3.2'],
                'asc',
                ['8.7', '9.3.2', '10', '11.0']
            ],
            [
                ['10', '8.7', '11.0', '9.3.2'],
                'desc',
                ['11.0', '10', '9.3.2', '8.7']
            ],
            [
                ['10', '8.7', 'master', '11.0', '9.3.2'],
                'asc',
                ['8.7', '9.3.2', '10', '11.0', 'master']
            ],
            [
                ['10', '8.7', 'master', '11.0', '9.3.2'],
                'desc',
                ['master', '11.0', '10', '9.3.2', '8.7']
            ]
        ];
    }
}
