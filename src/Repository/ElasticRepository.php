<?php

namespace App\Repository;

use App\Dto\Constraints;
use App\Dto\Manual;
use App\Dto\SearchDemand;
use App\QueryBuilder\ElasticQueryBuilder;
use Elastica\Aggregation\Terms;
use Elastica\Client;
use Elastica\Exception\InvalidException;
use Elastica\Index;
use Elastica\Query;
use Elastica\Script\AbstractScript;
use Elastica\Script\Script;
use Elastica\Util;
use function Symfony\Component\String\u;

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

        $scriptCode = <<<EOD
if (!ctx._source.manual_version.contains(params.manual_version)) {
    ctx._source.manual_version.add(params.manual_version);
}
if (!ctx._source.major_versions.contains(params.major_version)) {
    ctx._source.major_versions.add(params.major_version);
}
EOD;
        $version = $snippet['manual_version'];
        $majorVersion = explode('.', $version)[0];

        $script = new Script($scriptCode);
        $script->setParam('manual_version', $version);
        $script->setParam('major_version', $majorVersion);
        $snippet['manual_version'] = [$version];
        $snippet['major_versions'] = [$majorVersion];

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
            'source' => $this->getDeleteQueryScript()
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

    public function suggest(SearchDemand $searchDemand): array
    {
        $searchTerms = Util::escapeTerm($searchDemand->getQuery());
        $query = [
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'query_string' => [
                                'query' => $searchTerms,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $search = $this->elasticIndex->createSearch($query);
        $search->getQuery()->setSize($this->perPage);
        $search->getQuery()->setFrom(0);

        $elasticaResultSet = $search->search();
        $results = $elasticaResultSet->getResults();

        return [
            'results' => $results,
        ];
    }

    /**
     * @return array
     * @throws InvalidException
     */
    public function findByQuery(SearchDemand $searchDemand): array
    {
        $searchTerms = Util::escapeTerm($searchDemand->getQuery());
        $query = [
            'query' => [
                'function_score' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'query_string' => [
                                        'query' => $searchTerms,
                                        'fields' => [
                                            'page_title^10',
                                            'snippet_title^20',
                                            'snippet_content',
                                            'manual_title'
                                        ]
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'functions' => [
                        [
                            'script_score' => [
                                'script' => [
                                    'source' => "int matchCount = 0; for (String term : params.terms) { if (doc['manual_keywords'].contains(term)) { matchCount++; } } return 10 * matchCount;",
                                    'params' => [
                                        'terms' => explode(' ', u($searchTerms)->trim()->toString())
                                    ]
                                ]
                            ]
                        ],
                        [
                            'filter' => [
                                // query matching core manual pages
                                'terms' => [
                                    'manual_type' => [
                                        \App\Config\ManualType::SystemExtension->value,
                                        \App\Config\ManualType::Typo3Manual->value,
                                        \App\Config\ManualType::CoreChangelog->value,
                                    ],
                                ],
                            ],
                            'weight' => 5
                        ],
                        [
                            'filter' => [
                                // query matching recent version
                                'terms' => ['manual_version' => ['main', '12.4', '11.5']]
                            ],
                            'weight' => 5
                        ],
                    ],
                    'score_mode' => 'sum',
                    'boost_mode' => 'multiply'
                ],
            ],
            'highlight' => [
                'fields' => [
                    'snippet_content' => [
                        'fragment_size' => 400,
                        'number_of_fragments' => 1
                    ]
                ],
                'encoder' => 'html'
            ]
        ];
        $filters = $searchDemand->getFilters();
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                $query['post_filter']['bool']['must'][] = ['terms' => [$key => $value]];
            }
        }

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
