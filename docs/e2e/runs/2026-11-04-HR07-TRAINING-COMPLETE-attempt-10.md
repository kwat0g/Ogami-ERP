# HR07-TRAINING-COMPLETE — attempt 10

**Classification:** Test issue (submit button accessible name; TST-HR-048)  
**Run ID:** `0729b143-fb39-4645-a226-d32d76f1cb6d`  
**Started:** 2026-11-04 18:17 UTC  
**Environment:** Guarded `ogami-erp-e2e` Compose project and `ogami_e2e` database; fresh baseline created in the attached runner sequence  
**Execution:** Headless Chromium, one worker, no retries

## Result

Both selected actors signed in through the real UI. HR created the active course through Training Programs and all first-step invariants passed. HR found the seeded Employee through the Employees list, and the exact heading assertion including the visible `Active` chip passed. HR opened the Trainings tab and the Assign training dialog, selected the run-scoped course and scheduled date, then the test timed out before sending an assignment request. The dialog's submit button is labeled `Assign`; the test incorrectly searched globally for `Assign training`. The Department Head hand-off and completion steps were blocked.

| Step | Actor | Outcome |
|---|---|---|
| Create training course through Training Programs | `hr_officer` | Passed UI creation and all invariants |
| Assign training to department employee | `hr_officer` | Employee heading, Trainings tab, course selection, and date entry passed; test locator failed before clicking the dialog's `Assign` button |
| Follow upcoming-training badge | `department_head__hr` | Blocked |
| Complete training and observe live update | `hr_officer`; `department_head__hr` observer | Blocked |

## Evidence

- Playwright result: 1 auth-setup test passed, 1 journey test failed; 51.8 seconds total Playwright time.
- The failure snapshot shows the Assign training dialog with the selected course, `2026-11-05` date, and a visible button named `Assign`.
- `spa/src/pages/hr/employees/detail.tsx:1207-1221` renders the modal submit action as `Assign`.
- No employee-training POST was sent, and no assignment record was created. The course catalog record was UI-created and is disposable with the next fresh baseline.
- Trace, screenshots, page snapshot, journey context, and first-step walkthrough are archived in [attempt artifacts](assets/hr07-training-complete-attempt-10/).

## Follow-up

The test now scopes the submit locator to the named Assign training dialog and clicks its exact `Assign` button. TST-HR-047 is resolved by this run; TST-HR-048 remains open pending a clean-baseline confirmation. No application code changed.
