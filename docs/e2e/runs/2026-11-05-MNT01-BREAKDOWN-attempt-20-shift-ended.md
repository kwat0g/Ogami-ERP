# MNT01-BREAKDOWN — attempt 20: assignment blocked after the day shift ended

**Result:** Steps 1–3 passed. Step 4 opened the assignment dialog but could not select the technician because the current time was outside the configured shift. The journey stopped before assignment submission; steps 5–7 are blocked. No process coverage is credited for this incomplete step.

- **Environment:** A fresh guarded baseline and process TypeScript preflight passed. The four selected actors signed in through the real UI. Headless Chromium ran one journey with one worker and zero retries. The journey failed after 66.1 seconds; the supervisor detected no PHP-test process during this run.
- **Step 1 — HR Officer assigns Maintenance day-shift coverage:** Passed through the UI; the schedule used the seeded Day Shift (06:00–14:00).
- **Step 2 — Technician creates a corrective work order:** Passed through the UI; the new work order was Open.
- **Step 3 — Production Manager finds and observes the work order:** Passed.
- **Step 4 — Maintenance Supervisor assigns the technician:** The supervisor followed the assignment-needed notification and opened the UI dialog. At 14:03 Asia/Manila, after the configured 14:00 shift end, the dialog reported no technician available, showed three unavailable technicians, and left Assign technician disabled. The expected technician radio was not visible, so the test stopped before checking physical-presence attestation or submitting the assignment. The work order remained Open and unassigned in the UI.
- **Blocked:** Technician start, repair logging, completion, and final cross-module checks (steps 5–7) did not run.
- **Cause and correction:** The test derived its date from wall-clock time but did not freeze the backend clock. This is a test determinism issue, not evidence of an application defect: availability correctly reflected the ended shift. The journey will use the existing E2E-only clock at a fixed in-shift instant and set browser page clocks to that same instant. The eligibility assertion remains unchanged. TST-MNT-003 tracks the clock fixture; TST-MNT-002's corrected notification-order assertion remains pending because this run failed before reaching it.
- **Evidence:** [Sanitized Playwright trace, context, five screenshots, and runner output](assets/mnt01-breakdown-attempt-20/).

**Classification:** Test issue (time-dependent test data), TST-MNT-003. No application code changed.
