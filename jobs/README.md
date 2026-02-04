Jobs

- Purpose: scheduled workers for rankings, ETL, and data backfills.
- Guidelines:
  - Idempotent by design; safe to re-run.
  - Use services from /lib; no raw echo/print.
  - Log start/finish and key metrics via LoggerFactory.
