# PROD01-HAPPY — Attempt 14: E2E database stopped during the fresh migration

**Date:** 2026-11-05 UTC  
**Outcome:** Fresh baseline migration was interrupted before E2E seeders or browser setup  
**Classification:** Environment issue ENV-E2E-002  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Guarded attempt after a complete 300-second host-test quiet interval.

## Result

The E2E stack started, and `test-split` entered the fresh baseline step. Migration output reached `2026_08_11_160000_create_scheduler_execution_ledger`, which printed `FAIL`. The PostgreSQL container log records a fast administrator-requested shutdown at 11:49:14 UTC and active database connections terminated at that same time. The source migration itself was not changed or bypassed; the captured output does not include a database exception that distinguishes a migration defect from the interrupted connection.

The ancestry-aware monitor did not detect a host PHP test. The guarded runner exited 137, and the E2E containers stopped with no OOM flag in Docker's container state. The baseline did not reach the E2E reference/account seeders, TypeScript preflight, or Playwright. No actor session or business journey step ran. The E2E database is partial and must be reset by the next fresh-baseline attempt.

The source of the administrator shutdown is unknown. This is recorded as an environment interruption, not evidence that the migration is defective. If the migration fails again while PostgreSQL remains available, that failure will be preserved and investigated separately.

## Evidence

- Guarded supervisor output: `/tmp/ogami-prod01-attempt-14-supervised.log`
- PostgreSQL log: fast administrator shutdown at 11:49:14 UTC, with active connection termination.
- Docker state: inspected E2E containers reported `OOMKilled=false`.
- No new Playwright result, context, screenshot, or trace was produced.
