# INV01-NEGATIVE-STOCK — Environment interruption

**Date:** 2026-11-04 UTC  
**Classification:** Environment issue; not a business-journey result  
**Environment:** Dedicated `ogami-erp-e2e` Compose project and isolated `ogami_e2e` database

The E2E reset completed successfully: Compose started the dedicated services, `migrate:fresh --seed` completed, and the E2E seeder reported 37 isolated actors with no transactional demo records. The selected `INV01-NEGATIVE-STOCK` runner invocation then exited with code 143 before Playwright printed its test list or created a new journey context. No business journey step ran.

Docker events recorded SIGTERM for the one-off Playwright container. At inspection, the API, DB, and SPA showed `OOMKilled=false`; the E2E `queue-dss` container exited 137 with `OOMKilled=false` after a signal-3 then signal-9 stop sequence. These facts do not establish the source of the runner termination. `spa/test-results/process/.last-run.json` still described the previous passing run, confirming no new Playwright result was written.

The interruption is added to the existing open [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated). It is not counted as a passed, failed, or blocked business journey. No application or transactional data was written; the test attempt only inspected container state after reset.
