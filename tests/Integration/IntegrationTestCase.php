<?php

namespace App\Tests\Integration;

use App\QueryBuilder\ElasticQueryBuilder;
use App\Repository\ElasticRepository;
use Elastica\Index;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Base class for integration tests that exercise a real Elasticsearch instance
 * (queries, aggregations and Painless scripts that unit tests mock away).
 *
 * These tests are NOT part of CI yet — run them manually against a running ES:
 *
 *     composer ci:test:integration
 *
 * Inside DDEV the ELASTICA_HOST is preset to "elasticsearch"; otherwise export it.
 * Each test runs against an isolated throwaway index ("docsearch_test") built from
 * the real Elasticorn mapping/settings, so it cannot drift from production.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected const TEST_INDEX = 'docsearch_test';

    protected ElasticRepository $repository;

    protected Index $index;

    protected function setUp(): void
    {
        // The repository reads connection settings from $_ENV directly; make sure the
        // container's real env vars are present there (bootstrap keeps existing ones).
        foreach (['ELASTICA_HOST', 'ELASTICA_PORT', 'ELASTICA_TRANSPORT', 'ELASTICA_PATH'] as $key) {
            if (empty($_ENV[$key]) && ($value = getenv($key)) !== false) {
                $_ENV[$key] = $value;
            }
        }
        $_ENV['ELASTICA_INDEX'] = self::TEST_INDEX;

        $this->repository = new ElasticRepository(new ElasticQueryBuilder());
        $this->index = $this->repository->getElasticIndex();

        if ($this->index->exists()) {
            $this->index->delete();
        }
        $this->index->create($this->indexDefinition());
        $this->index->refresh();
    }

    protected function tearDown(): void
    {
        if (isset($this->index) && $this->index->exists()) {
            $this->index->delete();
        }
    }

    /**
     * Build the index from the real Elasticorn config so the test index cannot
     * drift from the production mapping.
     *
     * @return array<string, mixed>
     */
    private function indexDefinition(): array
    {
        $dir = dirname(__DIR__, 2) . '/config/Elasticorn/docsearch';
        $settings = Yaml::parseFile($dir . '/IndexConfiguration.yaml');
        // Single-node test node: no replicas, so the index stays green.
        $settings['number_of_replicas'] = 0;

        return [
            'settings' => $settings,
            'mappings' => ['properties' => Yaml::parseFile($dir . '/Mapping.yaml')],
        ];
    }

    /**
     * A complete snippet with sensible defaults; override per test.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function snippet(array $overrides = []): array
    {
        return $overrides + [
            'fragment' => 'section-1',
            'snippet_title' => 'Title',
            'snippet_content' => 'Content',
            'manual_title' => 'acme/demo',
            'manual_vendor' => 'acme',
            'manual_extension' => 'demo',
            'manual_package' => 'acme/demo',
            'manual_type' => 'c',
            'manual_version' => 'main',
            'manual_language' => 'en-us',
            'manual_slug' => 'p/acme/demo/main/en-us',
            'manual_keywords' => ['demo'],
            'relative_url' => 'Index.html',
            'content_hash' => 'hash-1',
            'is_core' => false,
            'is_last_versions' => true,
        ];
    }

    /**
     * _source of every document matching a query (the index is refreshed first).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sources(AbstractQuery $query): array
    {
        $this->index->refresh();
        $search = (new Query($query))->setSize(100);

        return array_map(
            static fn ($result) => $result->getSource(),
            $this->index->search($search)->getResults()
        );
    }
}
