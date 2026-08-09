#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# V1.9-specific tests run before this wrapper.
# Historical tests contain assertions for their own release metadata, so only
# release metadata and release-package tools are temporarily restored from the
# exact Version 1.8.0 release commit. V1.9 application/runtime code remains in
# place throughout the historical regression.
V18_BASELINE='3b729e7274f9561a9ce2aa10b1572b50f2ca882d'

if ! git cat-file -e "${V18_BASELINE}^{commit}" 2>/dev/null; then
  echo "ERROR: V1.8 baseline commit is unavailable: ${V18_BASELINE}" >&2
  echo 'Checkout must provide full history for the historical regression.' >&2
  exit 1
fi

BASELINE_VERSION="$(git show "${V18_BASELINE}:app/version.php")"
if [[ "$BASELINE_VERSION" != *"const APP_VERSION = '1.8.0';"* ]] \
  || [[ "$BASELINE_VERSION" != *"const APP_VERSION_LABEL = 'RSS Reader Modernization 1.8.0';"* ]]; then
  echo 'ERROR: configured historical baseline is not the exact Version 1.8.0 release.' >&2
  exit 1
fi

FILES=(
  app/version.php
  SOURCE_BUILD.txt
  README.md
  CHANGELOG.md
  RELEASE_NOTES.md
  APPLY_NOTE.md
  tools/build_complete_package.py
  tools/build_release_package.py
  tools/verify_complete_package.py
  tools/verify_release_package.py
)

TMP="$(mktemp -d)"
restore() {
  for f in "${FILES[@]}"; do
    if [ -f "$TMP/$f" ]; then
      mkdir -p "$(dirname "$f")"
      cp "$TMP/$f" "$f"
    fi
  done
  rm -rf "$TMP"
}
trap restore EXIT

for f in "${FILES[@]}"; do
  mkdir -p "$TMP/$(dirname "$f")"
  cp "$f" "$TMP/$f"
  git show "${V18_BASELINE}:$f" > "$f"
done

echo "Historical regression metadata baseline: ${V18_BASELINE} (Version 1.8.0)"
echo 'V1.9 runtime/application code remains active.'
bash tests/run.sh