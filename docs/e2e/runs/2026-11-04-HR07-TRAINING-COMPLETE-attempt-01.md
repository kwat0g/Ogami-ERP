# HR07-TRAINING-COMPLETE — attempt 01

**Classification:** Test issue (session initialization)  
**Run ID:** `bfbbad52-0cc5-44dc-b5d9-1dbb82e0ba59`  
**Started:** 2026-11-04 17:18:51 UTC  
**Environment:** Guarded `ogami-erp-e2e` stack; `ogami_e2e` database  
**Execution:** Headless Chromium, one worker, no retries

## Result

Playwright selected-role authentication passed. The business journey failed in step 1 while HR was opening **Human Resources → Training Programs**. `actorPage` creates an authenticated browser context but leaves its new page at `about:blank`; the test called the sidebar helper before loading the authenticated ERP shell. The Training Programs sidebar link therefore resolved to zero elements.

The failure happened before opening the course form or submitting any request. No course or employee-training assignment was created, and no process transition occurred. The remaining three business steps were blocked. This is a test setup failure, not evidence about the product workflow.

| Step | Actor | Outcome |
|---|---|---|
| Create training course through Training Programs | `hr_officer` | Failed before form navigation; no write attempted |
| Assign training to department employee | `hr_officer` | Blocked |
| Follow upcoming-training badge | `department_head__hr` | Blocked |
| Complete training and observe live update | `hr_officer`; `department_head__hr` observer | Blocked |

## Evidence

- Error: expected one `/hr/trainings` link under the Modules navigation; received zero after the 8-second locator timeout.
- Playwright result: 1 auth-setup test passed, 1 journey test failed; 22.2 seconds total.
- No API write or transactional record creation was reached.
- Archived trace, journey context, failure screenshots, and Playwright error context: [attempt artifacts](assets/hr07-training-complete-attempt-01/).

## Follow-up

The journey now loads the application's authenticated `/dashboard` landing destination and asserts the Modules navigation before using the Training Programs sidebar link. This preserves user navigation for the business action. A fresh guarded baseline and one clean confirmation run are required before this test issue can be closed.
