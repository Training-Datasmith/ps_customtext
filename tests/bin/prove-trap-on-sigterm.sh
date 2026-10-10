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
set +e
sleep 300
set -e
