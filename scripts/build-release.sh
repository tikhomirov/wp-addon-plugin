#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "Must run from a git checkout of wp-addon-plugin." >&2
    exit 1
fi

VERSION="$(grep -m1 'Version:' wp-addon-plugin.php | sed -E 's/.*Version:[[:space:]]*//')"
if [[ -z "$VERSION" ]]; then
    echo "Could not read Version from wp-addon-plugin.php" >&2
    exit 1
fi

REF="${1:-HEAD}"
PREFIX="wp-addon-plugin-${VERSION}/"
OUT_DIR="${ROOT}/dist"
OUT_FILE="${OUT_DIR}/wp-addon-plugin-${VERSION}.zip"

mkdir -p "$OUT_DIR"
rm -f "$OUT_FILE"

git archive --format=zip --prefix="$PREFIX" -o "$OUT_FILE" "$REF"

echo "Created ${OUT_FILE} ($(du -h "$OUT_FILE" | awk '{print $1}')) from ${REF}"