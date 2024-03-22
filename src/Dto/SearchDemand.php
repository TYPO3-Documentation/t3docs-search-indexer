<?php

namespace App\Dto;

use Symfony\Component\HttpFoundation\Request;

readonly class SearchDemand
{
    public function __construct(private string $query, private string $scope, private int $page, private array $filters)
    {
    }

    public static function createFromRequest(Request $request): SearchDemand
    {
        $requestFilters = $request->query->all()['filters'] ?? [];
        $filters = [];
        if (!empty($requestFilters)) {
            foreach ($requestFilters as $filter => $value) {
                $filterMap = [
                    'Document Type' => 'manual_type',
                    'Language' => 'manual_language',
                    'Version' => 'major_versions',
                ];
                if (!\array_key_exists($filter, $filterMap)) {
                    continue;
                }
                foreach ($value as $name => $state) {
                    if ($state === 'true') {
                        $filters[$filterMap[$filter]][] = $name;
                    }
                }
            }
        }
        $page = (int)$request->query->get('page', '1');
        $query = $request->query->get('q', '');

        // scope points to given manual version and language
        $scope = trim(htmlspecialchars(strip_tags((string)$request->query->get('scope'))), '/');
        if ($scope) {
            $filters['manual_slug'] = [$scope];
        }

        return new self($query, $scope, max($page, 1), $filters);
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }
}
