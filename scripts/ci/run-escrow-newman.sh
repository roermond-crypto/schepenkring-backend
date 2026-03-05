#!/usr/bin/env bash
set -euo pipefail

BASE_URL=${ESCROW_SUITE_BASE_URL:-http://127.0.0.1:8000}
LOGIN_EMAIL=${ESCROW_TEST_USER_EMAIL:-}
LOGIN_PASSWORD=${ESCROW_TEST_USER_PASSWORD:-}

if [ -z "$LOGIN_EMAIL" ] || [ -z "$LOGIN_PASSWORD" ]; then
  echo "ESCROW_TEST_USER_EMAIL and ESCROW_TEST_USER_PASSWORD must be set."
  exit 1
fi

php artisan serve --host=127.0.0.1 --port=8000 >/tmp/escrow-server.log 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID >/dev/null 2>&1 || true' EXIT

sleep 5

npx newman run postman/escrow-regression.json \
  --env-var baseUrl="$BASE_URL" \
  --env-var loginEmail="$LOGIN_EMAIL" \
  --env-var loginPassword="$LOGIN_PASSWORD" \
  --env-var dealId="999" \
  --timeout-request 30000
