# HR03-PUNCH — interrupted attempt

**Result:** Incomplete run; not counted as passed or failed. The attached runner command exited with code 143 before Playwright emitted a result. The final clean-baseline run is [documented separately](2026-11-04-HR03-PUNCH-full-run.md).

- **Actors:** `employee` and `hr_officer`, each in a separate UI session.
- **Observed:** The run created time-in and time-out through the Employee UI. Read-only checks after the interruption found one `present` row for 2026-11-05, local times 06:00 and 14:00, and exactly two punches (`in`, `out`). The E2E clock read 14:00 Asia/Manila. The process had not produced its final DTR/report assertions.
- **Environment evidence:** The runner and E2E service containers stopped without a Playwright report; inspected services were not marked OOM-killed. The exact source of the termination was not established. This adds a recurrence to [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated).
- **Disposition:** The partial UI-created record was removed by a guarded fresh reset. The next single journey was launched detached while still using `scripts/e2e/runner.mjs`; it completed successfully from a new baseline. This interruption is retained as an environment attempt and is not hidden as a retry or a product pass.

