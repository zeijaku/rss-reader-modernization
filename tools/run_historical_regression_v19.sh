#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Historical tests include release-marker assertions for their own Version.
# V1.9-specific tests run before this wrapper. Here only release metadata and
# version-specific package tools are temporarily taken from the V1.8 parent;
# V1.9 runtime/application code remains in place.
if ! git rev-parse HEAD^ >/dev/null 2>&1; then
  echo 'ERROR: parent commit is unavailable; checkout with fetch-depth >= 2.' >&2
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
  git show "HEAD^:$f" > "$f"
done

echo 'Historical regression uses V1.8 release metadata only; V1.9 runtime code remains active.'
bash tests/run.sh
