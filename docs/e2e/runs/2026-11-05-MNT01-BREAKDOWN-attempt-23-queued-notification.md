# MNT01-BREAKDOWN — attempt 23: queued assignment notification arrived after the invariant read

**Result:** Step 1 passed, including the HR shift assignment through the UI. Step 2 created the corrective work order through the Maintenance Technician UI and verified it as Open, but the immediate read-only notification invariant returned no rows. The journey stopped there; steps 3–7 are blocked.

- **Environment:** Guarded fresh baseline, process TypeScript check passed, selected actor auth setup passed, one headless Chromium worker, and no PHP-test process detected. The journey failed after 46.9 seconds.
- **Step 1 — HR configures Maintenance shift coverage:** Passed through Attendance & DTR → Shifts → Bulk assign. The test selected the seeded Extended Day shift for the current Manila work time, assigned all seven weekdays, and verified the row and HR audit actor through read-only queries.
- **Step 2 — Technician creates a corrective work order:** The Maintenance Technician created `MWO-202611-0001` on `IMM-01` through Maintenance → Maintenance Work Orders → New work order. UI and read-only checks confirmed the Open state, creator, and unique number. The expected `maintenance.assignment_needed` notification was absent at the immediate invariant read, so the test stopped before the next actor searched for the hand-off.
- **Later read-only evidence:** A subsequent query found exactly one matching notification for the intended Maintenance Supervisor. No matching queued job or failed job remained. This confirms an asynchronous delivery race in the test assertion; it does not establish a product notification defect. The failed run's MWO remains Open and unassigned in the disposable E2E baseline.
- **Next adjustment:** Replace the immediate notification read with a bounded Playwright `expect.poll` over the exact expected notification rows and recipients. If the correct notification does not arrive within the bound, the step still fails and blocks the hand-off.
- **Evidence:** [Sanitized Playwright trace, context, screenshots, and guarded runner output](assets/mnt01-breakdown-attempt-23/). Both evidence archives pass the sanitizer check with zero findings.

**Classification:** Test issue (async notification assertion), TST-MNT-004. No application code changed.
