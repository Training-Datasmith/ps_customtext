#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}"
PHAR_CACHE_DIR="/tmp/ps-customtext-tooling"
mkdir -p "${PHAR_CACHE_DIR}"
export PHPUNIT_PHAR_PATH="${PHAR_CACHE_DIR}/phpunit-4.8.36.phar"

PHP_IMAGE="php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143"
MARIADB_IMAGE="mariadb@sha256:3a7d3cbc8b6fddf66433d80dc124c1e4e75a73ebab9c6e137529cc270bdadfc0"
PHP_TAG="ps-customtext-php56-pdo"
MARIADB_NAME="ps_customtext_mariadb"

docker rm -f "${MARIADB_NAME}" >/dev/null 2>&1 || true

docker run -d --name "${MARIADB_NAME}" \
  -e MARIADB_ROOT_PASSWORD=root \
  -e MARIADB_DATABASE=ps_customtext_test \
  -e MARIADB_USER=ps_customtext \
  -e MARIADB_PASSWORD=ps_customtext \
  "${MARIADB_IMAGE}"

version=""
for _ in $(seq 1 60); do
  if docker exec "${MARIADB_NAME}" mariadb-admin ping -proot --silent >/dev/null 2>&1; then
    version="$(docker exec "${MARIADB_NAME}" mariadb -uroot -proot -N -e 'SELECT VERSION();' 2>/dev/null | tr -d '\r' || true)"
    if [ -n "${version}" ]; then
      break
    fi
  fi
  sleep 1
done
if [ -z "${version}" ]; then
  echo "MariaDB did not become ready" >&2
  exit 1
fi
if [[ "${version}" != 10.11.14* ]]; then
  echo "Unexpected MariaDB version: ${version}" >&2
  exit 1
fi

docker build -t "${PHP_TAG}" -f tests/docker/Dockerfile tests/docker

docker run --rm \
  -v "${ROOT}:/src" \
  -v "${PHAR_CACHE_DIR}:/phar-cache" \
  -w /src \
  -e PHPUNIT_PHAR_PATH=/phar-cache/phpunit-4.8.36.phar \
  "${PHP_TAG}" \
  bash tests/bin/verify-phpunit-phar.sh

mkdir -p /opt/cursor/artifacts

docker run --rm --network "container:${MARIADB_NAME}" \
  -v "${ROOT}:/src" \
  -v "${PHAR_CACHE_DIR}:/phar-cache" \
  -v /opt/cursor/artifacts:/opt/cursor/artifacts \
  -w /src \
  -e PS_CUSTOMTEXT_DB_HOST=127.0.0.1 \
  -e PS_CUSTOMTEXT_DB_PORT=3306 \
  -e PS_CUSTOMTEXT_DB_USER=ps_customtext \
  -e PS_CUSTOMTEXT_DB_PASSWORD=ps_customtext \
  -e PS_CUSTOMTEXT_DB_NAME=ps_customtext_test \
  -e PHPUNIT_PHAR_PATH=/phar-cache/phpunit-4.8.36.phar \
  -e GIT_BRANCH="$(git -C "${ROOT}" rev-parse --abbrev-ref HEAD)" \
  -e GIT_COMMIT="$(git -C "${ROOT}" rev-parse HEAD)" \
  "${PHP_TAG}" \
  bash -lc 'php -r "if (PHP_VERSION !== \"5.6.40\") { fwrite(STDERR, PHP_VERSION); exit(1);}" && bash tests/bin/run-gate.sh'

if [ "${RUN_MUTATIONS:-1}" = "1" ]; then
  docker run --rm --network "container:${MARIADB_NAME}" \
    -v "${ROOT}:/src" \
    -v /opt/cursor/artifacts:/opt/cursor/artifacts \
    -v "${PHAR_CACHE_DIR}:/phar-cache" \
    -w /src \
    -e PS_CUSTOMTEXT_DB_HOST=127.0.0.1 \
    -e PS_CUSTOMTEXT_DB_PORT=3306 \
    -e PS_CUSTOMTEXT_DB_USER=ps_customtext \
    -e PS_CUSTOMTEXT_DB_PASSWORD=ps_customtext \
    -e PS_CUSTOMTEXT_DB_NAME=ps_customtext_test \
    -e PHPUNIT_PHAR_PATH=/phar-cache/phpunit-4.8.36.phar \
    "${PHP_TAG}" \
    bash tests/bin/run-mutations.sh
fi

docker rm -f "${MARIADB_NAME}" >/dev/null 2>&1 || true
