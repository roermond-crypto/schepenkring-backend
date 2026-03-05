#!/usr/bin/env bash
set -euo pipefail

SPEC="openapi/openapi.yaml"

if [ ! -f "$SPEC" ]; then
  echo "OpenAPI spec not found at $SPEC"
  exit 1
fi

npx @redocly/cli lint "$SPEC"

BASE_REF=${GITHUB_BASE_REF:-main}
if git show "origin/${BASE_REF}:${SPEC}" > /tmp/openapi-base.yaml 2>/dev/null; then
  npx @openapitools/openapi-diff /tmp/openapi-base.yaml "$SPEC" \
    --fail-on-incompatible --fail-on-changed
else
  echo "No base OpenAPI spec found for diff; skipping diff check."
fi
