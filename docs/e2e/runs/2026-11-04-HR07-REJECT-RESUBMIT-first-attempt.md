# HR07-REJECT-RESUBMIT — first attempt

- **Date:** 2026-11-04 UTC
- **Journey run ID:** `19c84010-eded-4d05-b780-ae1dfd5f916b`
- **Environment:** guarded `ogami-erp-e2e` Compose project; fresh `ogami_e2e` baseline; Redis queue/cache cleared by the guarded seed command
- **Command:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-REJECT-RESUBMIT`
- **Playwright result:** 1 auth-setup test passed and 1 Chromium journey failed; all three selected actors signed in through the real login UI. Journey duration: 28.1 seconds; total command: 46.7 seconds.
- **Process result:** the Employee submitted the first bank change through Profile; HR rejected it through its queue; the Employee saw Rejected without refresh and submitted a distinct replacement through Profile. The replacement UI request returned HTTP 201. Its subsequent HR approval and Finance approval steps were blocked by a test locator error.
- **Classification:** Test issue `TST-HR-013`. The product flow had reached a valid rejected request and a second pending request. The assertion treated every expanded `Requested changes` region on the page as one element, although both the rejected and pending rows were expanded.
- **Artifacts:** [journey context, screenshots, error context, and trace](assets/hr07-reject-resubmit-first-attempt/).

## Step results

| # | Actor/session | UI action | Result |
|---|---|---|---|
| 1 | Employee, HR, Finance | Open Employee Profile and both reviewer queues | Passed from the real authenticated shell. Read-only baseline check found no request and the seeded Employee had no bank account. |
| 2 | Employee | Submit first bank change; HR follows its actionable badge | Passed. UI returned a new request in `pending`; HR saw the request and HR Approve; Finance had no actionable badge. Read-only state showed no reviewer and no account update. |
| 3 | HR Officer | Reject first request from Profile Change Requests | Passed. HR's row became `rejected` with no review buttons; Employee saw Rejected and the Request update action returned without refresh. Read-only state attributed the rejection to HR, kept Employee bank fields unchanged, and showed zero request notifications. |
| 4 | Employee, then HR queue | Submit a replacement from Profile; HR opens the pending row | Partial. Employee's UI POST returned HTTP 201 and a different public request ID. The test then failed because its page-wide requested-change locator matched both still-expanded request details. HR approval was not attempted. |
| 5 | HR Officer | Approve replacement and hand off to Finance | Blocked by step 4's test issue. |
| 6 | Finance Officer | Approve replacement | Blocked by step 4's test issue. |

## Independent read-only state check

After Playwright exited, a PostgreSQL `READ ONLY` transaction confirmed exactly two run-scoped records: the first is `rejected` with an HR reviewer; the second is `pending` with no reviewer and `requires_finance=true`. The employee's bank name remained null, its account value remained null, and both requests had zero notifications. The encrypted `changes` payload is not queryable as plaintext JSON keys, as intended by the source `EncryptedArrayCast`.

The next run starts from a fresh seeded baseline. The E2E helper now scopes the requested-change region to the unique bank name for the selected row. No application behavior was changed.
