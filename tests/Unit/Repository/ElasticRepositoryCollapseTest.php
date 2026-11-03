<?php

namespace App\Tests\Unit\Repository;

use App\Dto\SearchDemand;
use App\QueryBuilder\ElasticQueryBuilder;
use App\Repository\ElasticRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ElasticRepositoryCollapseTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['SEARCH_COLLAPSE_SECTIONS']);
    }

    /**
     * @test
     */
    public function collapseIsOffByDefault(): void
    {
        unset($_ENV['SEARCH_COLLAPSE_SECTIONS']);

        $query = $this->buildQuery();
        $this->assertArrayNotHasKey('collapse', $query);
        $this->assertArrayNotHasKey('section_count', $this->withSectionCount($query)['aggs'] ?? []);
    }

    /**
     * @test
     */
    public function collapseAndSectionCountAreAddedWhenEnabled(): void
    {
        $_ENV['SEARCH_COLLAPSE_SECTIONS'] = '1';

        $query = $this->buildQuery();
        $this->assertSame('snippet_id', $query['collapse']['field'] ?? null);
        $this->assertSame(
            'snippet_id',
            $this->withSectionCount($query)['aggs']['section_count']['aggs']['sections']['cardinality']['field'] ?? null
        );
    }

    /**
     * Aggregations ignore the post_filter, which holds the default "latest" version and every
     * ticked facet; the section count must apply it, or it counts sections the hits filter out.
     *
     * @test
     */
    public function sectionCountAppliesThePostFilter(): void
    {
        $_ENV['SEARCH_COLLAPSE_SECTIONS'] = '1';

        $query = $this->buildQuery(['q' => 'cache', 'filters' => ['Document Type' => ['TYPO3 manual' => 'true']]]);
        $this->assertNotEmpty($query['post_filter'] ?? null);

        $this->assertSame($query['post_filter'], $this->withSectionCount($query)['aggs']['section_count']['filter']);
    }

    /**
     * A document shared by several versions is updated, not inserted, on a re-import; the
     * update must write snippet_id as well.
     *
     * @test
     */
    public function upsertScriptWritesSnippetIdOnUpdate(): void
    {
        $method = new \ReflectionMethod(ElasticRepository::class, 'buildUpsertScript');
        $script = $method->invoke($this->repository(), [
            'manual_version' => '13.4',
            'manual_slug' => 'c/typo3/reference-coreapi/13.4/en-us',
            'is_last_versions' => true,
            'snippet_id' => 'TYPO3 Explained|TYPO3 manual|en-us|Index.html#intro',
        ]);

        $this->assertSame('TYPO3 Explained|TYPO3 manual|en-us|Index.html#intro', $script->getParam('snippet_id'));
        $this->assertStringContainsString('ctx._source.snippet_id = params.snippet_id', $script->getScript());
    }

    /**
     * @test
     */
    public function collapseStaysOffForAFalseValue(): void
    {
        $_ENV['SEARCH_COLLAPSE_SECTIONS'] = '0';

        $this->assertArrayNotHasKey('collapse', $this->buildQuery());
    }

    /**
     * The section count is not capped at Elasticsearch's 10,000-hit result window, so the
     * page links must be: page 1001 asks for from=10000 and Elasticsearch rejects it.
     *
     * @test
     */
    public function pageLinksStayInsideTheResultWindow(): void
    {
        $repository = $this->repository();
        (new \ReflectionProperty(ElasticRepository::class, 'totalHits'))->setValue($repository, 25000);
        $getPages = new \ReflectionMethod(ElasticRepository::class, 'getPages');

        $this->assertSame(1000, max($getPages->invoke($repository, 1000)));
        $this->assertSame(1000, max($getPages->invoke($repository, 2500)));
    }

    private function repository(): ElasticRepository
    {
        return new ElasticRepository(new ElasticQueryBuilder());
    }

    private function buildQuery(array $parameters = ['q' => 'cache']): array
    {
        $searchDemand = SearchDemand::createFromRequest(Request::create('/search', 'GET', $parameters));
        $method = new \ReflectionMethod(ElasticRepository::class, 'getDefaultSearchQuery');

        return $method->invoke($this->repository(), $searchDemand);
    }

    private function withSectionCount(array $query): array
    {
        $method = new \ReflectionMethod(ElasticRepository::class, 'withSectionCount');

        return $method->invoke($this->repository(), $query);
    }
}
