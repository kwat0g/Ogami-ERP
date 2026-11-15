# HR07-TRAINING-COMPLETE — attempt 05

**Classification:** Test issue (baseline name normalization)  
**Run ID:** `16361893-6dbc-4712-b27f-1951d99dd51c`  
**Started:** 2026-11-04 17:53:15 UTC  
**Environment:** Guarded `ogami-erp-e2e` stack; fresh baseline created and held under runner lock  
**Execution:** Headless Chromium, one worker, no retries

## Result

The HR Officer created the course through the UI. The full course step passed: the UI returned HTTP 201, the list showed the course, read-only checks confirmed active status and 12-month validity, the audit action/actor matched, no assignment existed, journey-actor Mailpit IDs were unchanged, and both pages had clean diagnostics.

In the next step, HR found and clicked the seeded Employee row through the Employees list. The test expected the exact heading `E2E department head  hr`, assembled by joining untrimmed baseline name fields. The detail page rendered `E2E department head hr`. The locator timed out before selecting the Trainings tab or assigning a course. No employee-training record was created; the last two steps were blocked.

| Step | Actor | Outcome |
|---|---|---|
| Create training course through Training Programs | `hr_officer` | Passed UI creation and all course, audit, email, assignment, and page-diagnostic invariants |
| Assign training to department employee | `hr_officer` | Failed at exact Employee detail heading after the list row was opened; no assignment sent |
| Follow upcoming-training badge | `department_head__hr` | Blocked |
| Complete training and observe live update | `hr_officer`; `department_head__hr` observer | Blocked |

## Evidence

- Playwright result: 1 auth-setup test passed, 1 journey test failed; 39.4 seconds total.
- Journey context contains the untrimmed employee name with two adjacent spaces. Screenshot 002 shows the employee detail heading with one space.
- No training assignment POST was sent.
- Archived trace, journey context, failure screenshots, and Playwright error context: [attempt artifacts](assets/hr07-training-complete-attempt-05/).

## Follow-up

The test now trims each source name component before joining them for the exact heading assertion. This keeps the UI assertion exact while normalizing whitespace in the reference data. TST-HR-046 remains open pending a clean-baseline confirmation.
