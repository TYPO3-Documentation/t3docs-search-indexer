TYPO3 Documentation Search
==========================

Install
-------

* Run ``composer install`` to install all dependencies.

* Install elasticsearch version 5.3.x.

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
