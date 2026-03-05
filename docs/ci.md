# CI/CD Gate Overview

## Local Commands
- PHPUnit: `vendor/bin/phpunit`
- PHPStan (Larastan): `vendor/bin/phpstan analyse --memory-limit=1G`
- OpenAPI lint + diff: `scripts/ci/check-openapi.sh`
- Route security policy: `php scripts/ci/check-route-security.php`
- Migration safety: `scripts/ci/check-migrations.sh`
- Escrow regression (Newman): `scripts/ci/run-escrow-newman.sh`

## Required CI Env Vars
- `ESCROW_TEST_USER_EMAIL`
- `ESCROW_TEST_USER_PASSWORD`
- `ESCROW_SUITE_BASE_URL` (defaults to `http://127.0.0.1:8000`)

## Notes
- The PR gate workflow runs PHPUnit, PHPStan, audits, OpenAPI validation, route security policy, migration safety checks, and the escrow Newman suite.
- The deploy workflow expects `STAGING_DEPLOY_COMMAND`, `STAGING_BASE_URL`, `PRODUCTION_DEPLOY_COMMAND`, and `PRODUCTION_BASE_URL` in GitHub secrets.
