NGN API v1

- Single front controller (index.php) dispatches versioned routes.
- Add GET/POST/etc routes via the Router in index.php.
- All responses must use the standard envelope: { data, meta, errors }.
- CORS and security headers are applied globally.
- Health endpoint:
  - GET /api/v1/health -> { data: { status: ok, version, time }, meta: { env }, errors: [] }
