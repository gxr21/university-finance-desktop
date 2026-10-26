#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if ! command -v php >/dev/null 2>&1; then
  echo "PHP غير موجود. ثبّت PHP 8.2+ وMySQL ثم أعد المحاولة."
  exit 1
fi
exec php bin/desktop-start.php
