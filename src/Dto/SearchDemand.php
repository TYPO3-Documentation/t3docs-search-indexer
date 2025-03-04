<?php

namespace App\Dto;

use App\Config\ManualType;
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
        $filters = [];
        $requestFilters = $request->query->all()['filters'] ?? [];

        if (is_string($requestFilters)) {
            $requestFilters = json_decode($requestFilters);
        }

        if (!empty($requestFilters)) {
            $filterMap = SearchDemand::getFilterMap();

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
            // special treatment for the Changelog scope because version is "wrong" here
            // @see https://github.com/TYPO3-Documentation/t3docs-search-indexer/issues/89#issuecomment-2696410395
            if (str_contains($scope, 'c/typo3/cms-core/main/en-us/Changelog') && empty($filters['manual_type'])) {
                $filters['manual_type'] = ManualType::CoreChangelog->value;
            } else {
                $filters['manual_slug'] = [$scope];
            }
        }
        $vendor = trim(htmlspecialchars(strip_tags((string)$request->query->get('vendor'))), '/');
        if ($vendor) {
            $filters['manual_vendor'] = [$vendor];
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

    public function getSuggestScopes(): array
    {
        return [
            'manual_package',
            'manual_vendor',
            'manual_version',
            'option',
        ];
    }

    public static function getFilterMap(): array
    {
        return [
            'document type' => [
                'field' => 'manual_type',
                'type' => 'array',
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
            'optionaggs' => [
                'field' => 'option',
                'type' => 'array'
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
    }

    public function withFilterValueForLinkGeneration(string $key, string $value): array
    {
        $key = strtolower($key);

        $filters = $this->getFilters();

        $filtersMap = self::getFilterMap();
        $filtersMapKey = array_combine(array_column($filtersMap, 'field'), array_keys($filtersMap))[$key];
        $type = $filtersMap[$filtersMapKey]['type'];

        $filters = $this->removeValueFromFilterArray($filters, $type, $key, $value);
        return $this->processFiltersIntoGetParams($filters);
    }

    public function withoutFilterValueForLinkGeneration(string $key, string $value): array
    {
        $key = strtolower($key);

        $filters = $this->getFilters();
        if (($filters[$key] ?? '') === $value) {
            return [];
        }

        $filtersMap = self::getFilterMap();
        $filtersMapKey = array_combine(array_column($filtersMap, 'field'), array_keys($filtersMap))[$key] ?? null;

        if ($filtersMapKey !== null) {
            $type = $filtersMap[$filtersMapKey]['type'];

            $filters = $this->addValueToFilterArray($filters, $type, $key, $value);
        }

        $finalFilters = $this->processFiltersIntoGetParams($filters);

        if (array_key_exists($key, $filtersMap)) {
            $finalFilters[$key] = $value;
        }

        return $finalFilters;
    }

    protected function processFiltersIntoGetParams(array $filters): array
    {
        $result = [];
        $filtersMap = self::getFilterMap();

        foreach ($filters as $filterKey => $filterValue) {
            $filtersMapKey = array_combine(array_column($filtersMap, 'field'), array_keys($filtersMap))[$filterKey] ?? null;
            if ($filtersMapKey === null) {
                continue;
            }

            $varType = $filtersMap[$filtersMapKey]['type'];

            $result = $this->addValueToFilterArray($result, $varType, $filtersMapKey, $filterValue);
        }

        return $result;
    }

    protected function addValueToFilterArray(array $filters, string $valueType, string $key, mixed $value): array
    {
        if (is_array($value)) {
            foreach ($value as $filter) {
                switch ($valueType) {
                    case 'string':
                        $filters[$key] = $filter;
                        break;
                    case 'array':
                        $filters[$key][$filter] = 'true';
                        break;
                    case 'bool':
                        $filters[$key][$filter] = 1;
                        break;
                }
            }
        } else {
            $filter = (string)$value;

            switch ($valueType) {
                case 'string':
                    $filters[$key] = $filter;
                    break;
                case 'array':
                    $filters[$key][$filter] = 'true';
                    break;
                case 'bool':
                    $filters[$key][$filter] = 1;
                    break;
            }
        }

        return $filters;
    }

    protected function removeValueFromFilterArray(array $filters, string $valueType, string $key, mixed $value): array
    {
        $filter = (string)$value;

        switch ($valueType) {
            case 'string':
            case 'bool':
                unset($filters[$key]);
                break;
            case 'array':
                if (is_array($filters[$key] ?? null) && in_array($filter, $filters[$key])) {
                    unset($filters[$key][array_search($filter, $filters[$key], true)]);
                }
                break;
        }

        return $filters;
    }

    public function getCurrentFilters(): array
    {
        return $this->processFiltersIntoGetParams($this->getFilters());
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
