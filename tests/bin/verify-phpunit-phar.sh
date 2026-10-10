#!/usr/bin/env bash
set -euo pipefail

PHAR_PATH="${PHPUNIT_PHAR_PATH:-/tmp/phpunit-4.8.36.phar}"
ASC_PATH="${PHAR_PATH}.asc"
EXPECTED_SHA256="ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e"
EXPECTED_FINGERPRINT="${EXPECTED_FINGERPRINT:-D8406D0D82947747293778314AA394086372C20A}"

if [ ! -f "${PHAR_PATH}" ] || ! echo "${EXPECTED_SHA256}  ${PHAR_PATH}" | sha256sum -c - >/dev/null 2>&1; then
  curl -fsSL --connect-timeout 30 --max-time 120 -o "${PHAR_PATH}" https://phar.phpunit.de/phpunit-4.8.36.phar
  echo "${EXPECTED_SHA256}  ${PHAR_PATH}" | sha256sum -c -
fi
if [ ! -f "${ASC_PATH}" ]; then
  curl -fsSL --connect-timeout 30 --max-time 120 -o "${ASC_PATH}" https://phar.phpunit.de/phpunit-4.8.36.phar.asc
fi

GNUPGHOME="$(mktemp -d)"
export GNUPGHOME
trap 'rm -rf "${GNUPGHOME}"' EXIT
chmod 700 "${GNUPGHOME}"
curl -fsSL https://sebastian-bergmann.de/gpg.asc | gpg --homedir "${GNUPGHOME}" --import
FINGERPRINT="$(gpg --homedir "${GNUPGHOME}" --with-colons --fingerprint "${EXPECTED_FINGERPRINT}" 2>/dev/null | awk -F: '$1 == "fpr" { print $10; exit }')"
if [ "${FINGERPRINT}" != "${EXPECTED_FINGERPRINT}" ]; then
  echo "Unexpected signing key fingerprint: ${FINGERPRINT}" >&2
  exit 1
fi

VERIFY_LOG="$(mktemp)"
gpg --homedir "${GNUPGHOME}" --status-fd 1 --verify "${ASC_PATH}" "${PHAR_PATH}" > "${VERIFY_LOG}" 2>&1
VERIFY_STATUS=$?
cat "${VERIFY_LOG}"
if [ "${VERIFY_STATUS}" -ne 0 ]; then
  rm -f "${VERIFY_LOG}"
  exit "${VERIFY_STATUS}"
fi
if ! grep -q "\[GNUPG:\] VALIDSIG.*${EXPECTED_FINGERPRINT}" "${VERIFY_LOG}"; then
  echo "GPG verify did not report VALIDSIG for ${EXPECTED_FINGERPRINT}" >&2
  rm -f "${VERIFY_LOG}"
  exit 1
fi
if grep -qE '\[GNUPG:\] (BADSIG|ERRSIG|EXPKEYSIG)' "${VERIFY_LOG}"; then
  echo "GPG verify reported a bad signature status" >&2
  rm -f "${VERIFY_LOG}"
  exit 1
fi
rm -f "${VERIFY_LOG}"
