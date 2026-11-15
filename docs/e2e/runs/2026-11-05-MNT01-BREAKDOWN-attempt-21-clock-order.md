# MNT01-BREAKDOWN — attempt 21: first fixed-clock setup redirected HR to sign-in

**Result:** The fresh isolated baseline, process TypeScript preflight, and four role UI logins passed. Step 1 failed before its first process action: after navigating through Attendance & DTR → Shifts, the page redirected to sign-in instead of showing the Bulk assign action. Steps 2–7 are blocked; no shift assignment or MNT work order was submitted.

- **Environment:** Guarded single-journey run, one headless Chromium worker, no PHP-test process detected. The auth setup passed and the journey failed at step 1.
- **Step 1 — HR configures Maintenance coverage:** The fixture froze the backend clock at 09:00 Asia/Manila, then fixed the browser page clocks. HR's session stopped resolving during navigation and the expected Bulk assign action timed out on the sign-in page.
- **Cause assessment:** The initial experiment set browser time after backend time. Attempt 22 corrected that order, but still received 401 responses because the fixed 09:00 instant was several hours behind host time. The ordering itself is therefore not a sufficient correction; TST-MNT-003 now tracks the larger E2E clock/session incompatibility.
- **Evidence:** [Sanitized Playwright trace, context, screenshots, and runner output](assets/mnt01-breakdown-attempt-21/). Both evidence archives pass the sanitizer check with zero findings.

**Classification:** Test issue (E2E clock/session fixture), TST-MNT-003. No application code changed.
