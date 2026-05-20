<?php

namespace App\Tests\Unit\Dto;

use App\Config\ManualType;
use App\Dto\SearchDemand;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;

class SearchDemandTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function createFromRequestWithAllParameters(): void
    {
        $request = Request::create(
            '/search',
            'GET',
            ['q' => 'TCA', 'scope' => 'p/vendor/package/main/en-us', 'page' => '2', 'filters' => ['Document Type' => ['manual' => 'true']]]
        );

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame('TCA', $searchDemand->getQuery());
        $this->assertSame('p/vendor/package/main/en-us', $searchDemand->getScope());
        $this->assertSame(2, $searchDemand->getPage());
        $this->assertSame([
            'manual_type' => ['manual'],
            'manual_slug' => ['p/vendor/package/main/en-us'],
            'major_versions' => ['latest']
        ], $searchDemand->getFilters());
    }

    /**
     * @test
     */
    public function createFromRequestWithDefaultValues(): void
    {
        $request = Request::create('/search');

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame('', $searchDemand->getQuery());
        $this->assertSame('', $searchDemand->getScope());
        $this->assertSame(1, $searchDemand->getPage());
        $this->assertSame([
            'major_versions' => ['latest']
        ], $searchDemand->getFilters());
    }

    /**
     * @test
     */
    public function createFromRequestWithEmptyFilters(): void
    {
        $request = Request::create('/search', 'GET', ['filters' => []]);

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame([
            'major_versions' => ['latest']
        ], $searchDemand->getFilters());
    }

    /**
     * @test
     */
    public function createFromRequestWithMultipleFilters(): void
    {
        $request = Request::create(
            '/search',
            'GET',
            ['filters' => [
                'Document Type' => ['manual' => 'true'],
                'Invalid Filter' => ['value' => 'true'],
                'Language' => ['en-us' => 'true', 'de-de' => 'false'],
                'Version' => ['12' => 'true', '11' => 'true'],
            ]]
        );

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame([
            'manual_type' => ['manual'],
            'manual_language' => ['en-us'],
            'major_versions' => [12, 11],
        ], $searchDemand->getFilters());
    }

    /**
     * @test
     */
    public function createFromRequestWithSpecialCharactersInQuery(): void
    {
        $request = Request::create('/search', 'GET', ['q' => 'test+query+%26+%22something+more%22']);

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame('test+query+%26+%22something+more%22', $searchDemand->getQuery());
    }

    /**
     * @test
     */
    public function createFromRequestWithSpecialCharactersInScope(): void
    {
        $request = Request::create('/search', 'GET', ['scope' => 'p/news/news+%26+%22something+more%22/main/en-us']);

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame('p/news/news+%26+%22something+more%22/main/en-us', $searchDemand->getScope());
    }

    /**
     * @test
     */
    public function createFromRequestWithInvalidFilter(): void
    {
        $request = Request::create(
            '/search',
            'GET',
            ['filters' => ['Invalid Filter' => ['value' => 'true']]]
        );

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame([
            'major_versions' => ['latest']
        ], $searchDemand->getFilters());
    }

    /**
     * @test
     */
    public function createFromRequestWithNegativePageNumber(): void
    {
        $request = Request::create('/search', 'GET', ['page' => -1]);

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame(1, $searchDemand->getPage());
    }

    /**
     * @test
     */
    public function createFromRequestWithPageNumberAsString(): void
    {
        $request = Request::create('/search', 'GET', ['page' => '3']);

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame(3, $searchDemand->getPage());
    }

    /**
     * Regression test for issue #128: searching from the Core Changelog
     * landing page sends scope=/c/typo3/cms-core/main/en-us/ (without the
     * "Changelog" segment). The detection must still recognise this as a
     * changelog scope and apply the Core changelog type filter instead of
     * the (never-matching) slug filter.
     *
     * @test
     */
    public function createFromRequestUsesChangelogTypeFilterForCmsCoreManualRoot(): void
    {
        $request = Request::create(
            '/search',
            'GET',
            ['q' => 'form', 'scope' => '/c/typo3/cms-core/main/en-us/']
        );

        $searchDemand = SearchDemand::createFromRequest($request);

        $filters = $searchDemand->getFilters();
        $this->assertSame(ManualType::CoreChangelog->value, $filters['manual_type'] ?? null);
        $this->assertArrayNotHasKey('manual_slug', $filters);
    }

    /**
     * Regression test for PR #108 / issue #89: the original fix for the deep
     * Changelog scope must keep working.
     *
     * @test
     */
    public function createFromRequestUsesChangelogTypeFilterForDeepChangelogScope(): void
    {
        $request = Request::create(
            '/search',
            'GET',
            ['q' => 'feature', 'scope' => '/c/typo3/cms-core/main/en-us/Changelog/12.4/']
        );

        $searchDemand = SearchDemand::createFromRequest($request);

        $filters = $searchDemand->getFilters();
        $this->assertSame(ManualType::CoreChangelog->value, $filters['manual_type'] ?? null);
        $this->assertArrayNotHasKey('manual_slug', $filters);
    }

    /**
     * Other system-extension scopes (e.g. cms-form) must continue to use the
     * slug-based filter and not be misclassified as Core changelog.
     *
     * @test
     */
    public function createFromRequestUsesSlugFilterForNonCmsCoreScope(): void
    {
        $request = Request::create(
            '/search',
            'GET',
            ['q' => 'form', 'scope' => '/c/typo3/cms-form/main/en-us/']
        );

        $searchDemand = SearchDemand::createFromRequest($request);

        $filters = $searchDemand->getFilters();
        $this->assertSame(['c/typo3/cms-form/main/en-us'], $filters['manual_slug'] ?? null);
        $this->assertArrayNotHasKey('manual_type', $filters);
    }
}
