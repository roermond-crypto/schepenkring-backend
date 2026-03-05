#!/usr/bin/env bash
set -euo pipefail

OUTPUT=${1:-release-evidence.json}
BASE_REF=${GITHUB_BASE_REF:-main}

COMMIT=$(git rev-parse HEAD)
DATE=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
MIGRATIONS=$(git diff --name-only origin/${BASE_REF}...HEAD | grep -E '^database/migrations/.*\.php$' || true)

cat > "$OUTPUT" <<EOF
{
  "commit": "${COMMIT}",
  "generated_at": "${DATE}",
  "migrations": [
$(echo "$MIGRATIONS" | awk '{printf "    \"%s\"\n", $0}' | paste -sd, -)
  ]
}
EOF

cat "$OUTPUT"
