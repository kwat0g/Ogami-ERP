# HR07-TRAINING-COMPLETE — attempt 02 (environment interruption)

**Classification:** Environment issue  
**Run ID:** `a538f68a-725b-4efe-9701-4c5925e2806a`  
**Started:** 2026-11-04 17:24:34 UTC  
**Environment:** Guarded `ogami-erp-e2e` stack; `ogami_e2e` database  
**Execution:** Headless Chromium, one worker, no retries

## Result

Selected-role authentication passed. After the test initialization correction, HR reached Human Resources → Training Programs, opened **Add training**, and saw the form heading. During that step, the browser received several HTTP 500 responses and then a 401 for the authenticated user request. The SPA returned to Sign in with its server-error banner before the first form field could be entered. No training course or employee-training assignment was submitted.

| Step | Actor | Outcome |
|---|---|---|
| Create training course through Training Programs | `hr_officer` | Blocked by environment failure before field entry or POST |
| Assign training to department employee | `hr_officer` | Blocked |
| Follow upcoming-training badge | `department_head__hr` | Blocked |
| Complete training and observe live update | `hr_officer`; `department_head__hr` observer | Blocked |

## Evidence

- Playwright result: 1 auth-setup test passed, 1 journey test failed; 40.6 seconds total.
- The archived trace shows dashboard/API requests returning HTTP 500 for endpoints that query `notifications`, `sessions`, and `products`; a later `/api/v1/auth/user` request returned 401 and the page navigated to `/sign-in`.
- The API and PostgreSQL logs at 2026-11-04 17:24:50–17:24:53 UTC record `relation does not exist` for those three tables while the browser journey was active.
- A read-only database inspection afterward found 274 tables and 831 recorded migrations, including the restored baseline. The cause and source of the transient schema disappearance were not established. This test invocation did not start another seed/reset after the initial baseline setup.
- No course form POST was sent and no process transition occurred.
- Archived trace, journey context, failure screenshots, and Playwright error context: [attempt artifacts](assets/hr07-training-complete-attempt-02/).

## Follow-up

The initial sidebar/session initialization issue (TST-HR-043) is corrected: the run reached the Add training heading through the sidebar. ENV-E2E-006 records the transient schema loss and must be resolved or shown stable before a fresh-baseline process run can continue. No application change or journey retry was made in this attempt.
