# HR04-INSUFFICIENT-BALANCE — attempt 02

**Result:** The UI submission reached the expected business-rule refusal. Read-only post-action checks confirmed no request, approval, audit, attendance, balance, notification or email mutation. The step then failed because the browser emitted a console resource error for the intentionally expected HTTP 422, and the general page diagnostics treated it as unexpected.

- **Run ID:** `2392092b-a531-4336-8a30-5c4ac0db524d`
- **Environment:** guarded `ogami-erp-e2e`; fresh migration and E2E seed; headless Chromium; one worker
- **Actor:** Employee; Department Head and HR remained logged in on their own observer sessions
- **Input:** Vacation Leave; 2026-11-06 through 2026-11-24; 16 working days requested against 15.0 remaining
- **UI/API result:** POST returned HTTP 422 with `Insufficient leave balance for 2026 (15.0 remaining; 16 requested).` The form remained open and showed the generic toast `Failed to submit leave request.`
- **Failure:** `PageDiagnostics.assertClean()` reported Chromium's console message `Failed to load resource: the server responded with a status of 422 (Unprocessable Content) at /api/v1/leaves/requests`. The test had not declared that exact 422 as an expected response to its one submission.
- **Correction:** Add a one-use, exact-fragment console expectation immediately before the UI submit. Keep the response status/body assertions and all other console/network diagnostics active.

The form-unit locator correction from attempt 01 was confirmed: the journey matched “16 days,” submitted through the UI, and continued through all read-only side-effect assertions. No application behavior was changed.

Evidence: [rejection screenshot](runs/assets/hr04-insufficient-balance-attempt-02/leave-rejection.png), [trace](runs/assets/hr04-insufficient-balance-attempt-02/trace.zip), [Playwright error context](runs/assets/hr04-insufficient-balance-attempt-02/error-context.md), and [journey context](runs/assets/hr04-insufficient-balance-attempt-02/journey-context.json).
