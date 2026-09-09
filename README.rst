TYPO3 Documentation Search
==========================

- Parse HTML rendered documentation to index each ``<section>`` in Elasticsearch
- Provide frontend and API for end-user documentation search

The application requires PHP 8.2 and Elasticsearch 7.x. Locally, both are
provided by DDEV.

Install locally
---------------

* Clone this repo: ``git clone https://github.com/TYPO3-Documentation/t3docs-search-indexer.git``

* Create a ``docs_server`` folder (on the same level where the
  ``t3docs-search-indexer`` folder is) and put rendered documentation inside.
  You should end up with a structure like ``docs_server/docs.typo3.org/Web``.
  This folder is mounted inside DDEV under ``/var/www/docs_server``.

* Enter the ``t3docs-search-indexer`` folder and run ``ddev start``

* Run ``ddev exec composer install`` to install all dependencies.

* Run ``ddev exec composer global require t3g/elasticorn:^7.0`` to install Elasticorn

* Create the Elasticsearch index via Elasticorn:

  ``ddev exec ~/.composer/vendor/bin/elasticorn.php index:init -c config/Elasticorn``

* Copy ``.env.dist`` to ``.env`` (``.env`` is not in version control) and adapt
  it if needed. For DDEV, the relevant environment variables — including
  ``DOCS_ROOT_PATH`` — are set in ``.ddev/docker-compose.environment.yaml``
  instead, and ``DOCS_ROOT_PATH`` has to point at the ``Web`` folder of the
  mounted docs server, that is ``/var/www/docs_server/docs.typo3.org/Web``.
  Check that file first if the importer reports that it found no manuals.

* Index documents as described below in the "Usage" section

* Enjoy the local search under https://t3docs-search-indexer.ddev.site/

Configuration
-------------

Configure assets
^^^^^^^^^^^^^^^^

* Assets configuration is located in the ``config/services.yaml`` file in the
  ``assets`` section, separately for the ``header`` and ``footer`` parts of the
  templates.

* To render assets in a template, use the Twig function
  ``{{ render_assets(assetType, assetLocation) | raw }}``, where ``assetType``
  is ``js`` or ``css`` and ``assetLocation`` is ``header`` or ``footer``.

* The CSS and JS files of the documentation theme are pinned to one CDN
  version and have to be raised together.

Usage
-----

Common instructions for docsearch indexer
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

* The docsearch indexer configuration is kept in the ``config/services.yaml``
  file in the ``docsearch`` section.

* You can configure 2 kinds of directories:

    * allowed_paths - regular expressions for paths which should be indexed by Indexer

    * excluded_directories - directories which should be ignored by Indexer

* A directory is only recognized as a manual if it contains an ``objects.inv``
  (or ``objects.inv.json``) file, see
  ``DirectoryFinderService::objectsFileExists()``.

Index docs
^^^^^^^^^^

* Start Elasticsearch.

* Run ``ddev exec ./bin/console docsearch:import`` to index all documentations
  from the configured root path (``DOCS_ROOT_PATH``) folder, taking into account
  the configured ``allowed_paths`` and ``excluded_directories``.

* Open https://t3docs-search-indexer.ddev.site:9201/docsearch/_search?q=*:* to
  see indexed documents.

* Enter https://t3docs-search-indexer.ddev.site to see the application.

Index single manual
^^^^^^^^^^^^^^^^^^^

* Run ``ddev exec ./bin/console docsearch:import <packagePath>`` where
  ``packagePath`` is a path to the manual (or manuals) you want to import,
  relative to ``DOCS_ROOT_PATH``. This command doesn't check ``allowed_paths``,
  to ease usage when indexing a single documentation folder from a custom
  location (so you don't have to recreate the folder structure from the docs
  server), e.g. ``ddev exec ./bin/console docsearch:import c/typo3/cms-felogin/12.4``
  to import the EXT:felogin documentation for v12.

Removing index to start fresh
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

If you want to start with a fresh Elasticsearch index locally, you can use a
tool like Elasticvue to clear/drop the Elasticsearch index if necessary.

Removing selected manuals from index
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

If you want to remove selected manuals from the index, you can use such a tool
or the command ``docsearch:index:delete``, which accepts these options:

``--manual-slug``
    Slug of the manual to remove from index (full slug with locale)

``--manual-package``
    Vendor and manual name (``typo3/reference-coreapi``) to remove from index

``--manual-version``
    Version of the manual to remove from index

``--manual-type``
    Type of the manual to remove from index

``--manual-language``
    Language of the manual to remove from index

Execute it with:

.. code-block:: bash

   # Remove version 9.5 from all extensions!
   ddev exec ./bin/console docsearch:index:delete --manual-version=9.5 --manual-type="System extension" --manual-language=en-us
   ddev exec ./bin/console docsearch:index:delete --manual-version=9.5 --manual-type=c

   # Remove `typo3/reference-coreapi` version 11.5 only
   ddev exec ./bin/console docsearch:index:delete --manual-type=c --manual-package=typo3/reference-coreapi --manual-version=11.5

.. note::
   If you set the ``--manual-version`` option, manuals with this version will be updated by removing
   the selected version from the list, and only if this version was the last one, the whole manual
   will be removed.

Indexing Core changelog
^^^^^^^^^^^^^^^^^^^^^^^

The Core changelog is treated as a "sub manual" of the Core manual. To index it,
just run indexing for the ``cms-core`` manual.

To avoid duplicates, the search indexes the Core changelog only from the "main"
version/branch of the Core documentation. E.g. when you run
``./bin/console docsearch:import c/typo3/cms-core/main/`` then the changelog for
all versions will be indexed, but if you run
``./bin/console docsearch:import c/typo3/cms-core/12.4/`` the changelog will NOT
be indexed.

Excluded and ignored files and folders
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Several files and folders are excluded from indexing by default. You can find
them in the ``config/services.yaml`` file in the ``docsearch`` section. If you
want to exclude more files or folders, you can add them to the
``excluded_directories`` section.

There are also specific places in the code where files or folders are ignored:

* The ``Manual::getFilesWithSections()`` method configures the Finder to ignore
  several files and folders. In the same place, if the indexed package is
  ``typo3/cms-core``, the ``Changelog`` folder is excluded from indexing, as it
  will be indexed as a part of the TYPO3 Core manual (see
  ``Manual::getSubManuals()`` for more details).

* Since ``typo3/cms-core`` is a special package for Core manuals, only the
  manuals from the ``main`` version should be indexed. To achieve this, the
  ``DirectoryFinderService::getFolderFilter() ... isNotIgnoredPath()`` method is
  used. It checks whether the processed directory is below
  ``/c/typo3/cms-core/`` and, if the version is not ``main``, the whole
  directory (other version) is ignored.

* The ``ImportManualHTMLService::importSectionsFromManual()`` method checks
  whether a file contains the meta tag
  ``<meta name="x-typo3-indexer" content="noindex">``. If such a tag exists
  inside the file, the file is ignored.

Run a Kibana instance
^^^^^^^^^^^^^^^^^^^^^

To get a local Kibana connected to your local Elasticsearch instance, you can
run this Docker command:

.. code-block:: bash

   docker run -it --rm --name kib01 --net ddev_default -p 5601:5601 -e ELASTICSEARCH_HOSTS='["http://elasticsearch:9200/"]' docker.elastic.co/kibana/kibana:7.17.1

Then, open http://localhost:5601/app/dev_tools#/console to get the Dev Tools.

Running the tests / Fix CS
^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: bash

   ddev exec composer ci:test:unit
   ddev exec composer ci:php:lint
   ddev exec composer ci:php:cs-fixer
   ddev exec composer fix:php:cs-fixer

The first three are what the ``CI`` GitHub Actions workflow runs on every pull
request; ``fix:php:cs-fixer`` applies the coding standards to ``src/``.
