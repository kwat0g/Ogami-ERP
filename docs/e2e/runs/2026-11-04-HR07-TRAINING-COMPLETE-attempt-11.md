# HR07-TRAINING-COMPLETE — attempt 11

**Classification:** Defect (training assignment returns HTTP 500; FND-HR-004 / GAP-HR-07-01)  
**Run ID:** `3385765d-2941-4d2b-b936-13c4bfb3817f`  
**Started:** 2026-11-04 18:22 UTC  
**Environment:** Guarded `ogami-erp-e2e` Compose project and `ogami_e2e` database; fresh baseline  
**Execution:** Headless Chromium, one worker, no retries

## Result

One auth-setup test passed and the journey test failed (40.2 seconds total Playwright time; 27.7 seconds for the journey). HR Officer and Department Head signed in through the real login UI. HR created the run-scoped active training course through Training Programs; its status, audit actor, no-assignment state, email state, and page diagnostics passed. HR then found the seeded Employee through the Employees list, opened the Trainings tab, selected the course and date, and clicked the exact `Assign` button in the dialog. The assignment POST returned HTTP 500. The Department Head hand-off and HR completion were blocked at this failed prerequisite.

| Step | Actor | Outcome |
|---|---|---|
| Create course through Training Programs | `hr_officer` | Passed UI creation and all first-step invariants |
| Assign course to Employee | `hr_officer` | UI path and exact submit locator passed; POST returned HTTP 500 |
| Follow upcoming-training badge | `department_head__hr` | Blocked by GAP-HR-07-01; no assignment existed to discover |
| Complete training and observe live update | `hr_officer`; `department_head__hr` observer | Blocked by GAP-HR-07-01; no scheduled record existed |

## Evidence

- Playwright result: auth setup passed; the single journey failed on its strict expected-201 assertion after receiving 500. No retry or assertion relaxation was made.
- The API exception is `Illuminate\\Database\\Eloquent\\MassAssignmentException: Add fillable property [status] to allow mass assignment on [App\\Modules\\HR\\Models\\EmployeeTraining]`.
- `api/app/Modules/HR/Services/EmployeeTrainingService.php:48-56` calls `EmployeeTraining::create()` with `status => EmployeeTrainingStatus::Scheduled`. `api/app/Modules/HR/Models/EmployeeTraining.php:24-37` excludes `status` from `$fillable` and documents that status is changed through `forceFill()` inside services.
- A read-only E2E database query found zero `employee_trainings` rows for employee `E2E-32663B6B7DA1372` and course `E2E-a7a8d9f6 Competence Training`, confirming the failed transaction did not leave the assignment behind.
- Trace, failure screenshots, page error context, journey context, and the course-creation walkthrough are archived in [attempt artifacts](assets/hr07-training-complete-attempt-11/). The failed submit is visible in [failure screenshot 1](assets/hr07-training-complete-attempt-11/test-failed-1.png); the course step is documented in [walkthrough screenshot 001](assets/hr07-training-complete-attempt-11/walkthrough/001-hr-creates-a-run-scoped-training-course-through-training-programs.png).

## Follow-up

FND-HR-004 / GAP-HR-07-01 is open. The training assignment, scheduled status, notification/badge hand-off, completion transition, and live observer update remain unverified. Do not advance this record through API or database writes. The test remains active and failing so a future approved Phase 7 fix can be verified through the same UI journey. TST-HR-048 is resolved: the scoped exact `Assign` button locator clicked successfully. No application code changed.
