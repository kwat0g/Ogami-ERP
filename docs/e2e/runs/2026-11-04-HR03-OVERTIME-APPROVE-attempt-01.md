# HR03-OVERTIME-APPROVE — attempt 01

**Result:** The selected-role real UI sign-in passed for Employee and Department Head. The business journey failed in its first step when Playwright found two buttons named “Apply for OT.” The submit action was never reached; no overtime request was created. The approval step was blocked.

- **Run ID:** `0413656a-00c9-4040-935d-04d1ae6b41a8`
- **Actors:** Employee and Department Head, each in a separate browser context.
- **Baseline-derived input:** Employee `E2E-2FDC0177057D3A5`, Manila date 2026-11-04, minimum configured request 0.5 hours, and run-scoped reason `E2E-0413656a-00c OT approval journey`.
- **Execution:** Dedicated E2E baseline; one headless Chromium worker; no retries.
- **Playwright result:** 1 selected-role auth setup passed; 1 business journey failed; 1 worker; 16.8 seconds.
- **Environment:** Isolated `ogami-erp-e2e` Compose project and E2E database only.

## Steps and outcomes

| Step | Actor | UI action | Observed result |
|---|---|---|---|
| 1 | Employee | Dashboard → Profile menu → Overtime; select Apply for OT | The empty state rendered a page-header “Apply for OT” button and an identical empty-state button. The exact-name locator matched both and strict mode stopped the step before either was clicked. No request form was opened and no mutation request was sent. |
| 2 | Department Head | Follow the Overtime badge and approve | Blocked because step 1 failed. |

## Verification and limits

- The failing action occurred before the request form, date, hours, reason, or submit button was touched. The E2E baseline had been freshly seeded and contained no transactional demo records.
- No business record was created or advanced. No notification, badge, audit, attendance, payroll, or email side effects were expected from this failed attempt.
- This is an E2E locator issue, TST-HR-038, not an application defect. The fix is to scope the click to the visible page-header action and retain an exact-count assertion so a future duplicate remains visible.

## Artifacts

Playwright failure screenshots, error context, journey context, and a sanitized 210-entry trace are archived in [attempt assets](assets/hr03-overtime-approve-attempt-01/). The trace redacts cookie, CSRF, session, and authorization values.
