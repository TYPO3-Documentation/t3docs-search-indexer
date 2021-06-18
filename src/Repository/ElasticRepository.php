<?php

namespace App\Repository;

use App\Dto\Manual;
use App\Dto\SearchDemand;
use Elastica\Aggregation\Terms;
use Elastica\Client;
use Elastica\Document;
use Elastica\Index;
use Elastica\Query;
use Elastica\Script\AbstractScript;
use Elastica\Script\Script;
use Elastica\Util;

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

    /**
     * @var Index
     */
    private $elasticIndex;

    private $perPage = 10;

    private $totalHits = 0;

    /**
     * @var Client
     */
    private $elasticClient;

    public function __construct()
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

        $script = new Script('ctx._source.manual_version.add(params.manual_version)');
        $script->setParam('manual_version', $snippet['manual_version']);
        $snippet['manual_version'] = [$snippet['manual_version']];
        $script->setUpsert($snippet);
        $this->elasticIndex->getClient()->updateDocument($documentId, $script, $this->elasticIndex->getName());
    }

    /**
     * Removes manual_version from all snippets and if it's the last version, remove the whole snippet
     * @param Manual $manual
     */
    public function deleteByManual(Manual $manual): void
    {
        $query = [
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'manual_title' => $manual->getTitle(),
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
            'source' =>  $this->getDeleteQueryScript()
        ];
        $deleteQuery = new Query($query);
        $script = new Script($this->getDeleteQueryScript(), ['manual_version' => $manual->getVersion()], AbstractScript::LANG_PAINLESS);
        $this->elasticIndex->updateByQuery($deleteQuery, $script);
    }

    /**
     * Provide elasticsearch script which removes version (provided in params) from a snippet
     * and if this is the last version assigned to snippet, it deletes the snippet from index (by setting ctx.op).
     *
     * @return string
     */
    protected function getDeleteQueryScript(): string
    {
        $script =<<<EOD
if (ctx._source.manual_version.contains(params.manual_version)) {
   ctx._source.manual_version.remove(ctx._source.manual_version.indexOf(params.manual_version));
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

        $out = [
            'results' => $results,
        ];
        return $out;
    }

    /**
     * @param string $searchTerms
     * @return array
     * @throws \Elastica\Exception\InvalidException
     */
    public function findByQuery(SearchDemand $searchDemand): array
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

    private function sortAggregations($aggregations, $direction='asc'):array
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

    /**
     * @param Query $elasticaQuery
     */
    private function addAggregations(Query $elasticaQuery): void
    {
        $catAggregation = new Terms('Document Type');
        $catAggregation->setField('manual_type');
        $elasticaQuery->addAggregation($catAggregation);

        $trackerAggregation = new Terms('Document');
        $trackerAggregation->setField('manual_title');
        $catAggregation->addAggregation($trackerAggregation);

//        $status = new Terms('Status');
//        $status->setField('status.name');
//        $elasticaQuery->addAggregation($status);

//        $priority = new Terms('Priority');
//        $priority->setField('priority.name');
//        $elasticaQuery->addAggregation($priority);

        $language = new Terms('Language');
        $language->setField('manual_language');
        $elasticaQuery->addAggregation($language);

        $t3ver = new Terms('Version');
        $t3ver->setField('manual_version');
//        $t3ver->setSize(50);
        $elasticaQuery->addAggregation($t3ver);

//
//        $targetver = new Terms('Target Version');
//        $targetver->setField('fixed_version.name');
//        $elasticaQuery->addAggregation($targetver);

//        $phpVer = new Terms('PHP Version');
//        $phpVer->setField('php_version');
//        $elasticaQuery->addAggregation($phpVer);
    }

    /**
     * @return array
     */
    protected function getPages($currentPage): array
    {
        $numPages = ceil($this->totalHits / $this->perPage);
        $i = 0;
        /*
         *
         */
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
