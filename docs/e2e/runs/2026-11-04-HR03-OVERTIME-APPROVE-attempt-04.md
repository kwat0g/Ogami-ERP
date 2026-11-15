# HR03-OVERTIME-APPROVE — attempt 04

**Result:** Employee submission passed. The Department Head session received the expected overtime badge and the dashboard showed the pending approval row, but the test helper timed out looking for the nested sidebar signal button. No approval was performed in this attempt.

- **Run ID:** `4cb45262-5d9c-40a1-b402-919756846c6b`
- **Actors:** Employee and Department Head in separate browser contexts.
- **Baseline:** Fresh reset through the guarded E2E runner; E2E actors only.
- **Execution:** Headless Chromium, one worker, retries disabled. Both selected-role UI sign-ins passed. The business test failed after Employee submission.
- **Environment:** Dedicated `ogami-erp-e2e` Compose project and `ogami_e2e` database.

## Steps and outcomes

| Step | Actor | Outcome |
|---|---|---|
| Submit overtime request | Employee | Passed through Self-service Overtime. The request was pending and the Department Head was the intended recipient. |
| Find the approval hand-off | Department Head | The dashboard showed one overtime approval in the Human Resources badge summary and a pending Overtime row with a link to the request. The helper expanded Human Resources and hovered the Overtime link, then timed out waiting for `View all Overtime signals`. |
| Open and approve the request | Department Head | Not reached. No approval request was sent. |

The archived trace records the HR section opening and the Overtime link hover before the signal-button assertion. The final screenshot and accessibility snapshot show the section collapsed again and the dashboard's Pending Approvals row. The row is a valid user-facing hand-off to the linked record; the next attempt will follow it and assert the badge summary rather than depend on the unavailable nested signal control. This keeps the Department Head action in the correct logged-in session and does not bypass the process.

The request remained pending until the next guarded baseline reset. No product finding is assigned from this attempt because the dashboard's linked approval row remains actionable and the exact cause of the nested sidebar signal locator failure is not isolated.

## Artifacts

Sanitized [Playwright trace](assets/hr03-overtime-approve-attempt-04/trace.zip), [error context](assets/hr03-overtime-approve-attempt-04/error-context.md), [journey context](assets/hr03-overtime-approve-attempt-04/journey-context.json), [failure screenshot](assets/hr03-overtime-approve-attempt-04/test-failed-2.png), and [Employee submission walkthrough](assets/hr03-overtime-approve-attempt-04/walkthrough/001-employee-submits-a-self-service-overtime-request.png) are archived. The trace scrubbed sensitive header fields before retention.
