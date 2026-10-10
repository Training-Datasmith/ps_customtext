#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}"

PHPUNIT="${PHPUNIT_PHAR_PATH:-/tmp/phpunit-4.8.36.phar}"
RESULTS_FILE="/opt/cursor/artifacts/suite-results.md"
MIGRATE="${ROOT}/classes/MigrateData.php"
CUSTOM="${ROOT}/classes/CustomText.php"
MODULE="${ROOT}/ps_customtext.php"

# shellcheck source=tests/bin/file-restore-trap.sh
source "${ROOT}/tests/bin/file-restore-trap.sh"

{
  echo "## Revert-prove (empty-intersect guard)"
  echo ""
} >> "${RESULTS_FILE}"

revert_backup_dir="$(mktemp -d)"
trap_restore_init "${revert_backup_dir}"
trap_restore_register "${MIGRATE}"
sed -i '/if (empty($customTexts)) {/,+2d' "${MIGRATE}"
set +e
revert_out="$(php "${PHPUNIT}" -c phpunit.xml.dist tests/Unit/MigrateDataTest.php --filter testInsertDataNoActiveShopMatchReturnsTrueWithNoRows 2>&1)"
revert_code=$?
set -e
trap_restore_finish
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
  echo "## Mutation checks (RUN_MUTATIONS=1)"
  echo ""
} >> "${RESULTS_FILE}"

mutate_and_expect_failure() {
  local name="$1"
  local file="$2"
  local test_cmd="$3"
  local mutate_cmd="$4"
  local mutation_backup_dir
  mutation_backup_dir="$(mktemp -d)"
  trap_restore_init "${mutation_backup_dir}"
  trap_restore_register "${file}"
  eval "${mutate_cmd}"
  set +e
  output="$(eval "${test_cmd}" 2>&1)"
  code=$?
  set -e
  trap_restore_finish
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

mutate_and_expect_failure "mutation-remove-empty-intersect-guard" "${MIGRATE}" \
  "php \"${PHPUNIT}\" -c phpunit.xml.dist tests/Unit/MigrateDataTest.php --filter testInsertDataNoActiveShopMatchReturnsTrueWithNoRows" \
  "sed -i '/if (empty(\$customTexts)) {/,+2d' \"${MIGRATE}\""

mutate_and_expect_failure "mutation-upgrade-orphan-without-guard" "${MIGRATE}" \
  "php \"${PHPUNIT}\" -c phpunit.xml.dist tests/Unit/UpgradeModule300Test.php --filter testUpgradeOrphanShopReturnsTrueWithNoRows" \
  "sed -i '/if (empty(\$customTexts)) {/,+2d' \"${MIGRATE}\""

mutate_and_expect_failure "mutation-break-retrieve-join" "${MIGRATE}" \
  "php \"${PHPUNIT}\" -c phpunit.xml.dist tests/Unit/MigrateDataTest.php --filter testRetrieveOldDataMapsSchema" \
  "sed -i 's/il.\`id_lang\`/il.\`id_langx\`/' \"${MIGRATE}\""

mutate_and_expect_failure "mutation-definition-multilang" "${CUSTOM}" \
  "php \"${PHPUNIT}\" -c phpunit.xml.dist tests/Unit/CustomTextTest.php --filter testDefinitionContract" \
  "sed -i \"s/'multilang' => true/'multilang' => false/\" \"${CUSTOM}\""

mutate_and_expect_failure "mutation-widget-missing-row" "${MODULE}" \
  "php \"${PHPUNIT}\" -c phpunit.xml.dist tests/Unit/PsCustomtextWidgetTest.php --filter testGetWidgetVariablesFalseWhenMissing" \
  "sed -i '/\$idInfo === false/,+1s/return false/return array(\"cms_infos\" => array());/' \"${MODULE}\""

echo "Mutations completed (5 checks, production restored)" >> "${RESULTS_FILE}"
