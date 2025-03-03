<?php

namespace App\Tests\Unit\Service;

use App\Service\DirectoryFinderService;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class DirectoryFinderServiceTest extends TestCase
{
    /**
     * @test
     */
    public function returnsManualsFromFolder(): void
    {
        $subject = new DirectoryFinderService(['^m/', '^c/', '^p/'], ['other', 'draft', 'typo3cms/extensions']);

        $rootDir = vfsStream::setup('_docsFolder', null, [
            'c' => [
                'typo3' => [
                    'cms-core' => [
                        'main' => [
                            'en-us' => [
                                'objects.inv.json' => ''
                            ],
                        ],
                    ],
                    'cms-form' => [
                        'main' => [
                            'en-us' => [
                                'objects.inv.json' => ''
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
                                'objects.inv.json' => ''
                            ],
                        ],
                        'main' => [
                            'en-us' => [
                                'objects.inv.json' => ''
                            ],
                        ],
                    ],
                    'reference-coreapi' => [
                        '9.5' => [
                            'en-us' => [
                                'objects.inv.json' => ''
                            ],
                        ],
                        'main' => [
                            'en-us' => [
                                'objects.inv.json' => ''
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertCount(6, $subject->getAllManualDirectories($rootDir->url()));
    }

    /**
     * @test
     * @see DirectoryFinderService::isNotIgnoredPath()
     */
    public function respectsIgnoredPats(): void
    {
        $subject = new DirectoryFinderService(['^m/', '^c/', '^p/'], []);

        $rootDir = vfsStream::setup('_docsFolder', null, [
            'c' => [
                'typo3' => [
                    'cms-core' => [
                        '10.4' => [
                            'en-us' => [
                                'objects.inv.json' => ''
                            ],
                        ],
                        '11.5' => [
                            'en-us' => [
                                'objects.inv.json' => ''
                            ],
                        ],
                        'main' => [
                            'en-us' => [
                                'objects.inv.json' => ''
                            ],
                        ],
                    ],
                    'cms-form' => [
                        '12.4' => [
                            'en-us' => [
                                'objects.inv.json' => ''
                            ],
                        ],
                        'main' => [
                            'en-us' => [
                                'objects.inv.json' => ''
                            ],
                        ],
                    ],
                ],
            ],
            'm' => [
                'typo3' => [
                    'reference-coreapi' => [
                        '9.5' => [
                            'en-us' => [
                                'objects.inv.json' => ''
                            ],
                        ],
                        'main' => [
                            'en-us' => [
                                'objects.inv.json' => ''
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // since DirectoryFinderService::isNotIgnoredPath() should ignore all versions of typo3/cms-core
        // except the main version, we should have 5 directories returned
        self::assertCount(5, $subject->getAllManualDirectories($rootDir->url()));
    }

    public function getAllManualDirectoriesRespectsOnlyDirectoriesWithMetadataFileDataProvider(): array
    {
        return [
            'none directory contain metadata file' => [
                'allowedPathsRegexs' => ['^p/'],
                'excluded' => [],
                [
                    'p' => [
                        'vendor' => [
                            'news' => [
                                'main' => [
                                    'en-us' => [
                                    ],
                                ],
                            ],
                            'gallery' => [
                                'main' => [
                                    'en-us' => [
                                    ],
                                ],
                            ],
                            'logger' => [
                                'main' => [
                                    'en-us' => [
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'expected' => 0,
            ],
            'single directory contains metadata file' => [
                'allowedPathsRegexs' => ['^p/'],
                'excluded' => [],
                [
                    'p' => [
                        'vendor' => [
                            'news' => [
                                'main' => [
                                    'en-us' => [
                                    ],
                                ],
                            ],
                            'gallery' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                            'logger' => [
                                'main' => [
                                    'en-us' => [
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'expected' => 1,
            ],
            'all directories contain metadata file' => [
                'allowedPathsRegexs' => ['^p/'],
                'excluded' => [],
                [
                    'p' => [
                        'vendor' => [
                            'news' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                            'gallery' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                            'logger' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'expected' => 3,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getAllManualDirectoriesRespectsOnlyDirectoriesWithMetadataFileDataProvider
     */
    public function getAllManualDirectoriesRespectsOnlyDirectoriesWithMetadataFile(
        array $allowedPathsRegexs,
        array $excludedDirectories,
        array $foldersStructure,
        int $expectedDirectories
    ): void {
        $vfsStream = vfsStream::setup('_docsFolder', null, $foldersStructure);
        $subject = new DirectoryFinderService($allowedPathsRegexs, $excludedDirectories);

        $finder = $subject->getAllManualDirectories($vfsStream->url());

        self::assertEquals($expectedDirectories, iterator_count($finder));
    }

    public function getAllManualDirectoriesRespectsAllowedPathsDataProvider(): array
    {
        return [
            'all paths are allowed (no selection)' => [
                'allowedPathsRegexs' => [],
                'excluded' => [],
                [
                    'c' => [
                        'vendor' => [
                            'news' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'm' => [
                        'vendor' => [
                            'gallery' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'p' => [
                        'vendor' => [
                            'logger' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'expected' => 3,
            ],
            '1 out of 3 paths are allowed' => [
                'allowedPathsRegexs' => ['^c/'],
                'excluded' => [],
                [
                    'c' => [
                        'vendor' => [
                            'news' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'm' => [
                        'vendor' => [
                            'gallery' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'p' => [
                        'vendor' => [
                            'logger' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'expected' => 1,
            ],
            '2 out of 3 paths are allowed' => [
                'allowedPathsRegexs' => ['^c/', '^p/'],
                'excluded' => [],
                [
                    'c' => [
                        'vendor' => [
                            'news' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'm' => [
                        'vendor' => [
                            'gallery' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'p' => [
                        'vendor' => [
                            'logger' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'expected' => 2,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getAllManualDirectoriesRespectsAllowedPathsDataProvider
     */
    public function getAllManualDirectoriesRespectsAllowedPaths(
        array $allowedPathsRegexs,
        array $excludedDirectories,
        array $foldersStructure,
        int $expectedDirectories
    ): void {
        $vfsStream = vfsStream::setup('_docsFolder', null, $foldersStructure);
        $subject = new DirectoryFinderService($allowedPathsRegexs, $excludedDirectories);

        $finder = $subject->getAllManualDirectories($vfsStream->url());

        self::assertEquals($expectedDirectories, iterator_count($finder));
    }

    public function getDirectoriesByPathRespectsDirectoriesExclusionDataProvider(): array
    {
        return [
            'all excluded' => [
                'allowedPathsRegexs' => ['^p/'],
                'excluded' => ['news', 'gallery', 'logger'],
                [
                    'p' => [
                        'vendor' => [
                            'news' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                            'gallery' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                            'logger' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'expected' => 0,
            ],
            'single excluded' => [
                'allowedPathsRegexs' => ['^p/'],
                'excluded' => ['news'],
                [
                    'p' => [
                        'vendor' => [
                            'news' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                            'gallery' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                            'logger' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'expected' => 2,
            ],
            'none excluded' => [
                'allowedPathsRegexs' => ['^p/'],
                'excluded' => [],
                [
                    'p' => [
                        'vendor' => [
                            'news' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                            'gallery' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                            'logger' => [
                                'main' => [
                                    'en-us' => [
                                        'objects.inv.json' => ''
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'expected' => 3,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getDirectoriesByPathRespectsDirectoriesExclusionDataProvider
     */
    public function getDirectoriesByPathRespectsDirectoriesExclusion(
        array $allowedPathsRegexs,
        array $excludedDirectories,
        array $foldersStructure,
        int $expectedDirectories
    ): void {
        $vfsStream = vfsStream::setup('_docsFolder', null, $foldersStructure);
        $subject = new DirectoryFinderService($allowedPathsRegexs, $excludedDirectories);

        $finder = $subject->getDirectoriesByPath($vfsStream->url());

        self::assertCount($expectedDirectories, $finder->directories());
    }
}
