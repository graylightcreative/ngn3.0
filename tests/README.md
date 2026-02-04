Tests

- PHPUnit test suite.
- To run locally:
  - composer dump-autoload
  - ./vendor/bin/phpunit -c phpunit.xml.dist
- Coverage targets:
  - Config validation (env requireds)
  - HTTP helpers (JSON envelope)
  - API /health endpoint (happy path + CORS preflight) — integration-lite
