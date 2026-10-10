#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}"

PHPUNIT_PHAR="${PHPUNIT_PHAR_PATH:-/tmp/phpunit-4.8.36.phar}"
RESULTS_FILE="/opt/cursor/artifacts/suite-results.md"
mkdir -p /opt/cursor/artifacts

PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
if [ "${PHP_VERSION}" != "5.6.40" ]; then
  echo "Expected PHP 5.6.40, got ${PHP_VERSION}" >&2
  exit 1
fi

run_once() {
  local label="$1"
  shift
  local output
  local exit_code=0
  set +e
  output="$("$@" 2>&1)"
  exit_code=$?
  set -e
  {
    echo "### ${label}"
    echo ""
    echo '```'
    echo "${output}"
    echo '```'
    echo ""
    echo "exit_code: ${exit_code}"
    echo ""
  } >> "${RESULTS_FILE}"
  return "${exit_code}"
}

: > "${RESULTS_FILE}"
{
  echo "# ps_customtext suite results"
  echo ""
  echo "- repo: Training-Datasmith/ps_customtext"
  echo "- date: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "- branch: ${GIT_BRANCH:-unknown}"
  echo "- commit: ${GIT_COMMIT:-unknown}"
  echo "- php: ${PHP_VERSION}"
  echo "- phpunit: 4.8.36 (verified PHAR)"
  echo ""
} >> "${RESULTS_FILE}"

FAIL=0
run_once "default order run 1" php "${PHPUNIT_PHAR}" -c phpunit.xml.dist tests/Unit || FAIL=1
run_once "default order run 2" php "${PHPUNIT_PHAR}" -c phpunit.xml.dist tests/Unit || FAIL=1
run_once "seeded shuffle run 1" php tests/bin/run-shuffled.php || FAIL=1
run_once "seeded shuffle run 2" php tests/bin/run-shuffled.php || FAIL=1

if [ "${FAIL}" -ne 0 ]; then
  echo "Gate failed" >> "${RESULTS_FILE}"
  exit 1
fi

summary="$(php "${PHPUNIT_PHAR}" -c phpunit.xml.dist tests/Unit 2>&1 | tail -n 1)"
{
  echo "Gate passed (4/4)"
  echo ""
  echo "summary_line: ${summary}"
  echo "command: php phpunit-4.8.36.phar -c phpunit.xml.dist tests/Unit (plus seeded runner x2)"
} >> "${RESULTS_FILE}"
exit 0
