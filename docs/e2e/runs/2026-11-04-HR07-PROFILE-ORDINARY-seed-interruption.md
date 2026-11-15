# HR07-PROFILE-ORDINARY — Baseline seed interrupted

**Date:** 2026-11-04 UTC  
**Classification:** Environment interruption; not a business-journey result  
**Environment:** Dedicated `ogami-erp-e2e` Compose project and isolated `ogami_e2e` database

After the prior E2E run, the guarded stack was brought up and reached healthy status. A fresh `runner.mjs seed` invocation exited 143 while rebuilding the baseline. Docker's event timeline shows the API seeding exec was terminated as the E2E services received stop signals. The API container exited 0 with `OOMKilled=false`; PostgreSQL received a fast-shutdown request and exited 0. No OOM event explains the shutdown, and its initiator is unknown.

Although the event stream shows the E2E account-seeder exec was started, no successful seed completion or 37-account baseline verification was obtained. The database is therefore treated as incomplete. No Playwright process test ran and no business transaction was created by this attempt. This adds evidence to [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated); it does not count as a journey pass, failure, or blocked process step.
