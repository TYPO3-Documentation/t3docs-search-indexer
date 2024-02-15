<?php

declare(strict_types=1);

namespace App\Tests\Unit\QueryBuilder;

use App\Dto\Constraints;
use App\QueryBuilder\ElasticQueryBuilder;
use Elastica\Query;
use PHPUnit\Framework\TestCase;

class ElasticQueryBuilderTest extends TestCase
{
    /**
     * @test
     */
    public function buildQueryWithNoConstraints(): void
    {
        $constraints = new Constraints();
        $queryBuilder = new ElasticQueryBuilder();
        $query = $queryBuilder->buildQuery($constraints);

        $expectedQuery = new Query([
            'query' => [
                'bool' => [
                    'must' => []
                ]
            ]
        ]);

        $this->assertEquals($expectedQuery, $query);
    }

    /**
     * @test
     */
    public function buildQueryWithOnlySlugConstraint(): void
    {
        $constraints = new Constraints('test-slug');
        $queryBuilder = new ElasticQueryBuilder();
        $query = $queryBuilder->buildQuery($constraints);

        $expectedQuery = new Query([
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'match' => [
                                'manual_slug' => 'test-slug'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $this->assertEquals($expectedQuery, $query);
    }

    /**
     * @test
     */
    public function buildQueryWithOnlyVersionConstraint(): void
    {
        $constraints = new Constraints('', '12.04');
        $queryBuilder = new ElasticQueryBuilder();
        $query = $queryBuilder->buildQuery($constraints);

        $expectedQuery = new Query([
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'match' => [
                                'manual_version' => '12.04'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $this->assertEquals($expectedQuery, $query);
    }

    /**
     * @test
     */
    public function buildQueryWithOnlyTypeConstraint(): void
    {
        $constraints = new Constraints('', '', 'TYPO3 Manual');
        $queryBuilder = new ElasticQueryBuilder();
        $query = $queryBuilder->buildQuery($constraints);

        $expectedQuery = new Query([
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'match' => [
                                'manual_type' => 'TYPO3 Manual'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $this->assertEquals($expectedQuery, $query);
    }

    /**
     * @test
     */
    public function buildQueryWithOnlyLanguageConstraint(): void
    {
        $constraints = new Constraints('', '', '', 'en-us');
        $queryBuilder = new ElasticQueryBuilder();
        $query = $queryBuilder->buildQuery($constraints);

        $expectedQuery = new Query([
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'match' => [
                                'manual_language' => 'en-us'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $this->assertEquals($expectedQuery, $query);
    }

    /**
     * @test
     */
    public function buildQueryWithSomeConstraints(): void
    {
        $constraints = new Constraints('m/typo3/reference-coreapi/12.4/en-us', '', 'TYPO3 Manual', '');
        $queryBuilder = new ElasticQueryBuilder();
        $query = $queryBuilder->buildQuery($constraints);

        $expectedQuery = new Query([
            'query' => [
                'bool' => [
                    'must' => [
                        ['match' => ['manual_slug' => 'm/typo3/reference-coreapi/12.4/en-us']],
                        ['match' => ['manual_type' => 'TYPO3 Manual']],
                    ]
                ]
            ]
        ]);

        $this->assertEquals($expectedQuery, $query);
    }

    /**
     * @test
     */
    public function buildQueryWithAllConstraints(): void
    {
        $constraints = new Constraints('m/typo3/reference-coreapi/12.4/en-us', '12.4', 'TYPO3 Manual', 'en-us');
        $queryBuilder = new ElasticQueryBuilder();
        $query = $queryBuilder->buildQuery($constraints);

        $expectedQuery = new Query([
            'query' => [
                'bool' => [
                    'must' => [
                        ['match' => ['manual_slug' => 'm/typo3/reference-coreapi/12.4/en-us']],
                        ['match' => ['manual_version' => '12.4']],
                        ['match' => ['manual_type' => 'TYPO3 Manual']],
                        ['match' => ['manual_language' => 'en-us']]
                    ]
                ]
            ]
        ]);

        $this->assertEquals($expectedQuery, $query);
    }
}