#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}"

PHPUNIT="${PHPUNIT_PHAR_PATH:-/tmp/phpunit-4.8.36.phar}"
RESULTS_FILE="/opt/cursor/artifacts/suite-results.md"
MIGRATE="${ROOT}/classes/MigrateData.php"
MIGRATE_BAK="$(mktemp)"
cp "${MIGRATE}" "${MIGRATE_BAK}"

{
  echo "## Revert-prove (empty-intersect guard)"
  echo ""
} >> "${RESULTS_FILE}"

cp "${ROOT}/classes/MigrateData.php" "${MIGRATE_BAK}"
sed -i '/if (empty($customTexts)) {/,+2d' "${ROOT}/classes/MigrateData.php"
set +e
revert_out="$(php "${PHPUNIT}" -c phpunit.xml.dist tests/Unit/MigrateDataTest.php --filter testInsertDataNoActiveShopMatchReturnsTrueWithNoRows 2>&1)"
revert_code=$?
set -e
cp "${MIGRATE_BAK}" "${ROOT}/classes/MigrateData.php"
{
  echo "without_guard_exit_code: ${revert_code}"
  echo '```'
  echo "${revert_out}" | tail -n 4
  echo '```'
  echo ""
} >> "${RESULTS_FILE}"
if [ "${revert_code}" -eq 0 ]; then
  echo "Revert-prove failed: tests passed without guard" >&2
  exit 1
fi

{
  echo "## Mutation checks"
  echo ""
} >> "${RESULTS_FILE}"

mutate_and_expect_failure() {
  local name="$1"
  local file="$2"
  local backup="$3"
  local test_cmd="$4"
  cp "${file}" "${backup}"
  eval "$5"
  set +e
  output="$(eval "${test_cmd}" 2>&1)"
  code=$?
  set -e
  cp "${backup}" "${file}"
  {
    echo "### ${name}"
    echo "exit_code: ${code}"
    echo '```'
    echo "${output}" | tail -n 8
    echo '```'
    echo ""
  } >> "${RESULTS_FILE}"
  if [ "${code}" -eq 0 ]; then
    echo "Mutation ${name} did not fail tests" >&2
    exit 1
  fi
}

CUSTOM="${ROOT}/classes/CustomText.php"
CUSTOM_BAK="$(mktemp)"
MODULE="${ROOT}/ps_customtext.php"
MODULE_BAK="$(mktemp)"

mutate_and_expect_failure "mutation-remove-empty-intersect-guard" "${MIGRATE}" "${MIGRATE_BAK}" \
  "php \"${PHPUNIT}\" -c phpunit.xml.dist tests/Unit/MigrateDataTest.php --filter testInsertDataNoActiveShopMatchReturnsTrueWithNoRows" \
  "sed -i '/if (empty(\$customTexts)) {/,+2d' \"${MIGRATE}\""

mutate_and_expect_failure "mutation-upgrade-orphan-without-guard" "${MIGRATE}" "${MIGRATE_BAK}" \
  "php \"${PHPUNIT}\" -c phpunit.xml.dist tests/Unit/UpgradeModule300Test.php --filter testUpgradeOrphanShopReturnsTrueWithNoRows" \
  "sed -i '/if (empty(\$customTexts)) {/,+2d' \"${MIGRATE}\""

mutate_and_expect_failure "mutation-break-retrieve-join" "${MIGRATE}" "${MIGRATE_BAK}" \
  "php \"${PHPUNIT}\" -c phpunit.xml.dist tests/Unit/MigrateDataTest.php --filter testRetrieveOldDataMapsSchema" \
  "sed -i 's/il.\`id_lang\`/il.\`id_langx\`/' \"${MIGRATE}\""

mutate_and_expect_failure "mutation-definition-multilang" "${CUSTOM}" "${CUSTOM_BAK}" \
  "php \"${PHPUNIT}\" -c phpunit.xml.dist tests/Unit/CustomTextTest.php --filter testDefinitionContract" \
  "sed -i \"s/'multilang' => true/'multilang' => false/\" \"${CUSTOM}\""

mutate_and_expect_failure "mutation-widget-missing-row" "${MODULE}" "${MODULE_BAK}" \
  "php \"${PHPUNIT}\" -c phpunit.xml.dist tests/Unit/PsCustomtextWidgetTest.php --filter testGetWidgetVariablesFalseWhenMissing" \
  "sed -i '/\$idInfo === false/,+1s/return false/return array(\"cms_infos\" => array());/' \"${MODULE}\""

rm -f "${MIGRATE_BAK}" "${CUSTOM_BAK}" "${MODULE_BAK}"
echo "Mutations completed (5 checks, production restored)" >> "${RESULTS_FILE}"
