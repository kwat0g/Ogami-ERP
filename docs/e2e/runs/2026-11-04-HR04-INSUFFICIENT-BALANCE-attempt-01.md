# HR04-INSUFFICIENT-BALANCE — attempt 01

**Result:** Test helper stopped before the UI submission. The auth setup passed for Employee, Department Head and HR; the business step failed on its form-estimate locator. No leave transaction was submitted.

- **Run ID:** `0d47f7e2-8342-4788-9bb6-de2687c5d7f5`
- **Environment:** guarded `ogami-erp-e2e`; freshly migrated and seeded baseline; headless Chromium; one worker
- **Input:** Vacation Leave; 2026-11-06 through 2026-11-24; UI showed 16 working days against 15.0 days remaining
- **Failed assertion:** `getByText('16', { exact: true })` did not match the rendered text `16 days`
- **Actual result:** The screenshot confirms the UI calculation and balance display were both correct. The Playwright test expected the numeric value without the unit, while the UI renders the number and unit as one text node.
- **Product state:** The test failed before clicking Submit. It created no leave request, approval, audit, notification or email and made no balance/attendance change.
- **Correction:** Match the full rendered unit label (`16 days`, or `1 day` for a one-day request) while preserving the estimate-to-server calculation assertion. Rerun only after a fresh baseline.

Evidence: [captured form](runs/assets/hr04-insufficient-balance-attempt-01/leave-form-estimate.png), [trace](runs/assets/hr04-insufficient-balance-attempt-01/trace.zip), [Playwright error context](runs/assets/hr04-insufficient-balance-attempt-01/error-context.md).
