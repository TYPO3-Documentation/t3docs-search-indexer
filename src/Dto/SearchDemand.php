<?php

namespace App\Dto;

use Symfony\Component\HttpFoundation\Request;

readonly class SearchDemand
{
    public function __construct(
        private string $query,
        private string $scope,
        private int $page,
        private array $filters,
        private bool $suggestionsHighlighted = false
    ) {
    }

    public static function createFromRequest(Request $request): SearchDemand
    {
        $requestFilters = $request->query->all()['filters'] ?? [];
        $filters = [];

        if (!empty($requestFilters)) {
            $filterMap = [
                'document type' => [
                    'field' => 'manual_type',
                    'type' => 'array'
                ],
                'language' => [
                    'field' => 'manual_language',
                    'type' => 'array'
                ],
                'version' => [
                    'field' => 'major_versions',
                    'type' => 'array'
                ],
                'option' => [
                    'field' => 'option',
                    'type' => 'string'
                ],
                'sversion' => [
                    'field' => 'manual_version',
                    'type' => 'string'
                ],
                'vendor' => [
                    'field' => 'manual_vendor',
                    'type' => 'string'
                ],
                'package' => [
                    'field' => 'manual_package',
                    'type' => 'string'
                ],
                'core' => [
                    'field' => 'is_core',
                    'type' => 'bool'
                ],
            ];

            foreach ($requestFilters as $filter => $value) {
                $filter = strtolower($filter);

                if (!\array_key_exists($filter, $filterMap)) {
                    continue;
                }

                $searchField = $filterMap[$filter]['field'];
                $type = $filterMap[$filter]['type'] ?? null;

                switch ($type) {
                    case 'bool':
                        if (isset($value[1])) {
                            $filters[$searchField] = true;
                        } elseif (isset($value[0])) {
                            $filters[$searchField] = false;
                        }
                        break;
                    case 'string':
                        $filters[$searchField] = $value;
                        break;
                    case 'array':
                        if (!is_array($value)) {
                            break;
                        }

                        foreach ($value as $name => $state) {
                            if ($state === 'true') {
                                $filters[$searchField][] = $name;
                            }
                        }

                        break;
                }
            }
        }
        $page = (int)$request->query->get('page', '1');
        $query = $request->query->get('q', '');
        $areSuggestionsHighlighted = (bool)$request->query->get('suggest-highlight');

        // scope points to given manual version and language
        $scope = trim(htmlspecialchars(strip_tags((string)$request->query->get('scope'))), '/');
        if ($scope) {
            $filters['manual_slug'] = [$scope];
        }

        return new self($query, $scope, max($page, 1), $filters, $areSuggestionsHighlighted);
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

    public function areSuggestionsHighlighted(): bool
    {
        return $this->suggestionsHighlighted;
    }

    public function toArray(): array
    {
        return [
            'filters' => $this->getFilters(),
            'page' => $this->getPage(),
            'query' => $this->getQuery(),
            'scope' => $this->getScope(),
            'suggestionsHighlighted' => $this->areSuggestionsHighlighted(),
        ];
    }
}
