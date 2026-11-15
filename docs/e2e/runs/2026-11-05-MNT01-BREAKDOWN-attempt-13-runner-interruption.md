# MNT01-BREAKDOWN — attempt 13: runner interruption after HR prerequisite

**Result:** HR shift assignment step passed; the process journey was interrupted before maintenance work-order creation. The variant is not complete.

- **Environment:** Guarded E2E environment passed validation. A fresh baseline seeded 38 isolated actors. Four selected roles completed real UI sign-in. Playwright ran headless Chromium with one worker; the E2E TypeScript check passed.
- **Step 1 — HR shift coverage:** HR Officer used the Attendance & DTR → Shifts → Bulk assign UI and assigned Day Shift to the Maintenance department for Monday–Friday, effective 2026-11-05. The UI confirmed “Assigned shift to 3 employees.” Read-only assertions confirmed the technician's active schedule and the audit actor was HR Officer. The annotated screenshot is archived.
- **Remaining steps:** Corrective MWO creation, Production Manager live observation, supervisor assignment, technician start, repair log, and completion were not recorded in this attempt.
- **Interruption evidence:** The journey context contains only the passed HR step. Playwright's JSON result file remained from an earlier attempt, so it is not valid evidence for this run. The trace ended without a final result; when checked, the E2E containers were no longer present. The shutdown source is unknown and no application defect is inferred.
- **Classification:** Environment interruption under [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated).
- **Data handling:** This attempt's shift assignment is in the disposable E2E database and will be removed by the next guarded fresh baseline before the journey runs again.

Evidence: [journey context and annotated screenshot](assets/mnt01-breakdown-attempt-13/) and [sanitized Playwright trace](assets/mnt01-breakdown-attempt-13/trace-sanitized.zip). The archive contains no network payloads or encrypted cookie values.
