# Build status

Generated as a Laravel 12 + MySQL source scaffold with an embedded Shopify Admin UI.

## Checks run in the workspace

- `node --check public/app.js` — passed.
- `composer.json` JSON parse — passed.
- `shopify.app.toml` TOML parse — passed.
- `phpunit.xml` XML parse — passed.
- `docker-compose.yml` YAML parse — passed.

## Not run

This workspace has no PHP, Composer, or Docker executable, so PHP syntax linting, Composer dependency installation, Laravel migrations, PHPUnit tests, container build, and live Shopify API/OAuth tests were not run. Run those checks before deploying or submitting to Shopify. Shopify App Store approval is determined by Shopify’s review and cannot be guaranteed by source generation.
