<?php

namespace App\Tests\Integration\Repository;

use App\Dto\Manual;
use App\Tests\Integration\IntegrationTestCase;
use Elastica\Query\Term;

/**
 * Covers ES behaviour that the mocked unit tests cannot reach:
 * the Painless "latest" recompute and the content-hash de-duplication.
 */
class ElasticRepositoryIntegrationTest extends IntegrationTestCase
{
    /**
     * @test
     */
    public function recalculateLatestVersionsDropsStaleLatestAndSyncsSortField(): void
    {
        // A snippet that only ever existed in an old version (0.6), tagged "latest"
        // back when 0.6 was the newest — the stale state that leaks obsolete versions.
        $this->repository->addOrUpdateDocument($this->snippet([
            'fragment' => 'old',
            'content_hash' => 'old-only',
            'manual_version' => '0.6',
        ]));
        // A snippet from the current version.
        $this->repository->addOrUpdateDocument($this->snippet([
            'fragment' => 'current',
            'content_hash' => 'current',
            'manual_version' => 'main',
        ]));

        // Before the recompute both carry "latest".
        self::assertCount(2, $this->sources(new Term(['major_versions' => 'latest'])));

        // Current last-versions set of the manual = only "main".
        $this->repository->recalculateLatestVersions($this->manual(['main']));

        // After: only the current snippet keeps "latest".
        self::assertCount(1, $this->sources(new Term(['major_versions' => 'latest'])));

        $old = $this->docByFragment('old');
        self::assertNotContains('latest', $old['major_versions']);
        self::assertFalse($old['is_last_versions']);

        $current = $this->docByFragment('current');
        self::assertContains('latest', $current['major_versions']);
        self::assertTrue($current['is_last_versions']);
    }

    /**
     * @test
     */
    public function identicalContentAcrossVersionsIsStoredAsOneDocument(): void
    {
        $shared = $this->snippet(['fragment' => 'shared', 'content_hash' => 'shared']);

        $this->repository->addOrUpdateDocument(['manual_version' => '0.6'] + $shared);
        $this->repository->addOrUpdateDocument(['manual_version' => 'main'] + $shared);

        $docs = $this->sources(new Term(['content_hash' => 'shared']));

        self::assertCount(1, $docs, 'Identical content must dedupe into a single document');
        self::assertEqualsCanonicalizing(['0.6', 'main'], $docs[0]['manual_version']);
    }

    /**
     * @param array<string> $lastVersions
     */
    private function manual(array $lastVersions): Manual
    {
        return new Manual(
            absolutePath: '',
            name: 'demo',
            type: 'c',
            version: 'main',
            language: 'en-us',
            slug: 'p/acme/demo/main/en-us',
            keywords: ['demo'],
            vendor: 'acme',
            isCore: false,
            isLastVersions: true,
            lastVersions: $lastVersions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function docByFragment(string $fragment): array
    {
        $docs = $this->sources(new Term(['fragment' => $fragment]));
        self::assertCount(1, $docs);

        return $docs[0];
    }
}
