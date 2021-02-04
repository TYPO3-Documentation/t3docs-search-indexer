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


Usage
-----

Index docs
^^^^^^^^^^

* Start elasticsearch.

* Run ``./bin/console docsearch:import`` to index all documentations from ``_docs``
  folder.

* Open ``http://localhost:9200/docsearch_english_a/_search?q=*:*`` to see indexed
  documentations.

*  php -S 127.0.0.1:8081 -t ./public

