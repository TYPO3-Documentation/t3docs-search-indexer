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
    public function returnsManualsFromFolder()
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
}
