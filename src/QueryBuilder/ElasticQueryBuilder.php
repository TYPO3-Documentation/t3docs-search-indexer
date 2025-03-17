<?php

declare(strict_types=1);

namespace App\QueryBuilder;

use App\Config\ManualType;
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

        if ($constraints->getPackage() !== '') {
            $query['bool']['must'][] = ['match' => ['manual_package' => $constraints->getPackage()]];
        }

        if ($constraints->getVersion() !== '') {
            $query['bool']['must'][] = ['match' => ['manual_version' => $constraints->getVersion()]];
        }

        if ($constraints->getType() !== '') {
            $type = $constraints->getType();
            $map = ManualType::getMap();
            if (isset($map[$constraints->getType()])) {
                $type = $map[$constraints->getType()];
            }

            $query['bool']['must'][] = ['match' => ['manual_type' => $type]];
        }

        if ($constraints->getLanguage() !== '') {
            $query['bool']['must'][] = ['match' => ['manual_language' => $constraints->getLanguage()]];
        }

        return new Query(['query' => $query]);
    }
}
