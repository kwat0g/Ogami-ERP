# HR07-TRAINING-COMPLETE — attempt 07

**Classification:** Test issue (detail-heading accessible name; TST-HR-047)  
**Run ID:** `2cebbdf6-997c-4b91-837c-36736cca849c`  
**Started:** 2026-11-04 18:04 UTC  
**Environment:** Guarded `ogami-erp-e2e` Compose project and `ogami_e2e` database; fresh baseline held under the runner lock  
**Execution:** Headless Chromium, one worker, no retries

## Result

The fresh migration and baseline seed completed. The selected HR Officer and Department Head signed in through the real UI. HR created a run-scoped active training course through Training Programs, and its read-only state, audit actor, no-assignment condition, mail state, and page diagnostics passed. HR then found and opened the seeded Employee through the Employees list. The test expected the exact employee name as the detail heading, but the accessible heading includes the visible `Active` status chip. No employee training assignment was sent; the Department Head hand-off and completion steps were blocked.

| Step | Actor | Outcome |
|---|---|---|
| Create training course through Training Programs | `hr_officer` | Passed UI creation and all invariants |
| Assign training to department employee | `hr_officer` | Opened the correct employee detail; exact heading assertion omitted the `Active` chip and failed before opening Trainings or submitting an assignment |
| Follow upcoming-training badge | `department_head__hr` | Blocked |
| Complete training and observe live update | `hr_officer`; `department_head__hr` observer | Blocked |

## Evidence

- Playwright result: 1 auth-setup test passed, 1 journey test failed; one worker; 40.7 seconds total Playwright time.
- The page accessibility snapshot shows heading `E2E department head hr Active`; the breadcrumb and Full name field show the expected normalized name `E2E department head hr`.
- `spa/src/pages/hr/employees/detail.tsx:210-214` renders the employee name and status `Chip` in the same `PageHeader` title.
- No employee-training POST occurred. The course master record was created through the UI; the next fresh baseline will discard it.
- Failure screenshots, trace, error context, journey context, and the first-step walkthrough are archived in [attempt artifacts](assets/hr07-training-complete-attempt-07/).

## Follow-up

The expected accessible heading now includes the displayed `Active` status while retaining an exact match. TST-HR-046 is resolved: the normalized employee name matched the displayed name. TST-HR-047 tracks the omitted status chip in the heading locator. A fresh-baseline confirmation run is pending.
