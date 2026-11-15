# HR07-TRAINING-COMPLETE — attempt 04

**Classification:** Test issue (audit snapshot expectation)  
**Run ID:** `b7cf990f-29ba-4c9d-a88f-da2aec2a8ccd`  
**Started:** 2026-11-04 17:46:49 UTC  
**Environment:** Guarded `ogami-erp-e2e` stack; fresh baseline created and held under runner lock  
**Execution:** Headless Chromium, one worker, no retries

## Result

Selected-role authentication passed. HR created the run-scoped training course through the UI; the API returned HTTP 201, the list showed the course, and the read-only snapshot confirmed `is_active = true` and 12-month validity. The step then failed because the test expected the audit row's `new_values` to contain the database-defaulted `is_active` field. The audit row had the correct `created` action and HR actor, but that field was absent. No employee-training assignment or completion step ran.

| Step | Actor | Outcome |
|---|---|---|
| Create training course through Training Programs | `hr_officer` | UI creation and course-state checks passed; audit assertion failed on absent default field |
| Assign training to department employee | `hr_officer` | Blocked |
| Follow upcoming-training badge | `department_head__hr` | Blocked |
| Complete training and observe live update | `hr_officer`; `department_head__hr` observer | Blocked |

## Evidence

- Playwright result: 1 auth-setup test passed, 1 journey test failed; 28.6 seconds total.
- The UI-created course was visible in Training Programs and read back as active with 12-month validity.
- The assertion projected `audit_logs.new_values->>'is_active'`; it returned null. The audit action was `created`, and its actor email matched `hr_officer`.
- `HasAuditLog` captures the model's current attributes on the `created` event. The database default for `is_active` is present on the course row but was not present in the audit snapshot.
- No employee training assignment request was sent; downstream process steps were blocked.
- Archived trace, journey context, failure screenshots, and Playwright error context: [attempt artifacts](assets/hr07-training-complete-attempt-04/).

## Follow-up

The course invariant now checks the active database value separately and asserts the exact audit action and actor without expecting a database-defaulted attribute in `new_values`. This corrects the test's source-derived expectation; it does not change application behavior. A fresh-baseline run must confirm the revised audit assertion.
