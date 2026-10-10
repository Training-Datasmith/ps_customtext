#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}"
RESULTS_FILE="/opt/cursor/artifacts/suite-results.md"
PHPUNIT="${PHPUNIT_PHAR_PATH:-/tmp/phpunit-4.8.36.phar}"

{
  echo "## Stage 4b requirement proofs"
  echo ""
} >> "${RESULTS_FILE}"

# Missing pinned file must fail shuffled runner
missing="${ROOT}/tests/Unit/PsCustomtextWidgetTest.php"
bak="$(mktemp)"
cp "${missing}" "${bak}"
rm -f "${missing}"
set +e
missing_out="$(php tests/bin/run-shuffled.php 2>&1)"
missing_code=$?
set -e
mv "${bak}" "${missing}"
{
  echo "### delete-pinned-file-shuffle"
  echo "exit_code: ${missing_code}"
  echo '```'
  echo "${missing_out}" | tail -n 5
  echo '```'
  echo ""
} >> "${RESULTS_FILE}"
if [ "${missing_code}" -eq 0 ]; then
  echo "Delete-file proof failed: shuffle succeeded" >&2
  exit 1
fi

prove_exit_to_return() {
  local target="$1"
  local test_file="$2"
  local filter="$3"
  local label="$4"
  local backup
  backup="$(mktemp)"
  cp "${target}" "${backup}"
  sed -i 's/\bexit;/return;/' "${target}"
  set +e
  out="$(php "${PHPUNIT}" -c phpunit.xml.dist "${test_file}" --filter "${filter}" 2>&1)"
  code=$?
  set -e
  cp "${backup}" "${target}"
  rm -f "${backup}"
  {
    echo "### exit-to-return-${label}"
    echo "exit_code: ${code}"
    echo '```'
    echo "${out}" | tail -n 6
    echo '```'
    echo ""
  } >> "${RESULTS_FILE}"
  if [ "${code}" -eq 0 ]; then
    echo "exit-to-return proof failed for ${label}" >&2
    exit 1
  fi
}

module_test="${ROOT}/tests/Unit/PsCustomtextModuleTest.php"
upgrade_test="${ROOT}/tests/Unit/UpgradeModule300Test.php"
prove_exit_to_return "${ROOT}/index.php" "${module_test}" "testWebEntryPointIncludeGuardsUseExit" "root-index"
prove_exit_to_return "${ROOT}/translations/index.php" "${module_test}" "testWebEntryPointIncludeGuardsUseExit" "translations-index"
prove_exit_to_return "${ROOT}/upgrade/index.php" "${module_test}" "testWebEntryPointIncludeGuardsUseExit" "upgrade-index"
prove_exit_to_return "${ROOT}/upgrade/install-3.0.0.php" "${upgrade_test}" "testUpgradeInstallScriptGuardUsesExit" "upgrade-install-script"

echo "Stage 4b container proofs passed" >> "${RESULTS_FILE}"
