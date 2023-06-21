TYPO3 Documentation Search
==========================

Install locally
---------------

* Clone this repo ``git clone https://github.com/TYPO3-Documentation/t3docs-search-indexer.git``

* Enter the `t3docs-search-indexer`` folder and run ``ddev start``

* Run ``ddev exec composer install`` to install all dependencies.

* Run ``ddev exec composer global require t3g/elasticorn:^7.0`` to install Elasticorn

* Create elasticsearch index via Elasticorn:

  ``ddev exec  ~/.composer/vendor/bin/elasticorn.php index:init -c config/Elasticorn``

* If necessary adapt ``DOCS_ROOT_PATH`` in your ``.env`` file if needed (see .env.dist for examples).
  DDEV environment has ``DOCS_ROOT_PATH=../docs_server/docs.typo3.org/Web`` set up by default, so usually
  you don't need to change it if you followed the folder structure.

* Create ``docs_server`` folder (on the same level where ``t3docs-search-indexer`` folder is)
  and put rendered documentation inside. This folder will be mounted inside DDEV under ``/var/www/docs_server``.
  You should have a structure like ``docs_server/docs.typo3.org/Web``

* Index documents as described below in "Usage" section

* Enjoy the local search under ``https://t3docs-search-indexer.ddev.site/``

Configuration
-------------

Configure assets
^^^^^^^^^^^^^^^^

* Assets configuration is located in ``services.yml`` file in ``assets`` section

* For rendering assets in template use Twig function ``{{ render_assets($assetType, $assetLocation) | raw }}`` where $assetType is ``js`` or ``css``
and ``$assetLocation`` is ``header`` or ``footer``

* You can define assets for ``header`` and ``footer`` parts of templates in in ``services.yml`` file in ``assets``

Usage
-----

Common instructions for docsearch indexer
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

* Docsearch indexer configuration is keep in the ``services.yml`` file in ``docsearch`` section.

* You can configure 2 kinds of directories:

    * allowed_paths - regular expressions for paths which should be indexed by Indexer

    * excluded_directories - directories which should be ignored by Indexer

Index docs
^^^^^^^^^^

* Start elasticsearch.

* Run ``./bin/console docsearch:import`` to index all documentations from configured
  root path (DOCS_ROOT_PATH) folder (taking into account configured ``allowed_paths``
  and ``excluded_directories``).

* Open `https://t3docs-search-indexer.ddev.site:9201/docsearch/_search?q=*:*` to see indexed
  documents.

* enter `https://t3docs-search-indexer.ddev.site` to see application

Index single manual
^^^^^^^^^^^^^^^^^^^

* Run ``ddev exec ./bin/console docsearch:import <packagePath>`` where ``packagePath`` is
   a path to manual (or manuals) you want to import, relative to ``DOCS_ROOT_PATH``.
   This command doesn't check ``allowed_paths``, to ease usage when indexing single
   documentation folder from custom location (so you don't have to recreate folder
   structure from docs server).
   e.g. ``ddev exec ./bin/console docsearch:import c/typo3/cms-felogin/12.4``
   to import EXT:felogin documentation for v12

* Open `https://t3docs-search-indexer.ddev.site:9201/docsearch_english_a/_search?q=*:*` to see indexed
  documents.

Removing index to start fresh
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

If you want to start with fresh Elasticsearch index locally, you can use chrome extensions
like `Elasticvue` to clear/drop Elasticsearch index if necessary.
