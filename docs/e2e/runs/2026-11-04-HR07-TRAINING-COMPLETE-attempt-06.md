# HR07-TRAINING-COMPLETE — attempt 06

**Classification:** Test issue (baseline name normalization; TST-HR-046)  
**Run ID:** `3fbf8cd8-8976-48c2-a493-bb5c25263230`  
**Started:** 2026-11-04 17:54 UTC  
**Environment:** Guarded `ogami-erp-e2e` stack; fresh baseline created and held under runner lock  
**Execution:** Headless Chromium, one worker, no retries

## Result

Course creation and all first-step invariants passed again. HR found and opened the seeded Employee row, then the exact detail-heading assertion failed a second time. Trimming leading/trailing spaces did not normalize the two adjacent spaces inside the baseline `last_name` (`department head  hr`); the UI rendered a single space (`department head hr`). No employee-training assignment request was sent, and the final two steps were blocked.

| Step | Actor | Outcome |
|---|---|---|
| Create training course through Training Programs | `hr_officer` | Passed UI creation and all invariants |
| Assign training to department employee | `hr_officer` | Failed at the exact detail-heading assertion; no assignment sent |
| Follow upcoming-training badge | `department_head__hr` | Blocked |
| Complete training and observe live update | `hr_officer`; `department_head__hr` observer | Blocked |

## Evidence

- Playwright result: 1 auth-setup test passed, 1 journey test failed; 39.4 seconds total.
- The read-only baseline row has `first_name = "E2E"` and `last_name = "department head  hr"`; the detail page renders it with repeated whitespace collapsed to a single space.
- The journey context still contained the double-space expected name, confirming that edge trimming alone was insufficient.
- No training assignment POST was sent.
- Archived trace, journey context, failure screenshots, and Playwright error context: [attempt artifacts](assets/hr07-training-complete-attempt-06/).

## Follow-up

The test now trims and collapses repeated whitespace in each baseline name component before joining them. The exact visible heading assertion remains. TST-HR-046 stays open until a fresh-baseline confirmation.
