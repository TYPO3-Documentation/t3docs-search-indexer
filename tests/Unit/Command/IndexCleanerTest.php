<?php

namespace App\Tests\Unit\Command;

use App\Command\IndexCleaner;
use App\Repository\ElasticRepository;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Tester\CommandTester;

class IndexCleanerTest extends TestCase
{
    use ProphecyTrait;

    public function usesConstraintsToPerformDeleteQueryDataProvider(): array
    {
        return [
            'All options' => [
                [
                    '--manual-slug' => 'm/typo3/reference-coreapi/12.4/en-us',
                    '--manual-version' => '12.4',
                    '--manual-type' => 'TYPO3 Manual',
                    '--manual-language' => 'en-us'
                ],
                10
            ],
            'Some options' => [
                [
                    '--manual-slug' => 'm/typo3/reference-coreapi/12.4/en-us',
                    '--manual-type' => 'TYPO3 Manual',
                ],
                8
            ],
            'Only path' => [
                ['--manual-slug' => 'm/typo3/reference-coreapi/12.4/en-us'],
                5
            ],
            'Only version' => [
                ['--manual-version' => '12.4'],
                3
            ],
            'Only type' => [
                ['--manual-type' => 'TYPO3 Manual'],
                2
            ],
            'Only language' => [
                ['--manual-type' => 'en-us'],
                1
            ],
        ];
    }

    /**
     * @test
     * @dataProvider usesConstraintsToPerformDeleteQueryDataProvider
     */
    public function usesConstraintsToPerformDeleteQuery(array $options, int $expectedDeletions): void
    {
        $elasticRepositoryProphecy = $this->prophesize(ElasticRepository::class);
        $elasticRepositoryProphecy
            ->deleteByConstraints(Argument::type('App\Dto\Constraints'))
            ->shouldBeCalledTimes(1)
            ->willReturn($expectedDeletions);

        $command = new IndexCleaner($elasticRepositoryProphecy->reveal());
        $commandTester = new CommandTester($command);
        $commandTester->execute($options);
        $output = $commandTester->getDisplay(true);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString("Total of $expectedDeletions manuals were removed", $output);
    }
}
