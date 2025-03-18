<?php

namespace App\Repository;

use App\Config\ManualType;
use App\Dto\Constraints;
use App\Dto\Manual;
use App\Dto\SearchDemand;
use App\Helper\SlugBuilder;
use App\QueryBuilder\ElasticQueryBuilder;
use Elastica\Aggregation\Terms;
use Elastica\Client;
use Elastica\Exception\InvalidException;
use Elastica\Index;
use Elastica\Multi\Search as MultiSearch;
use Elastica\Query;
use Elastica\Result;
use Elastica\Script\AbstractScript;
use Elastica\Script\Script;
use Elastica\Search;
use Elastica\Util;

use function Symfony\Component\String\u;

use T3Docs\VersionHandling\Typo3VersionMapping;

class ElasticRepository
{
    private const ELASTICA_DEFAULT_CONFIGURATION = [
        'host' => 'localhost',
        'port' => '9200',
        'path' => '',
        'transport' => 'Http',
        'index' => 'docsearch',
        'username' => '',
        'password' => '',
    ];

    private readonly Index $elasticIndex;

    private int $perPage = 10;

    private int $totalHits = 0;

    private readonly Client $elasticClient;

    public function __construct(private readonly ElasticQueryBuilder $elasticQueryBuilder)
    {
        $elasticConfig = $this->getElasticSearchConfig();

        if (!empty($elasticConfig['username']) && !empty($elasticConfig['password'])) {
            $elasticConfig['headers'] = [
                'Authorization' => 'Basic ' .
                    base64_encode($elasticConfig['username'] . ':' . $elasticConfig['password']) . '==',
            ];
        }

        $this->elasticClient = new Client($elasticConfig);
        $this->elasticIndex = $this->elasticClient->getIndex($elasticConfig['index']);
    }

    /**
     * @return Client
     */
    public function getElasticClient(): Client
    {
        return $this->elasticClient;
    }

    /**
     * @return Index
     */
    public function getElasticIndex(): Index
    {
        return $this->elasticIndex;
    }

    public function addOrUpdateDocument(array $snippet): void
    {
        // Generate id, without document version (snippet can be reused between versions)
        $urlFragment = str_replace('/', '-', $snippet['manual_title'] . '-' . $snippet['relative_url'] . '-' . $snippet['content_hash']);
        $documentId = $urlFragment . '-' . $snippet['fragment'];

        // Append versions in the array
        $scriptCode = <<<EOD
if (!(ctx._source.manual_slug instanceof List)) {
    ctx._source.manual_slug = [ctx._source.manual_slug];
}
if (!ctx._source.manual_slug.contains(params.manual_slug)) {
    ctx._source.manual_slug.add(params.manual_slug);
}

if (!ctx._source.manual_version.contains(params.manual_version)) {
    ctx._source.manual_version.add(params.manual_version);
}

for (int i = 0; i < params.major_versions.length; i++) {
    if (!ctx._source.major_versions.contains(params.major_versions[i])) {
        ctx._source.major_versions.add(params.major_versions[i]);
    }
}
EOD;
        $version = $snippet['manual_version'];
        $majorVersion = explode('.', $version)[0];

        // Add "last" as a version for facet filtering
        $majorVersions = [$majorVersion, 'all'];
        // Add "latest" version for LTS / two last version
        if ($snippet['is_last_versions']) {
            $majorVersions[] = 'latest';
        }

        $script = new Script($scriptCode);
        // For UPDATE
        $script->setParam('manual_version', $version);
        $script->setParam('manual_slug', $snippet['manual_slug']);
        $script->setParam('major_versions', $majorVersions);

        // For INSERT
        $snippet['manual_slug'] = [$snippet['manual_slug']];
        $snippet['manual_version'] = [$version];
        $snippet['major_versions'] = $majorVersions;

        $script->setUpsert($snippet);
        $this->elasticIndex->getClient()->updateDocument($documentId, $script, $this->elasticIndex->getName());
    }

    /**
     * Removes manual_version from all snippets and if it's the last version, remove the whole snippet
     */
    public function deleteByManual(Manual $manual): void
    {
        $query = [
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'manual_title.raw' => $manual->getTitle(),
                            ],
                        ],
                        [
                            'term' => [
                                'manual_version' => $manual->getVersion(),
                            ],
                        ],
                        [
                            'term' => [
                                'manual_type' => $manual->getType(),
                            ],
                        ],
                        [
                            'term' => [
                                'manual_language' => $manual->getLanguage(),
                            ],
                        ],
                    ],
                ],
            ],
            'source' => $this->getDeleteQueryScript(),
        ];
        $deleteQuery = new Query($query);
        $script = new Script($this->getDeleteQueryScript(), ['manual_version' => $manual->getVersion()], AbstractScript::LANG_PAINLESS);
        $this->elasticIndex->updateByQuery($deleteQuery, $script);
    }

    /**
     * @return int Number of deleted documents
     */
    public function deleteByConstraints(Constraints $constraints): int
    {
        $query = $this->elasticQueryBuilder->buildQuery($constraints);

        // If a specific manual version is provided, the goal is to remove only this version from
        // all associated snippets. In such cases, an update query is used instead of delete.
        // This approach ensures that if a snippet has no other versions remaining after the
        // removal of the specified one, the entire snippet is deleted. This deletion is
        // accomplished by setting ctx.op to "delete" in the provided script.
        if ($constraints->getVersion()) {
            $script = new Script($this->getDeleteQueryScript(), ['manual_version' => $constraints->getVersion()], AbstractScript::LANG_PAINLESS);
            $response = $this->elasticIndex->updateByQuery($query, $script, ['wait_for_completion' => true]);
        } else {
            $response = $this->elasticIndex->deleteByQuery($query, ['wait_for_completion' => true]);
        }

        return $response->getData()['total'];
    }

    /**
     * Provide elasticsearch script which removes version (provided in params) from a snippet
     * and if this is the last version assigned to snippet, it deletes the snippet from index (by setting ctx.op).
     *
     * @return string
     */
    protected function getDeleteQueryScript(): string
    {
        $script = <<<EOD
if (ctx._source.manual_version.contains(params.manual_version)) {
    for (int i=ctx._source.manual_version.length-1; i>=0; i--) {
        if (ctx._source.manual_version[i] == params.manual_version) {
            ctx._source.manual_version.remove(i);
        }
    }
    
    for (int i=ctx._source.manual_slug.length-1; i>=0; i--) {
        if (ctx._source.manual_slug[i].contains(params.manual_version)) {
            ctx._source.manual_slug.remove(i);
        }
    }
}

def majorVersionParam = params.manual_version.splitOnToken('.')[0];
def hasOtherWithSameMajorVersion = false;
for (def version : ctx._source.manual_version) {
    def majorVersion = version.splitOnToken('.')[0];
    if (majorVersion.equals(majorVersionParam)) {
        hasOtherWithSameMajorVersion = true;
        break;
    }
}
if (!hasOtherWithSameMajorVersion && ctx._source.major_versions.contains(majorVersionParam)) {
    ctx._source.major_versions.remove(ctx._source.major_versions.indexOf(majorVersionParam));
}

if (ctx._source.manual_version.size() == 0) {
    ctx.op = "delete";
}
EOD;
        return \str_replace("\n", ' ', $script);
    }

    public function suggestScopes(SearchDemand $searchDemand): array
    {
        $suggestions = [];
        $searchTerms = trim(Util::escapeTerm($searchDemand->getQuery()));

        if ($searchTerms === '') {
            return [];
        }

        $limitingScopes = [
            'manual_vendor' => [
                'removeIfField' => 'manual_package',
            ],
            'manual_package' => [
                'addTopHits' => true,
            ],
            'option' => [],
            'manual_version' => [
                'field' => 'major_versions',
            ],
        ];

        $multiSearch = new MultiSearch($this->elasticClient);

        foreach ($limitingScopes as $scope => $settings) {
            $searchValue = $searchDemand->getFilters()[$scope] ?? null;
            $search = $searchTerms;

            $removeFromSuggestions = ($searchDemand->getFilters()[$settings['removeIfField'] ?? ''] ?? null) !== null;

            if ($searchValue !== null || $removeFromSuggestions) {
                continue;
            }

            $singleQuery = [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => $search,
                                'fields' =>
                                    [
                                        $settings['field'] ?? $scope,
                                        $scope,
                                        $scope . '.small_suggest',
                                        $scope . '.large_suggest',
                                    ],
                                'operator' => 'AND',
                            ],
                        ],
                    ],
                ],
                'highlight' => [
                    'fields' => [
                        $scope . '.small_suggest' => (object)[],
                        $scope . '.large_suggest' => (object)[],
                    ],
                ],
                'aggs' => [
                    $scope => [
                        'terms' => [
                            'field' => $settings['field'] ?? $scope,
                            'size' => 5,
                        ],
                    ],
                ],
                '_source' => false,
                'size' => 0,
            ];

            if ($settings['addTopHits'] ?? false) {
                $singleQuery['aggs'][$scope]['aggs']['manual_slug_hits'] = [
                    'top_hits' => [
                        'size' => 1,
                        '_source' => ['manual_version', 'manual_slug'],
                    ],
                ];
            }

            $searchObj = new Search($this->elasticClient);

            $filters = $searchDemand->getFilters();

            if (!empty($filters)) {
                foreach ($filters as $key => $value) {
                    if (is_array($value)) {
                        $singleQuery['query']['bool']['filter'][]['terms'][$key] = $value;
                    } else {
                        $singleQuery['query']['bool']['filter'][]['term'][$key] = $value;
                    }
                }
            }

            $searchObj
                ->setQuery($singleQuery);

            $multiSearch->addSearch($searchObj);
            $suggestions[$scope] = [];
        }

        if (count($suggestions) === count($limitingScopes)) {
            unset($suggestions['manual_version']);
        }

        if ($suggestions === []) {
            return [
                'time' => 0,
                'suggestions' => [],
            ];
        }

        $results = $multiSearch->search();
        $totalTime = 0;
        $expectedAggregations = array_keys($suggestions);

        foreach ($results as $resultSet) {
            $totalTime += $resultSet->getTotalTime();

            foreach ($resultSet->getAggregations() as $aggsName => $aggregation) {
                if (!in_array($aggsName, $expectedAggregations, true)) {
                    continue;
                }

                $suggestionsForCurrentQuery = [];

                foreach ($aggregation['buckets'] as $bucket) {
                    // Add URL on manual_package
                    if (isset($bucket['manual_slug_hits']['hits']['hits'][0])) {
                        $suggestionsForCurrentQuery[] = [
                            'slug' => SlugBuilder::build($bucket['manual_slug_hits']['hits']['hits'][0]['_source'], $searchDemand),
                            'title' => $bucket['key'],
                        ];
                    } else {
                        $suggestionsForCurrentQuery[] = ['title' => $bucket['key']];
                    }
                }

                if ($suggestionsForCurrentQuery === []) {
                    unset($suggestions[$aggsName]);
                    continue;
                }

                $suggestions[$aggsName] = $suggestionsForCurrentQuery;

                if ($searchDemand->areSuggestionsHighlighted()) {
                    $suggestions[$aggsName] = array_map(static function ($value) use ($searchTerms) {
                        return str_ireplace($searchTerms, '<em>' . $searchTerms . '</em>', $value);
                    }, $suggestions[$aggsName]);
                }
            }
        }

        return [
            'time' => $totalTime,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * @return array
     * @throws InvalidException
     */
    public function findByQuery(SearchDemand $searchDemand): array
    {
        $query = $this->getDefaultSearchQuery($searchDemand);

        $currentPage = $searchDemand->getPage();

        $search = $this->elasticIndex->createSearch($query);
        $search->getQuery()->setSize($this->perPage);
        $search->getQuery()->setFrom(($currentPage * $this->perPage) - $this->perPage);

        $this->addAggregations($search->getQuery());

        $elasticaResultSet = $search->search();
        $results = $elasticaResultSet->getResults();

        $maxScore = $elasticaResultSet->getMaxScore();
        $aggs = $elasticaResultSet->getAggregations();
        $aggs = $this->sortAggregations($aggs);

        $this->totalHits = $elasticaResultSet->getTotalHits();

        $out = [
            'pagesToLinkTo' => $this->getPages($currentPage),
            'currentPage' => $currentPage,
            'prev' => $currentPage - 1,
            'next' => $currentPage < ceil($this->totalHits / $this->perPage) ? $currentPage + 1 : 0,
            'totalResults' => $this->totalHits,
            'startingAtItem' => ($currentPage * $this->perPage) - ($this->perPage - 1),
            'endingAtItem' => $currentPage * $this->perPage,
            'results' => $results,
            'maxScore' => $maxScore,
            'aggs' => $aggs,
        ];
        if ($this->totalHits <= (int)$out['endingAtItem']) {
            $out['endingAtItem'] = $this->totalHits;
        }
        return $out;
    }

    /**
     * @return array{time: int, results: array<Result>}
     * @throws InvalidException
     */
    public function searchDocumentsForSuggest(SearchDemand $searchDemand): array
    {
        $query = $this->getDefaultSearchQuery($searchDemand);

        $search = $this->elasticIndex->createSearch($query);
        $search->getQuery()->setSize(5);
        $searchResults = $search->search();

        return [
            'time' => $searchResults->getTotalTime(),
            'results' => $searchResults->getResults(),
        ];
    }

    private function sortAggregations($aggregations, $direction = 'asc'): array
    {
        uksort($aggregations, function ($a, $b) {
            if ($a === 'Language') {
                return 1;
            }
            if ($b === 'Language') {
                return -1;
            }
            return strcasecmp($a, $b);
        });

        if ($direction === 'desc') {
            $aggregations = \array_reverse($aggregations);
        }

        // Move special "all" and "latest" to the top
        if (isset($aggregations['Version'])) {
            usort($aggregations['Version']['buckets'], function ($a, $b) {
                // Define the order of special keys
                $order = ['all' => 0, 'latest' => 1];
                if (isset($order[$a['key']]) && isset($order[$b['key']])) {
                    return $order[$a['key']] <=> $order[$b['key']];
                }

                if (isset($order[$a['key']])) {
                    return -1;
                }

                if (isset($order[$b['key']])) {
                    return 1;
                }

                return $b['doc_count'] <=> $a['doc_count'];
            });
        }

        return $aggregations;
    }

    private function addAggregations(Query $elasticaQuery): void
    {
        $catAggregation = new Terms('Document Type');
        $catAggregation->setField('manual_type');
        $elasticaQuery->addAggregation($catAggregation);

        $trackerAggregation = new Terms('Document');
        $trackerAggregation->setField('manual_title.raw');
        $catAggregation->addAggregation($trackerAggregation);

        $language = new Terms('Language');
        $language->setField('manual_language');
        $elasticaQuery->addAggregation($language);

        $option = new Terms('optionaggs');
        $option->setField('option');
        $elasticaQuery->addAggregation($option);

        $majorVersionsAgg = new Terms('Version');
        $majorVersionsAgg->setField('major_versions');
        $majorVersionsAgg->setSize(10);
        $elasticaQuery->addAggregation($majorVersionsAgg);
    }

    /**
     * @return array
     */
    protected function getPages($currentPage): array
    {
        $numPages = ceil($this->totalHits / $this->perPage);
        $i = 0;
        $maxPages = $numPages;
        if ($numPages > 15 && $currentPage <= 7) {
            $numPages = 15;
        }
        if ($currentPage > 7) {
            $i = $currentPage - 7;
            $numPages = $currentPage + 6;
        }
        if ($numPages > $maxPages) {
            $numPages = $maxPages;
            $i = $maxPages - 15;
        }

        if ($i < 0) {
            $i = 0;
        }

        $out = [];
        while ($i < $numPages) {
            $out[(int)$i] = ($i + 1);
            ++$i;
        }

        return $out;
    }

    private function getDefaultSearchQuery(SearchDemand $searchDemand): array
    {
        $searchTerms = Util::escapeTerm($searchDemand->getQuery());

        $query = [
            'query' => [
                'function_score' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                $searchTerms ? [
                                    'multi_match' => [
                                        'query' => $searchTerms,
                                        'type' => 'most_fields',
                                        'fields' => [
                                            'page_title^10',
                                            'snippet_title^10',
                                            'snippet_content^5',
                                            'manual_title',
                                            'keywords^4',
                                        ],
                                    ],
                                ] : ['match_all' => new \stdClass()],
                            ],
                            // Boost documents with ALL the terms
                            'should' => [
                                $searchTerms ? [
                                    'multi_match' => [
                                        'query' => $searchTerms,
                                        'operator' => 'and',
                                        'fields' => [
                                            'page_title^5',
                                            'snippet_content^5',
                                        ],
                                    ],
                                ] : ['match_all' => new \stdClass()],
                            ],
                        ],
                    ],
                    'functions' => [
                        [
                            'script_score' => [
                                'script' => [
                                    'source' => "int matchCount = 0; for (String term : params.terms) { if (doc['manual_keywords'].contains(term)) { matchCount++; } } return 10 * matchCount;",
                                    'params' => [
                                        'terms' => explode(' ', u($searchTerms)->trim()->toString()),
                                    ],
                                ],
                            ],
                        ],
                        [
                            'filter' => [
                                'term' => [
                                    'is_core' => true,
                                ],
                            ],
                            'weight' => 5,
                        ],
                        [
                            'filter' => [
                                // query matching stable major version
                                'term' => [
                                    'manual_version' => Typo3VersionMapping::Stable->getVersion(),
                                ],
                            ],
                            'weight' => 5,
                        ],
                        [
                            'filter' => [
                                // query matching main
                                'term' => [
                                    'manual_version' => Typo3VersionMapping::Dev->getVersion(),
                                ],
                            ],
                            'weight' => 5,
                        ],
                    ],
                    'score_mode' => 'sum',
                    'boost_mode' => 'multiply',
                ],
            ],
            'highlight' => [
                'fields' => [
                    'snippet_content' => [
                        'fragment_size' => 200,
                        'number_of_fragments' => 2,
                    ],
                ],
                'encoder' => 'html',
            ],
        ];

        $filters = $searchDemand->getFilters();

        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if (!is_array($value)) {
                    $value = [$value];
                }

                if ($key === 'major_versions') {
                    $boolVersion = [
                        'bool' => [
                            'should' => [
                                // Either the doc had ONLY version which is "main" (and no other),
                                [
                                    'bool' => [
                                        'filter' => [
                                            ['script' => [
                                                'script' => "doc['$key'].length == 1",
                                            ]],
                                            ['terms' => [$key => ['main']]],
                                        ],
                                    ],
                                ],
                                // Or it has the version requested.
                                ['terms' => [$key => $value]],
                                // Or it's a changelog.
                                ['term' => ['manual_type' => ['value' => ManualType::CoreChangelog->value]]],
                            ],
                        ],
                    ];
                    $query['post_filter']['bool']['must'][] = $boolVersion;

                    // Also boost on the main results
                    $query['query']['function_score']['functions'][] = [
                        'filter' => ['terms' => [$key => $value]],
                        'weight' => 10,
                    ];
                } else {
                    $query['post_filter']['bool']['must'][] = ['terms' => [$key => $value]];
                }
            }
        }

        return $query;
    }

    private function getElasticSearchConfig(): array
    {
        $config = [];
        $config['host'] = $_ENV['ELASTICA_HOST'] ?? self::ELASTICA_DEFAULT_CONFIGURATION['host'];
        $config['port'] = $_ENV['ELASTICA_PORT'] ?? self::ELASTICA_DEFAULT_CONFIGURATION['port'];
        $config['path'] = $_ENV['ELASTICA_PATH'] ?? self::ELASTICA_DEFAULT_CONFIGURATION['path'];
        $config['transport'] = $_ENV['ELASTICA_TRANSPORT'] ?? self::ELASTICA_DEFAULT_CONFIGURATION['transport'];
        $config['index'] = $_ENV['ELASTICA_INDEX'] ?? self::ELASTICA_DEFAULT_CONFIGURATION['index'];
        $config['username'] = $_ENV['ELASTICA_USERNAME'] ?? self::ELASTICA_DEFAULT_CONFIGURATION['username'];
        $config['password'] = $_ENV['ELASTICA_PASSWORD'] ?? self::ELASTICA_DEFAULT_CONFIGURATION['password'];

        return $config;
    }
}
