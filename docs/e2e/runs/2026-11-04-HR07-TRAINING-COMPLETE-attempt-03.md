# HR07-TRAINING-COMPLETE — attempt 03

**Classification:** Test issue (required-field label locator)  
**Run ID:** `cbc25a8d-7618-4c5b-adc3-ede9911c6903`  
**Started:** 2026-11-04 17:42:24 UTC  
**Environment:** Guarded `ogami-erp-e2e` stack; baseline seeded and test lock held by `runner.mjs test`  
**Execution:** Headless Chromium, one worker, no retries

## Result

Selected-role authentication passed, and HR navigated through the Training Programs sidebar to the Add training form. The form was visible, including its required **Name** field, but `getByLabel('Name', { exact: true })` did not resolve the input. The step stopped before any field was filled or submitted. No training course or employee-training assignment was created; the remaining three steps were blocked.

| Step | Actor | Outcome |
|---|---|---|
| Create training course through Training Programs | `hr_officer` | Failed at the required Name label locator; no write attempted |
| Assign training to department employee | `hr_officer` | Blocked |
| Follow upcoming-training badge | `department_head__hr` | Blocked |
| Complete training and observe live update | `hr_officer`; `department_head__hr` observer | Blocked |

## Evidence

- Playwright result: 1 auth-setup test passed, 1 journey test failed; 36.4 seconds total.
- Failure: `locator.fill` timed out after 15 seconds waiting for `getByLabel('Name', { exact: true })`.
- Screenshot 002 shows the HR Add training form and visible required Name field; the trace snapshot contains the linked `<label for="input-r10">Name</label>` and input `id="input-r10"`.
- No course form POST was sent and no process transition occurred.
- Archived trace, journey context, failure screenshots, and Playwright error context: [attempt artifacts](assets/hr07-training-complete-attempt-03/).

## Follow-up

The locator now permits only the optional required-field marker around the exact Name label (`/^Name\s*\*?$/`). This preserves the field-specific label assertion. A fresh-baseline run must confirm the locator before this test issue can be closed.
