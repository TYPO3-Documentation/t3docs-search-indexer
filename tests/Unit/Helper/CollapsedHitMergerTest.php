<?php

namespace App\Tests\Unit\Helper;

use App\Helper\CollapsedHitMerger;
use Elastica\Result;
use PHPUnit\Framework\TestCase;
use T3Docs\VersionHandling\Typo3VersionMapping;

class CollapsedHitMergerTest extends TestCase
{
    private function innerHit(array $versions, string $text): array
    {
        return [
            '_source' => [
                'manual_version' => $versions,
                'manual_slug' => array_map(static fn (string $v) => 'c/typo3/reference-coreapi/' . $v . '/en-us', $versions),
                'snippet_title' => 'Title ' . $text,
                'snippet_content' => 'Content ' . $text,
                'content_hash' => 'hash-' . $text,
            ],
            'highlight' => ['snippet_content' => ['<em>Content</em> ' . $text]],
        ];
    }

    /**
     * The best-scoring document of the group is "main"; the hit must still show the
     * text of the stable version, which is the version it links to.
     *
     * @test
     */
    public function takesTextFromRepresentativeVersion(): void
    {
        $stable = Typo3VersionMapping::Stable->getVersion();
        $top = $this->innerHit(['main'], 'dev');
        $result = new Result([
            '_source' => $top['_source'] + ['is_core' => true],
            'highlight' => $top['highlight'],
            'inner_hits' => [
                CollapsedHitMerger::INNER_HITS_NAME => ['hits' => ['hits' => [$top, $this->innerHit([$stable], 'stable')]]],
            ],
        ]);

        $merged = CollapsedHitMerger::merge([$result]);

        self::assertCount(1, $merged);
        self::assertSame(['main', $stable], $merged[0]['data']['manual_version']);
        self::assertSame('Title stable', $merged[0]['data']['snippet_title']);
        self::assertSame('Content stable', $merged[0]['data']['snippet_content']);
        self::assertSame(['snippet_content' => ['<em>Content</em> stable']], $merged[0]['highlights']);
    }

    /**
     * @test
     */
    public function keepsTopDocumentWhenItIsTheRepresentative(): void
    {
        $stable = Typo3VersionMapping::Stable->getVersion();
        $top = $this->innerHit([$stable], 'stable');
        $result = new Result([
            '_source' => $top['_source'] + ['is_core' => true],
            'highlight' => $top['highlight'],
            'inner_hits' => [
                CollapsedHitMerger::INNER_HITS_NAME => ['hits' => ['hits' => [$top, $this->innerHit(['main'], 'dev')]]],
            ],
        ]);

        $merged = CollapsedHitMerger::merge([$result]);

        self::assertSame([$stable, 'main'], $merged[0]['data']['manual_version']);
        self::assertSame('Title stable', $merged[0]['data']['snippet_title']);
        self::assertSame(['snippet_content' => ['<em>Content</em> stable']], $merged[0]['highlights']);
    }

    /**
     * @test
     */
    public function resultWithoutInnerHitsIsPassedThrough(): void
    {
        $hit = $this->innerHit(['0.3'], 'only');
        $merged = CollapsedHitMerger::merge([new Result($hit)]);

        self::assertSame(['0.3'], $merged[0]['data']['manual_version']);
        self::assertSame('Title only', $merged[0]['data']['snippet_title']);
    }
}
