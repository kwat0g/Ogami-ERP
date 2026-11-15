# HR03-OVERTIME-APPROVE — attempt 02

**Result:** Employee submission and Department Head approval completed through their own UI sessions. The open Employee page showed Approved without refresh. The journey then stopped at a read-only badge-count API assertion: the test helper's same-origin request received 401 from Sanctum. A read-only database query confirmed the record persisted as approved by a Department Head. No process step was advanced outside the UI.

- **Run ID:** `3e0fc35e-7748-4abb-955b-2b2e0079831a`
- **Actors:** Employee and Department Head in separate browser contexts; selected-role UI authentication passed.
- **Baseline-derived input:** Employee `E2E-2FDC0177057D3A5`, Manila date 2026-11-04, configured minimum 0.5 hours, run-scoped reason `E2E-3e0fc35e-774 OT approval journey`.
- **Execution:** Fresh dedicated E2E baseline; one headless Chromium worker; no retries; one auth setup passed and one journey failed after 39.3 seconds.
- **Environment:** Isolated `ogami-erp-e2e` Compose project and `ogami_e2e` database.

## Steps and outcomes

| Step | Actor | UI action | Observed result |
|---|---|---|---|
| 1 | Employee | Profile menu → Overtime → Apply for OT → submit | Request was created through the Employee UI as Pending. The Employee had a Cancel action; no approval action was available. The eligible Department Head received the linked submission notification. |
| 2 | Department Head | Follow the Overtime approval signal → open linked row → approve | Department Head approved through the UI. The Department Head detail showed Approved and removed its approval/cancellation actions. The already-open Employee list showed Approved without refresh and removed its cancellation action. Expected submission and decision notifications reached only the Department Head and Employee; their links pointed to the same request. Mailpit message IDs were unchanged. |
| 3 | Department Head | Read-only badge invariant | **Blocked at verifier:** `GET /api/v1/dashboards/badges` through `BrowserContext.request` returned 401. The UI approval and live observer assertions had already passed. The API badge count and final page diagnostic assertions were not verified. |

## Read-only evidence

- The seeded Employee's fresh baseline had zero overtime requests.
- After the UI approval, the record status was `approved`; the approving user's seeded role was `department_head`.
- Two audit rows were attached to the record. The test's prior assertions matched the creation actor to Employee and the approval actor to Department Head.
- Database notification facts were exactly `attendance.ot_submitted` to the Department Head with `/hr/attendance/overtime/<public-id>` and `attendance.ot_approved` to Employee with `/self-service/overtime/<public-id>`.
- Attendance remained unchanged for this approval-only request. The in-test read-only assertion confirmed zero attendance rows for the request date.
- The failed GET was issued by the test helper, not the SPA. It performed no mutation and did not change the approved record.

This is E2E test issue TST-HR-039. The helper now supplies the active same-origin page's `Origin` and `Referer`, plus the SPA's JSON/XHR headers, so Sanctum evaluates it as a stateful browser request. The corrected helper still requires a clean-baseline end-to-end run. No application code changed.

## Artifacts

Sanitized trace, three failure screenshots, two annotated walkthrough screenshots, error context, and journey context are archived in [attempt assets](assets/hr03-overtime-approve-attempt-02/). The trace redacts cookie, CSRF, session, authorization, password, and token values.
