<?php

namespace App\Tests\Unit\Dto;

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
            ['q' => 'TCA', 'page' => '2', 'filters' => ['Document Type' => ['manual' => 'true']]]
        );

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame('TCA', $searchDemand->getQuery());
        $this->assertSame(2, $searchDemand->getPage());
        $this->assertSame([
            'manual_type' => ['manual']
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
        $this->assertSame(1, $searchDemand->getPage());
        $this->assertSame([], $searchDemand->getFilters());
    }

    /**
     * @test
     */
    public function createFromRequestWithEmptyFilters(): void
    {
        $request = Request::create('/search', 'GET', ['filters' => []]);

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame([], $searchDemand->getFilters());
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
                'Version' => ['12.4' => 'true', '11.5' => 'true'],
            ]]
        );

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame([
            'manual_type' => ['manual'],
            'manual_language' => ['en-us'],
            'manual_version' => ['12.4', '11.5'],
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
    public function createFromRequestWithInvalidFilter(): void
    {
        $request = Request::create(
            '/search',
            'GET',
            ['filters' => ['Invalid Filter' => ['value' => 'true']]]
        );

        $searchDemand = SearchDemand::createFromRequest($request);

        $this->assertSame([], $searchDemand->getFilters());
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
}
