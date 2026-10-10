#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}"
RESULTS_FILE="/opt/cursor/artifacts/suite-results.md"
PHPUNIT="${PHPUNIT_PHAR_PATH:-/tmp/phpunit-4.8.36.phar}"

# shellcheck source=tests/bin/file-restore-trap.sh
source "${ROOT}/tests/bin/file-restore-trap.sh"

{
  echo "## Stage proofs (RUN_PROOFS=1)"
  echo ""
} >> "${RESULTS_FILE}"

# SIGTERM mid-mutation: subprocess must restore via trap (bounded parent wait)
index_expected_hash="$(sha256sum "${ROOT}/index.php" | awk '{print $1}')"
sigterm_proof_start="$(php -r 'echo microtime(true);')"
set +e
bash "${ROOT}/tests/bin/prove-trap-on-sigterm.sh" &
trap_proof_pid=$!
sleep 0.5
kill -TERM "${trap_proof_pid}" 2>/dev/null || true
trap_proof_wait=124
sigterm_deadline_epoch="$(php -r 'echo microtime(true) + 10;')"
while kill -0 "${trap_proof_pid}" 2>/dev/null; do
  if ! php -r 'exit(microtime(true) < (float)$argv[1] ? 0 : 1);' "${sigterm_deadline_epoch}"; then
    kill -KILL "${trap_proof_pid}" 2>/dev/null || true
    echo "SIGTERM proof parent wait timed out after 10s" >&2
    exit 1
  fi
  sleep 0.05
done
wait "${trap_proof_pid}" 2>/dev/null
trap_proof_wait=$?
set -e
sigterm_proof_elapsed="$(php -r 'printf("%.3f", microtime(true) - (float)$argv[1]);' "${sigterm_proof_start}")"
index_actual_hash="$(sha256sum "${ROOT}/index.php" | awk '{print $1}')"
readable="no"
if [ -r "${ROOT}/index.php" ] && [ -w "${ROOT}/index.php" ]; then
  readable="yes"
fi
content_clean="no"
if [ "${index_expected_hash}" = "${index_actual_hash}" ] && tail -n 1 "${ROOT}/index.php" | grep -q 'exit;'; then
  content_clean="yes"
fi
{
  echo "### sigterm-mid-mutation-trap-restore"
  echo "wait_exit: ${trap_proof_wait}"
  echo "elapsed_seconds: ${sigterm_proof_elapsed}"
  echo "index_content_restored: ${content_clean}"
  echo "index_php_readable: ${readable}"
  echo ""
} >> "${RESULTS_FILE}"
if [ "${content_clean}" != "yes" ] || [ "${readable}" != "yes" ]; then
  echo "SIGTERM trap proof failed" >&2
  exit 1
fi
if ! php -r 'exit((float)$argv[1] <= 2.0 ? 0 : 1);' "${sigterm_proof_elapsed}"; then
  echo "SIGTERM proof took longer than 2.0s (${sigterm_proof_elapsed}s)" >&2
  exit 1
fi
if [ "${trap_proof_wait}" -ne 143 ]; then
  echo "SIGTERM proof expected wait exit 143, got ${trap_proof_wait}" >&2
  exit 1
fi

# Missing pinned file must fail shuffled runner
missing="${ROOT}/tests/Unit/PsCustomtextWidgetTest.php"
delete_backup_dir="$(mktemp -d)"
trap_restore_init "${delete_backup_dir}"
trap_restore_register "${missing}"
rm -f "${missing}"
set +e
missing_out="$(php tests/bin/run-shuffled.php 2>&1)"
missing_code=$?
set -e
trap_restore_finish
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
  local proof_backup_dir
  proof_backup_dir="$(mktemp -d)"
  trap_restore_init "${proof_backup_dir}"
  trap_restore_register "${target}"
  sed -i 's/\bexit;/return;/' "${target}"
  set +e
  out="$(php "${PHPUNIT}" -c phpunit.xml.dist "${test_file}" --filter "${filter}" 2>&1)"
  code=$?
  set -e
  trap_restore_finish
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
prove_exit_to_return "${ROOT}/ps_customtext.php" "${module_test}" "testWebEntryPointIncludeGuardsUseExit" "ps-customtext-module"
prove_exit_to_return "${ROOT}/upgrade/install-3.0.0.php" "${upgrade_test}" "testUpgradeInstallScriptGuardUsesExit" "upgrade-install-script"

echo "Stage proofs passed" >> "${RESULTS_FILE}"
