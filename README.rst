TYPO3 Documentation Search
==========================

Install locally
---------------

* create `docs_server` folder (on the same level where cloned repository is)
  and put some documents inside

* install DDEV

* Run ``ddev start``

* Run ``ddev exec composer install`` to install all dependencies.

* Run ``ddev exec composer global require t3g/elasticorn:7.0.1`` to install Elasticorn

* Create elasticsearch index via Elasticorn:

  ``ddev exec  ~/.composer/vendor/bin/elasticorn.php index:init -c config/Elasticorn``

* Adapt ``DOCS_ROOT_PATH`` in your ``.env`` file if needed (see .env.dist for examples).

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
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

* Docsearch indexer configuration is keep in the `services.yml` file in `docsearch` section.

* You can configure 2 kinds of directories:

    * allowed_paths - regular expressions for paths which should be indexed by Indexer

    * excluded_directories - directories which should be ignored by Indexer

Index docs
^^^^^^^^^^

* Start elasticsearch.

* Run ``./bin/console docsearch:import`` to index all documentations from configured
  root path (DOCS_ROOT_PATH) folder (taking into account configured ``allowed_paths``
  and ``excluded_directories``).

* Open :samp:`https://t3docs-search-indexer.ddev.site:9201/docsearch_english_a/_search?q=*:*` to see indexed
  documents.

* enter :samp:`https://t3docs-search-indexer.ddev.site` to see application

Index single manual
^^^^^^^^^^^^^^^^^^^

* Run ``ddev exec ./bin/console docsearch:import <packagePath>`` where ``packagePath`` is
   a path to manual (or manuals) you want to import, relative to ``DOCS_ROOT_PATH``.
   This command doesn't check ``allowed_paths``, to ease usage when indexing single
   documentation folder from custom location (so you don't have to recreate folder
   structure from docs server).

* Open :samp:`https://t3docs-search-indexer.ddev.site:9201/docsearch_english_a/_search?q=*:*` to see indexed
  documents.
