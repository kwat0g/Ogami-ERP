# HR03-MISSING-PUNCH — attempt 06

**Result:** The two-context run stopped before HR corrected the attendance. The Employee time-in and selected-actor auth setup completed, but an existing journey invariant expected zero attendance notifications and read one for the new attendance row. The DTR realtime assertion was not reached.

- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer node scripts/e2e/runner.mjs test --grep HR03-MISSING-PUNCH`
- **Baseline:** The isolated E2E stack was started with its existing volumes; this attempt did not reset/seed a fresh baseline.
- **Observed:** Playwright ran one auth setup test (pass) and one HR03 scenario (fail, 15.5 seconds). The failure was `attendance_notification_count`: expected `0`, received `1`, after HR opened the missing-time-out list. The run did not establish whether that row came from prior E2E data or the current journey.
- **Impact:** The UI correction and post-fix DTR update were not exercised. This is not evidence for or against realtime delivery.
- **Cleanup:** The isolated stack was stopped without deleting its volumes. A managed `runner seed` process and migration child appeared during shutdown; the child had already exited by the time termination was issued, so whether that seed completed is unknown. Only E2E-labeled containers were disabled/stopped. Dev containers and data were not targeted. Raw Playwright trace was replaced with a redaction notice; the two auth-state files written during setup were cleared.
- **Status:** Harness precondition unresolved; no product defect or post-fix browser PASS is claimed.
