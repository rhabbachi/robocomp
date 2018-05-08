---
project:
  name: amva

modes:
  - dev

domains:
  - dkan.docker

setup:
  - dkan-asset-dbsnapshot-import
  - dkan-asset-files-unpack
  - dkan-deploy

build:
  - dkan-asset-code-clone
  - dkan-asset-dbsnapshot-download
  - dkan-asset-files-download

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
    - GITHUB_REPO=github.com/angrycactus/dkan-amva.git

  dkan-db:
    - MYSQL_ROOT_PASSWORD=password
    - MYSQL_DATABASE=dkan
    - MYSQL_USER=dkan
    - MYSQL_PASSWORD=dkan
    - DB_1_PORT_3306_TCP_ADDR=dkan-mariadb
    - DB_1_ENV_MYSQL_USER=dkan
    - DB_1_ENV_MYSQL_PASSWORD=dkan
    - DB_1_ENV_MYSQL_DATABASE=dkan

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
