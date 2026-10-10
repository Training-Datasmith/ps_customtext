#!/usr/bin/env bash
# Invoked by run-stage-proofs.sh: mutates a tracked file, sleeps until SIGTERM, trap must restore.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TARGET="${ROOT}/index.php"
BACKUP_DIR="$(mktemp -d)"

# shellcheck source=tests/bin/file-restore-trap.sh
source "${ROOT}/tests/bin/file-restore-trap.sh"
trap_restore_init "${BACKUP_DIR}"
trap_restore_register "${TARGET}"
sed -i 's/\bexit;/return;/' "${TARGET}"

TRAP_SLEEP_PID=""

trap_restore_on_signal_with_sleep() {
  if [ -n "${TRAP_SLEEP_PID}" ] && kill -0 "${TRAP_SLEEP_PID}" 2>/dev/null; then
    kill -TERM "${TRAP_SLEEP_PID}" 2>/dev/null || true
    wait "${TRAP_SLEEP_PID}" 2>/dev/null || true
  fi
  trap_restore_restore_all
  trap - EXIT INT TERM
  exit 143
}

trap 'trap_restore_on_signal_with_sleep' INT TERM

sleep 300 &
TRAP_SLEEP_PID=$!
set +e
wait "${TRAP_SLEEP_PID}"
set -e
