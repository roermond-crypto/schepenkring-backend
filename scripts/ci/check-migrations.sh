#!/usr/bin/env bash
set -euo pipefail

BASE_REF=${GITHUB_BASE_REF:-main}

git fetch origin ${BASE_REF} --depth=1 >/dev/null 2>&1 || true

CHANGED=$(git diff --name-only origin/${BASE_REF}...HEAD | grep -E '^database/migrations/.*\.php$' || true)

if [ -z "$CHANGED" ]; then
  echo "No migration changes detected."
  exit 0
fi

RISKY_PATTERN='dropColumn|dropIfExists|dropIndex|renameColumn|->change\(|decimal\('

FAILED=0

for file in $CHANGED; do
  if grep -q "@allow-risky-migration" "$file"; then
    echo "[skip] $file has @allow-risky-migration"
    continue
  fi

  if grep -E -n "$RISKY_PATTERN" "$file" >/dev/null; then
    echo "[risk] $file contains potentially destructive changes. Add @allow-risky-migration to acknowledge."
    FAILED=1
  fi
done

if [ $FAILED -ne 0 ]; then
  exit 1
fi

echo "Migration safety check passed."
