---
project:
  name: &project_name myproject

mode: _dev

domains:
  - &domain dkan.docker

setup:
  dkan-asset-dbsnapshot-import:
    args:
      - ARG_SOURCE_DBSNAPSHOT=
  dkan-asset-files-unpack:
    args:
      - ARG_SOURCE_TAR=
  dkan-deploy:

build:
  dkan-asset-code-clone:

  dkan-asset-dbsnapshot-download:
    args:
      - ARG_SOURCE=
      - ARG_DESTINATION=

  dkan-asset-files-download:
    args:
      - ARG_SOURCE=

volumes:
  dkan-asset-code-vlm:
    user: 82
    group: 82

  dkan-asset-db-vlm:
    user: 82
    group: 82

  dkan-asset-db-snapshots-vlm:
    user: 100
    group: 101

  dkan-asset-files-snapshots-vlm:
    user: 82
    group: 82

  dkan-asset-files-vlm:
    user: 82
    group: 82

env_files:
  dkan-php:
    # 'local' => 'local',
    # 'dev' => 'development',
    # 'test' => 'test',
    # 'live' => 'production',
    # 'prod' => 'production',
    # 'ra' => 'production',
    - ENVIRONMENT=
    - DKAN_ENV_SWITCH=
    - PHP_SENDMAIL_PATH=/usr/sbin/sendmail -t -i -S mailhog:1025

  aws:
    - AWS_ACCESS_KEY_ID=
    - AWS_SECRET_ACCESS_KEY=
    - ARG_HOST=
    - ARG_HOST_BUCKET=

  github:
    - GITHUB_USER=
    - GITHUB_AUTH_TOKEN=
    - GITHUB_REPO=

  dkan-db:
    - MYSQL_ROOT_PASSWORD=password
    - MYSQL_DATABASE=dkan
    - MYSQL_USER=dkan
    - MYSQL_PASSWORD=dkan
    - DB_1_PORT_3306_TCP_ADDR=dkan-mariadb
    - DB_1_ENV_MYSQL_USER=dkan
    - DB_1_ENV_MYSQL_PASSWORD=dkan
    - DB_1_ENV_MYSQL_DATABASE=dkan

crontab:
  dkan-cron:
    comment: Run Cron on a dkan_starter site.
    schedule: "@every 3h"
    command: bash -c 'cd /var/www/html/docroot && drush cron'
    project: *project_name
    container: dkan-php
    onstart: false

  dkan-harvest:
    comment: Run Harvest on a dkan_starter site.
    schedule: "@weekly"
    command: bash -c 'cd /var/www/html/docroot && drush php-eval \registry_rebuild();\ && drush dkan-h'
    project: *project_name
    container: dkan-php
    onstart: false

  dkan-odsm:
    comment: Update ODSM data.json Cache on a dkan_starter site.
    schedule: "@daily"
    command: bash -c 'cd /var/www/html/docroot && drush odsm-filecache data_json_1_1 --yes && drush data-json-validate --yes'
    project: *project_name
    container: dkan-php
    onstart: false

  dkan-index:
    comment: Update the search index on a dkan_starter site.
    schedule: "@daily"
    command: bash -c 'cd /var/www/html/docroot && drush search-api-index --yes'
    project: *project_name
    container: dkan-php
    onstart: false

  dkan-fast-import:
    comment: Run Datastore Fast Import Queue on a dkan_starter site.
    schedule: "@every 3h"
    command: bash -c 'cd /var/www/html/docroot && drush queue-run dkan_datastore_fast_import_queue'
    project: *project_name
    container: dkan-php
    onstart: false

_local:
  services:

    dkan-nginx:
      networks:
        default:
          aliases:
            - dkan.docker
      labels:
        - 'traefik.port=80'
        - "traefik.frontend.rule=HostRegexp:{catchall:.*}"
        - "traefik.frontend.priority=1"
