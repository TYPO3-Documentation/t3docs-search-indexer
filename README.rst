TYPO3 Documentation Search
==========================

- Parse HTML rendered documentation to index each `<section>` in Elasticsearch
- Provide frontend and API for end-user documentation search

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

Removing selected manuals from index
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

If you want to remove selected manuals from index, you can use chrome extensions or the command `docsearch:index:delete`.

.. code-block:: bash

  --manual-slug - slug of the manual to remove from index (full slug with locale)
  --manual-package - vendor and manual name (`typo3/reference-coreapi`) to remove from index
  --manual-version - version of the manual to remove from index
  --manual-type - type of the manual to remove from index
  --manual-language - language of the manual to remove from index

execute it with:

.. code-block:: bash

  # Remove version 9.5 from all extensions!
  ddev exec ./bin/console docsearch:index:delete --manual-version=9.5 --manual-type="System extension" --manual-language=en-us
  ddev exec ./bin/console docsearch:index:delete --manual-version=9.5 --manual-type=c

  # Remove `typo3/reference-coreapi` version 11.5 only
  ddev exec ./bin/console docsearch:index:delete --manual-type=c --manual-package=typo3/reference-coreapi --manual-version=11.5

.. note::
   If you set the ``--manual-version`` option, manuals with this version will be updated by removing
   selected version from the list, and if this version was the last one, only then the whole manual will be removed.

Indexing Core changelog
^^^^^^^^^^^^^^^^^^^^^^^

Core changelog is treated as a "sub manual" of the core manual. To index it, just run indexing for `cms-core` manual.

To avoid duplicates search is indexing Core changelog only from "main" version/branch of the core documentation.
E.g. when you run ``./bin/console docsearch:import c/typo3/cms-core/main/`` then the changelog for all versions will be indexed,
but if you run `./bin/console docsearch:import c/typo3/cms-core/12.4/` the changelog will NOT be indexed.

Excluded and ignored files and folders
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

There are several files and folders that are excluded from indexing by default.
You can find them in the ``services.yml`` file in the ``docsearch`` section.

If you want to exclude more files or folders, you can add them to the ``excluded_directories`` section.

There are also specific places in the code where files or folders are ignored.

Inside the ``Manual::getFilesWithSections()`` method, the Finder is configured to ignore several files and folders.
In the same place if teh indexed packages is ``typo3/cms-core`` the ``Changelog`` folder is excluded from indexing,\
as it wil be indexed as a part of the TYPO3 core manual (``see Manual::getSubManuals()`` for more details).

Since the ``typo3/cms-core`` is a special package for core manuals, only the manuals from the ``main`` versions should be indexed.\
TO achieve this the ``DirectoryFinderService::getFolderFilter() ... isNotIgnoredPath()`` method is used.
It wil check if the processed directory is a ``/c/typo3/cms-core/'`` and if the version is not ``main``, the whole directory (other version) will be ignored.

The ``ImportManualHTMLService::importSectionsFromManual()`` method will check if the file contains.\
``<meta name="x-typo3-indexer" content="noindex">`` meta tag. If such tag exists inside the file, such file will be ignored.

Run a Kibana instance
^^^^^^^^^^^^^^^^^^^^^

To get a local Kibana connected to your local Elasticsearch instance, you can run this Docker command:

.. code-block:: bash
  docker run -it --rm --name kib01 --net ddev_default -p 5601:5601 -e ELASTICSEARCH_HOSTS='["http://elasticsearch:9200/"]' docker.elastic.co/kibana/kibana:7.17.1

Then, open http://localhost:5601/app/dev_tools#/console to get the Dev Tools.

Running the tests / Fix CS
^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: bash
  ddev exec composer ci:test:unit
  ddev exec composer fix:php:cs-fixer
