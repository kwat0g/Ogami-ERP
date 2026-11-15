# INV01-NEGATIVE-STOCK — Interrupted browser attempt

**Date:** 2026-11-04 UTC  
**Classification:** Environment interruption; not a process pass/failure  
**Environment:** Dedicated `ogami-erp-e2e` Compose project, after a clean E2E reset  
**Playwright:** Selected actor auth setup passed; one worker, no retries

The selected real-UI login setup passed for `system_admin`, `warehouse_staff`, and `finance_officer`. Playwright began the business test but the runner exited 143 before the first observer step completed. The archived trace shows `finance_officer` navigating to Finance → Stock Adjustments and checking the empty list for this run's marker. It does not show any mutation request or Warehouse submission.

No business transactional record or stock movement was created. The attempt has no journey-context result because the process was terminated before `runProcessStep` completed its first step. The raw Playwright trace, network recording, and screencast frames are preserved under [attempt assets](assets/inv01-negative-stock-attempt-01-interrupted/traces/).

The Compose services stopped during the same interval. The browser container received SIGTERM; API, DB, and SPA had `OOMKilled=false`, while queue-dss exited 137 with `OOMKilled=false`. This is another occurrence of [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated); no product defect is inferred. The database is reset before the next attempt.
