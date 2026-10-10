#!/usr/bin/env bash
# Sourced by mutation/proof runners. Restores tracked files via cp (not mv) on EXIT/INT/TERM.

trap_restore_init() {
  TRAP_RESTORE_BACKUP_DIR="${1:?backup dir required}"
  mkdir -p "${TRAP_RESTORE_BACKUP_DIR}"
  TRAP_RESTORE_TARGETS=()

  trap_restore_on_signal() {
    trap_restore_restore_all
    trap - EXIT INT TERM
    exit 143
  }

  trap 'trap_restore_restore_all' EXIT
  trap 'trap_restore_on_signal' INT TERM
}

trap_restore_register() {
  local target="${1:?}"
  local idx="${#TRAP_RESTORE_TARGETS[@]}"
  TRAP_RESTORE_TARGETS+=("${target}")
  cp "${target}" "${TRAP_RESTORE_BACKUP_DIR}/${idx}.bak"
  stat -c '%u:%g %a' "${target}" > "${TRAP_RESTORE_BACKUP_DIR}/${idx}.meta"
}

trap_restore_restore_all() {
  local i target bak meta own mode
  for i in "${!TRAP_RESTORE_TARGETS[@]}"; do
    target="${TRAP_RESTORE_TARGETS[$i]}"
    bak="${TRAP_RESTORE_BACKUP_DIR}/${i}.bak"
    meta="${TRAP_RESTORE_BACKUP_DIR}/${i}.meta"
    if [ ! -f "${bak}" ]; then
      continue
    fi
    cp "${bak}" "${target}"
    if [ -f "${meta}" ]; then
      read -r own mode < "${meta}" || true
      if [ -n "${own}" ]; then
        chown "${own}" "${target}" 2>/dev/null || true
      fi
      if [ -n "${mode}" ]; then
        chmod "${mode}" "${target}" 2>/dev/null || true
      fi
    fi
  done
}

trap_restore_finish() {
  trap_restore_restore_all
  trap - EXIT INT TERM
  rm -rf "${TRAP_RESTORE_BACKUP_DIR}"
}
