<?php

namespace App\Tests\Unit\Repository;

use App\Config\ManualType;
use App\Dto\SearchDemand;
use App\QueryBuilder\ElasticQueryBuilder;
use App\Repository\ElasticRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ElasticRepositoryTest extends TestCase
{
    /**
     * @test
     */
    public function scopeIsAppliedToTheMainQueryAsFilter(): void
    {
        $query = $this->buildQuery(['q' => 'cache', 'scope' => 'p/netresearch/nr-llm/main/en-us']);

        $this->assertContains(
            ['terms' => ['manual_slug' => ['p/netresearch/nr-llm/main/en-us']]],
            $this->mainQueryFilters($query)
        );
        $this->assertNotContains('manual_slug', $this->postFilterKeys($query));
        $this->assertCount(1, $query['query']['function_score']['query']['bool']['must']);
    }

    /**
     * @test
     */
    public function changelogScopeIsAppliedToTheMainQueryAsFilter(): void
    {
        $query = $this->buildQuery(['q' => 'cache', 'scope' => 'c/typo3/cms-core/main/en-us/Changelog/12.4']);

        $this->assertContains(
            ['terms' => ['manual_type' => [ManualType::CoreChangelog->value]]],
            $this->mainQueryFilters($query)
        );
        $this->assertNotContains('manual_type', $this->postFilterKeys($query));
    }

    /**
     * @test
     */
    public function changelogScopeSurvivesAFacetToggle(): void
    {
        $firstRequest = ['q' => 'cache', 'scope' => 'c/typo3/cms-core/main/en-us/Changelog/12.4'];
        $firstQuery = $this->buildQuery($firstRequest);

        // The facet form sends the scope, the checked buckets and the newly ticked one.
        $currentFilters = SearchDemand::createFromRequest(Request::create('/search', 'GET', $firstRequest))->getCurrentFilters();
        $currentFilters['version']['12.4'] = 'true';
        $secondQuery = $this->buildQuery($firstRequest + ['filters' => $currentFilters]);

        $this->assertSame($this->mainQueryFilters($firstQuery), $this->mainQueryFilters($secondQuery));
    }

    /**
     * @test
     */
    public function packageAndVendorFiltersAreAppliedToTheMainQueryAsFilter(): void
    {
        $query = $this->buildQuery([
            'q' => 'cache',
            'filters' => ['package' => 'typo3/cms-core', 'vendor' => 'typo3'],
        ]);

        $this->assertContains(['terms' => ['manual_package' => ['typo3/cms-core']]], $this->mainQueryFilters($query));
        $this->assertContains(['terms' => ['manual_vendor' => ['typo3']]], $this->mainQueryFilters($query));
        $this->assertNotContains('manual_package', $this->postFilterKeys($query));
        $this->assertNotContains('manual_vendor', $this->postFilterKeys($query));
    }

    /**
     * @test
     */
    public function selectedFacetStaysInThePostFilter(): void
    {
        $query = $this->buildQuery([
            'q' => 'cache',
            'filters' => ['Document Type' => [ManualType::SystemExtension->value => 'true']],
        ]);

        $this->assertContains('manual_type', $this->postFilterKeys($query));
        $this->assertSame([], $this->mainQueryFilters($query));
    }

    private function buildQuery(array $parameters): array
    {
        $searchDemand = SearchDemand::createFromRequest(Request::create('/search', 'GET', $parameters));
        $method = new \ReflectionMethod(ElasticRepository::class, 'getDefaultSearchQuery');

        return $method->invoke(new ElasticRepository(new ElasticQueryBuilder()), $searchDemand);
    }

    private function mainQueryFilters(array $query): array
    {
        return $query['query']['function_score']['query']['bool']['filter'] ?? [];
    }

    private function postFilterKeys(array $query): array
    {
        $keys = [];
        foreach ($query['post_filter']['bool']['must'] ?? [] as $clause) {
            if (isset($clause['terms'])) {
                $keys[] = array_key_first($clause['terms']);
            }
        }

        return $keys;
    }
}
