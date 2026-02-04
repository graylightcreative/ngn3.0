Library (/lib)

- Domain, Data Access, and Services. Pure PHP, framework-free.
- Principles:
  - Typed services; no HTML rendering here.
  - DB via PDO only with prepared statements (see DB/ConnectionFactory.php).
  - Logging via Monolog (see Logging/LoggerFactory.php).
  - HTTP helpers kept minimal (Request/Response/Router/Cors/Json).
- Coding standards:
  - PHP 8.2+ (target 8.4 syntax where applicable, no higher-version features).
  - Keep functions small and testable.
