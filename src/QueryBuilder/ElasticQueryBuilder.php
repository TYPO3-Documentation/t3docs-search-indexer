<?php

declare(strict_types=1);

namespace App\QueryBuilder;

use App\Dto\Constraints;
use Elastica\Query;

class ElasticQueryBuilder
{
    public function buildQuery(Constraints $constraints): Query
    {
        $query = ['bool' => ['must' => []]];

        if ($constraints->getSlug() !== '') {
            $query['bool']['must'][] = ['match' => ['manual_slug' => $constraints->getSlug()]];
        }

        if ($constraints->getVersion() !== '') {
            $query['bool']['must'][] = ['match' => ['manual_version' => $constraints->getVersion()]];
        }

        if ($constraints->getType() !== '') {
            $query['bool']['must'][] = ['match' => ['manual_type' => $constraints->getType()]];
        }

        if ($constraints->getLanguage() !== '') {
            $query['bool']['must'][] = ['match' => ['manual_language' => $constraints->getLanguage()]];
        }

        return new Query(['query' => $query]);
    }
}
