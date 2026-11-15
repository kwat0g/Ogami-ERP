# MNT01-BREAKDOWN — attempt 16: assignment notification expectation order

**Result:** The journey stopped at the assignment notification invariant. Steps 1–3 passed. The supervisor's assignment action and the resulting UI status passed in step 4, then the read-only notification assertion failed because the expected rows were listed in a different order from the verifier's query. Steps 5–7 are blocked pending a clean-baseline rerun after the test correction.

- **Environment:** The guarded E2E runner created a fresh baseline and seeded 38 E2E actors. The selected HR Officer, Maintenance Technician, Maintenance Supervisor, and Production Manager sign-ins passed through the real login UI. Process TypeScript preflight passed. Playwright ran headless Chromium with one worker and no retries. The setup test passed; the single MNT01 journey failed after 77.4 seconds.
- **Step 1 — HR Officer configures the maintenance shift:** Passed through the UI; read-only schedule and audit assertions passed.
- **Step 2 — Maintenance Technician creates a corrective work order:** Passed through the UI.
- **Step 3 — Production Manager observes the open work order:** Passed.
- **Step 4 — Maintenance Supervisor assigns the technician:** The UI action succeeded, the assigned state appeared, and the second-role observer saw the status change. The following notification invariant failed: the expected array placed `maintenance.assignment_needed` before `maintenance.assigned`, while the read-only helper deliberately sorts by `notice.type, recipient.email`, which returns `maintenance.assigned` first. The notification types and recipients were present; only the test's array order was wrong.
- **Blocked:** Work-order repair logging, completion, and final cross-module checks (steps 5–7) did not run.
- **Correction:** Reordered the expected notification rows in the E2E journey to match the helper's declared sort order. Recipient and notification-type assertions remain exact. This resolves test issue TST-MNT-002 in test code; no application code changed. A fresh-baseline rerun is required before the journey can continue.
- **Concurrency note:** The runner's boundary checks did not report an active PHP test. The temporary external monitor became unresponsive during the run and was stopped after Playwright completed, so this attempt does not establish continuous process-level exclusion. ENV-E2E-008 remains open.
- **Evidence:** [Sanitized trace, journey context, and five failure screenshots](assets/mnt01-breakdown-attempt-16/).

**Classification:** Test issue, TST-MNT-002. No product defect is inferred from the ordering mismatch.
