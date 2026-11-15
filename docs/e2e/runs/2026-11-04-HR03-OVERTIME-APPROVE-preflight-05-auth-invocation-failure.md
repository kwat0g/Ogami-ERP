# HR03-OVERTIME-APPROVE — auth invocation preflight 05

**Result:** Auth setup failed before opening a role session. The business journey did not run.

- **Command:** `node scripts/e2e/runner.mjs test --grep HR03-OVERTIME-APPROVE`
- **Playwright result:** 1 auth-setup test failed; 1 business journey did not run.
- **Cause:** The command did not set `E2E_AUTH_ACTORS`. `.env.e2e` supplies an empty value, which Compose passes to Playwright as an empty string. Auth setup parses this as an empty requested actor list and correctly stops before login. The seeded manifest contains `employee` and `department_head`.
- **Business effects:** No actor signed in and no process record or transaction was created.
- **Finding:** Recurrence of [TST-INV-014](../FINDINGS.md#tst-inv-014--direct-compose-invocation-omitted-the-targeted-actor-selector). The corrected invocation must explicitly select both actor names.

## Evidence

The archived [failure screenshot](assets/hr03-overtime-approve-auth-actors-empty/test-failed-1.png) shows the login page before any credentials were entered. The [Playwright error context](assets/hr03-overtime-approve-auth-actors-empty/error-context.md) contains the failed selection assertion. There is no trace because no browser page was opened.
