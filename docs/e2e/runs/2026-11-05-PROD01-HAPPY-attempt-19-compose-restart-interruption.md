# PROD01-HAPPY — Attempt 19: E2E services stopped during split-run restart

**Date:** 2026-11-05 UTC  
**Outcome:** Environment interruption after TypeScript preflight and auth setup, before the process journey  
**Classification:** Environment issue, ENV-E2E-002  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Guarded E2E stack, fresh baseline, selected actor login, one Chromium journey configured. The required five-minute host PHP-test quiet interval cleared before startup.

## What ran

The isolated stack passed readiness, `migrate:fresh --seed` and E2E reference/account seeding completed, and the Playwright TypeScript preflight completed successfully. One auth-setup test then signed in all six selected role accounts through the real UI and passed.

As required by `test-split`, the runner stopped the services to restart them with the baseline volumes preserved. During the restart, Docker events show SIGQUIT (signal 3) sent to the scheduler, Reverb, and queue containers; SIGTERM (15) to the SPA, Redis, Mailpit and Redis cache; SIGINT (2) to PostgreSQL; and SIGQUIT to the API. The supervised runner exited with signal 15 before the new services reached readiness. No PHP-test interruption was reported, and no OOM event was recorded. No production journey test or process action began.

## Result

This is an environment interruption, not a PROD01 journey result. The direct touchscreen option selection remains unverified. The baseline was freshly recreated and the six role sessions were successfully authenticated, but no shift, stock, or work-order transaction was created. The shutdown source is unknown.

## Evidence

- Supervisor log: `/tmp/ogami-prod01-attempt-19-supervised.log`
- Docker events at approximately 13:04 UTC show stop signals to the E2E services; no Docker OOM event was present.
- The runner reported `php_test_interrupted=false`; the auth-setup test passed for six actors.
- No process trace, screenshot, or journey context was produced.
