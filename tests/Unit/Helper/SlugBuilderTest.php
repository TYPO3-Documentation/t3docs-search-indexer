<?php

namespace App\Tests\Unit\Helper;

use App\Dto\SearchDemand;
use App\Helper\SlugBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use T3Docs\VersionHandling\Typo3VersionMapping;

class SlugBuilderTest extends TestCase
{
    private function demand(array $query = []): SearchDemand
    {
        return SearchDemand::createFromRequest(Request::create('/search', 'GET', $query + ['q' => 'cache']));
    }

    private function coreHit(): array
    {
        $stable = Typo3VersionMapping::Stable->getVersion();
        $oldStable = Typo3VersionMapping::OldStable->getVersion();

        return [
            'manual_version' => ['main', $stable, $oldStable],
            'manual_slug' => [
                'c/typo3/cms-core/main/en-us',
                'c/typo3/cms-core/' . $stable . '/en-us',
                'c/typo3/cms-core/' . $oldStable . '/en-us',
            ],
            'is_core' => true,
        ];
    }

    /**
     * @test
     */
    public function coreHitLinksToStableByDefault(): void
    {
        self::assertSame(
            'c/typo3/cms-core/' . Typo3VersionMapping::Stable->getVersion() . '/en-us',
            SlugBuilder::build($this->coreHit(), $this->demand())
        );
    }

    /**
     * @test
     */
    public function coreHitLinksToStableForAllFilter(): void
    {
        $demand = $this->demand(['filters' => ['version' => ['all' => 'true']]]);
        self::assertSame(['all'], $demand->getFilters()['major_versions']);
        self::assertSame(
            'c/typo3/cms-core/' . Typo3VersionMapping::Stable->getVersion() . '/en-us',
            SlugBuilder::build($this->coreHit(), $demand)
        );
    }

    /**
     * @test
     */
    public function explicitVersionStillWins(): void
    {
        $oldStable = Typo3VersionMapping::OldStable->getVersion();

        self::assertSame(
            'c/typo3/cms-core/' . $oldStable . '/en-us',
            SlugBuilder::build($this->coreHit(), $this->demand(), $oldStable)
        );
    }

    /**
     * @test
     */
    public function extensionHitLinksToNewestNonMainRelease(): void
    {
        $hit = [
            'manual_version' => ['main', '0.25', '0.3'],
            'manual_slug' => [
                'p/netresearch/nr-llm/main/en-us',
                'p/netresearch/nr-llm/0.25/en-us',
                'p/netresearch/nr-llm/0.3/en-us',
            ],
            'is_core' => false,
        ];

        self::assertSame('p/netresearch/nr-llm/0.25/en-us', SlugBuilder::build($hit, $this->demand()));
    }

    /**
     * A version badge forces its own version; a minor of the same major must not be
     * replaced by another minor ("0.3" is not "0.25").
     *
     * @test
     */
    public function forcedVersionLinksToExactlyThatVersion(): void
    {
        $hit = [
            'manual_version' => ['main', '0.25', '0.3'],
            'manual_slug' => [
                'p/netresearch/nr-llm/main/en-us',
                'p/netresearch/nr-llm/0.25/en-us',
                'p/netresearch/nr-llm/0.3/en-us',
            ],
            'is_core' => false,
        ];

        self::assertSame('p/netresearch/nr-llm/0.3/en-us', SlugBuilder::build($hit, $this->demand(), '0.3'));
    }

    /**
     * A version is matched as a whole path segment: "0.2" must not pick the slug of "0.25".
     *
     * @test
     */
    public function forcedVersionDoesNotMatchALongerVersion(): void
    {
        $hit = [
            'manual_version' => ['0.25', '0.2'],
            'manual_slug' => ['p/netresearch/nr-llm/0.25/en-us', 'p/netresearch/nr-llm/0.2/en-us'],
            'is_core' => false,
        ];

        self::assertSame('p/netresearch/nr-llm/0.2/en-us', SlugBuilder::build($hit, $this->demand(), '0.2'));
    }
}
