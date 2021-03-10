TYPO3 Documentation Search
==========================

Install
-------

* Run ``composer install`` to install all dependencies.

* Install elasticsearch version 5.3.x.

docker pull docker.elastic.co/elasticsearch/elasticsearch:5.5.3
docker run -p 9200:9200 -p 9300:9300 -e "discovery.type=single-node" -e "xpack.security.enabled=false" docker.elastic.co/elasticsearch/elasticsearch:5.5.3


* Install elasticorn: http://elasticorn.net/

* Create elasticsearch index via elasticorn:

  ``elasticorn.php index:init -c config/Elasticorn``

* Copy ``.env.dist`` to ``.env``

Configuration
-------

Configure assets
^^^^^^^^^^

* Assets configuration is located in ``services.yml`` file in ``assets`` section

* For rendering assets in template use Twig function ``{{ render_assets($assetType, $assetLocation) | raw }}`` where $assetType is ``js`` or ``css``
and ``$assetLocation`` is ``header`` or ``footer``

* You can define assets for ``header`` and ``footer`` parts of templates in in ``services.yml`` file in ``assets``

Usage
-----
Common instructions for docsearch indexer
^^^^^^^^^^

* Docsearch indexer configuration is keep in the `services.yml` file in `docsearch` section.

* You can configure 2 kinds of directories:

    * allowed_directories - directories which should be indexed by Indexer

    * excluded_directories - directories which should be ignored by Indexer

Index docs
^^^^^^^^^^

* Start elasticsearch.

* Run ``./bin/console docsearch:import`` to index all documentations from ``_docs``
  folder.

* Open ``http://localhost:9200/docsearch_english_a/_search?q=*:*`` to see indexed
  documentations.

* php -S 127.0.0.1:8081 -t ./public

Index single manual
^^^^^^^^^^

* Start elasticsearch.

* Run ``./bin/console docsearch:import:single-manual`` and, following the instructions on screen, select
manual which should be indexed (selected from the docsearch configuration)

* Open ``http://localhost:9200/docsearch_english_a/_search?q=*:*`` to see indexed
  documentations.

* php -S 127.0.0.1:8081 -t ./public


