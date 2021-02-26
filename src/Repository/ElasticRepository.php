<?php

/**
 * Created by PhpStorm.
 * User: mathiasschreiber
 * Date: 15.01.18
 * Time: 20:53
 */

namespace App\Repository;

use App\Dto\Manual;
use Elastica\Aggregation\Terms;
use Elastica\Client;
use Elastica\Document;
use Elastica\Index;
use Elastica\Query;
use Symfony\Component\Config\Definition\Exception\Exception;

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

    private $currentPage = 1;

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
        // Generate id
        $urlFragment = str_replace('/', '-', $snippet['manual_slug'] . '-' . $snippet['relative_url']);
        $document = new Document($urlFragment . '-' . $snippet['fragment']);
        $document->setData($snippet);
        $snippetType = $this->elasticIndex->getType('snippet');
        $snippetType->addDocument($document);
    }

    public function deleteByManual(Manual $manual): void
    {
        $snippets = $this->elasticIndex->getType('snippet');
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
        ];
        $deleteQuery = new Query($query);
        $snippets->deleteByQuery($deleteQuery);
    }

    /**
     * @param string $searchTerms
     * @return array
     * @throws \Elastica\Exception\InvalidException
     */
    public function findByQuery(string $searchTerms): array
    {
        $searchTerms = $this->escape($searchTerms);
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
        if (array_key_exists('page', $_GET)) {
            $this->currentPage = (int)$_GET['page'];
        }
        #$usedFilters = $this->addFilters();
        #if (count($usedFilters) > 0) {
        #    $query['post_filter'] = $usedFilters;
        #}

        $search = $this->elasticIndex->createSearch($query);
        $search->addType('snippet');
        $search->getQuery()->setSize($this->perPage);
        $search->getQuery()->setFrom(($this->currentPage * $this->perPage) - $this->perPage);

        $this->addAggregations($search->getQuery());

        $elasticaResultSet = $search->search();
        $results = $elasticaResultSet->getResults();
        $maxScore = $elasticaResultSet->getMaxScore();
        $aggs = $elasticaResultSet->getAggregations();

        $this->totalHits = $elasticaResultSet->getTotalHits();

        $out = [
            'pagesToLinkTo' => $this->getPages(),
            'currentPage' => $this->currentPage,
            'prev' => $this->currentPage - 1,
            'next' => $this->currentPage < ceil($this->totalHits / $this->perPage) ? $this->currentPage + 1 : 0,
            'totalResults' => $this->totalHits,
            'startingAtItem' => ($this->currentPage * $this->perPage) - ($this->perPage - 1),
            'endingAtItem' => $this->currentPage * $this->perPage,
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
    protected function getPages(): array
    {
        $numPages = ceil($this->totalHits / $this->perPage);
        $i = 0;
        /*
         *
         */
        $maxPages = $numPages;
        if ($numPages > 15 && $this->currentPage <= 7) {
            $numPages = 15;
        }
        if ($this->currentPage > 7) {
            $i = $this->currentPage - 7;
            $numPages = $this->currentPage + 6;
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

    /**
     * Escape a value for special query characters such as ':', '(', ')', '*', '?', etc.
     * NOTE: inside a phrase fewer characters need escaped, use {@link Apache_Solr_Service::escapePhrase()} instead.
     *
     * @param string $value
     *
     * @return string
     */
    private function escape($value): string
    {
        //list taken from http://lucene.apache.org/java/docs/queryparsersyntax.html#Escaping%20Special%20Characters
        $pattern = '/(\+|-|&&|\|\||!|\(|\)|\{|}|\[|]|\^|"|~|\*|\?|:|\\\)/';
        $replace = '\\\$1';
        $escapedString = preg_replace($pattern, $replace, $value);
        $escapedString = str_replace('/', '\/', $escapedString);

        return $escapedString;
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
