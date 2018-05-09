---
project:
  name: medata

modes:
  - dev

domains:
  - dkan.docker

setup:
  dkan-asset-dbsnapshot-import:
    args:
      - ARG_SOURCE_DBSNAPSHOT=/tmp/dkan-asset-db-snapshots-vlm/dkan-medellin.prod.sql.gz
  dkan-asset-files-unpack:
    args:
      - ARG_SOURCE_TAR=/tmp/dkan-asset-files-snapshots-vlm/dkan-medellin.prod.files.tar.gz
  dkan-deploy:

build:
  dkan-asset-code-clone:

  dkan-asset-dbsnapshot-download:
    args:
      - ARG_SOURCE=s3://dkan-medellin/dkan-medellin.prod.sql.gz
      - ARG_DESTINATION=/tmp/dkan-asset-db-snapshots-vlm/dkan-medellin.prod.sql.gz

  dkan-asset-files-download:
    args:
      - ARG_SOURCE=s3://dkan-medellin/dkan-medellin.prod.files.tar.gz

volumes:
  dkan-asset-code-vlm:
    - user: 82
    - group: 82

  dkan-asset-db-vlm:
    - user: 82
    - group: 82

  dkan-asset-db-snapshots-vlm:
    - user: 100
    - group: 101

  dkan-asset-files-snapshots-vlm:
    - user: 82
    - group: 82

  dkan-asset-files-vlm:
    - user: 82
    - group: 82

env_files:
  dkan-php:
    - ENVIRONMENT=production
    - DKAN_ENV_SWITCH=production

  aws:
    - AWS_ACCESS_KEY_ID=AKIAJFQS7DXO2LJXQL4A
    - AWS_SECRET_ACCESS_KEY=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

  github:
    - GITHUB_USER=angrycactus-bot
    - GITHUB_AUTH_TOKEN=60bb29031fcf9993345ff94a5788fbd458d88f39
    - GITHUB_REPO=github.com/angrycactus/dkan-medellin.git

  dkan-db:
    - MYSQL_ROOT_PASSWORD=password
    - MYSQL_DATABASE=dkan
    - MYSQL_USER=dkan
    - MYSQL_PASSWORD=dkan
    - DB_1_PORT_3306_TCP_ADDR=dkan-mariadb
    - DB_1_ENV_MYSQL_USER=dkan
    - DB_1_ENV_MYSQL_PASSWORD=dkan
    - DB_1_ENV_MYSQL_DATABASE=dkan

  jwt:
    - OPENDATASTACK_DKAN_CONSUMER_JWT_KEY=dkan_opendatastack_kibana
    - OPENDATASTACK_DKAN_CONSUMER_JWT_SECRET=e71829c351aa4242c2719cbfbe671c09
    - DKAN_OPENDATASTACK_KIBANA_JWT_SECRET=e71829c351aa4242c2719cbfbe671c09

crontab:
  dkan-cron:
    comment: Run Cron on a dkan_starter site.
    schedule: "@every 3h"
    command: bash -c 'cd /var/www/html/docroot && drush cron'
    project: opendatastack-medellin
    container: dkan-php
    onstart: false

  dkan-harvest:
    comment: Run Harvest on a dkan_starter site.
    schedule: "@weekly"
    command: bash -c 'cd /var/www/html/docroot && drush php-eval \registry_rebuild();\ && drush dkan-h'
    project: opendatastack-medellin
    container: dkan-php
    onstart: false

  dkan-odsm:
    comment: Update ODSM data.json Cache on a dkan_starter site.
    schedule: "@daily"
    command: bash -c 'cd /var/www/html/docroot && drush odsm-filecache data_json_1_1 --yes && drush data-json-validate --yes'
    project: opendatastack-medellin
    container: dkan-php
    onstart: false

  dkan-index:
    comment: Update the search index on a dkan_starter site.
    schedule: "@daily"
    command: bash -c 'cd /var/www/html/docroot && drush search-api-index --yes'
    project: opendatastack-medellin
    container: dkan-php
    onstart: false

  dkan-fast-import:
    comment: Run Datastore Fast Import Queue on a dkan_starter site.
    schedule: "@every 3h"
    command: bash -c 'cd /var/www/html/docroot && drush queue-run dkan_datastore_fast_import_queue'
    project: opendatastack-medellin
    container: dkan-php
    onstart: false

_local:
  services:
    dkan-php:
      environment:
        - DKAN_OPENDATASTACK_KIBANA_SRC=http://kibana.dkan.docker/analytics

    dkan-nginx:
      networks:
        default:
          aliases:
            - dkan.docker
      labels:
        - 'traefik.port=80'
        - 'traefik.frontend.rule=Host:dkan.docker'

    kibana-gateway:
      networks:
        default:
          aliases:
            - kibana.dkan.docker
      labels:
        - 'traefik.port=8000'
        - 'traefik.frontend.rule=Host:kibana.dkan.docker'
