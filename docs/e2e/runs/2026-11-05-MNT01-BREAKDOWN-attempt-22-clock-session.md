# MNT01-BREAKDOWN — attempt 22: frozen application time invalidated the UI session

**Result:** The fresh isolated baseline, process TypeScript preflight, and four role UI logins passed. Step 1 stopped while HR selected the Department on the bulk shift assignment form; subsequent API requests returned HTTP 401 and the SPA redirected to sign-in. The assignment was not submitted. Steps 2–7 are blocked and no MNT process transaction is credited.

- **Environment:** Guarded `test-split --grep MNT01-BREAKDOWN`; one headless Chromium worker, fresh E2E baseline, no PHP-test process detected. The journey failed after approximately 1.3 minutes.
- **Step 1 — HR configures Maintenance shift coverage:** HR opened Attendance & DTR → Shifts → Bulk assign. Selecting the Department timed out after the page redirected to sign-in. The UI had not reached the Assign shift action.
- **Evidence:** The sanitized trace records HTTP 401 responses from `/api/v1/auth/user`, `/api/v1/hr/departments/tree`, `/api/v1/attendance/attendances`, and `/api/v1/attendance/shifts` after the backend clock was frozen at 09:00 Asia/Manila. That instant was about five and a half hours behind the host clock. The response body was `Unauthenticated.` No form submission or MNT record creation occurred.
- **Cause assessment:** This is an E2E clock/session interaction, tracked under TST-MNT-003. The clock helper rebases database-session `last_activity` to simulated time, while Laravel's database session handler also uses host time for expiration and writes. Moving simulated time several hours backward invalidates the browser session during the journey. No product behavior was exercised after that redirect.
- **Next fixture adjustment:** Freeze at the current host instant rounded down to the minute. Use the seeded Extended Day shift for 06:00–18:00 Manila time and Night Shift for 18:00–06:00; for the overnight portion before 06:00, make the UI assignment effective from the prior work date. This keeps the test clock within one minute of the session clock while placing the technician inside configured coverage.
- **Evidence:** [Sanitized Playwright trace, context, screenshots, and guarded runner output](assets/mnt01-breakdown-attempt-22/). Both evidence archives pass the sanitizer check with zero findings.

**Classification:** Test issue (E2E clock/session fixture), TST-MNT-003. No application code changed.
