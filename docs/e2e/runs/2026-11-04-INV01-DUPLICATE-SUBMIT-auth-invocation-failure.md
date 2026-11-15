# INV01-DUPLICATE-SUBMIT: auth invocation failure

**Classification:** Test invocation issue (same root cause as TST-INV-014).  
**Result:** Auth setup stopped before browser login; the business journey did not run.

The isolated stack and Playwright environment guard started correctly, but this invocation omitted `E2E_AUTH_ACTORS`. Auth setup rejected the empty selector before signing in either role. Read-only checks confirmed zero stock adjustments and zero stock movements; no process step or transaction ran.

The screenshot, error context, and Playwright last-run status are preserved in [attempt assets](assets/inv01-duplicate-submit-auth-invocation-01/). Continue from a newly seeded E2E baseline with `E2E_AUTH_ACTORS=warehouse_staff,finance_officer` included in the runner process environment. This attempt is not counted as a business journey result.
