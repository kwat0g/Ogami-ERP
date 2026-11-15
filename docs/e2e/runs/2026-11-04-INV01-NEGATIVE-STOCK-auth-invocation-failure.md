# INV01-NEGATIVE-STOCK — Auth selector invocation failure

**Date:** 2026-11-04 UTC  
**Classification:** Test invocation issue; business journey did not run  
**Environment:** Dedicated E2E Compose profile; freshly seeded baseline

The first direct Compose Playwright invocation omitted the selected actor environment variable from the Compose command. The `auth-setup` test therefore failed before opening a browser login or creating role sessions: `E2E_AUTH_ACTORS did not match seeded actors.` The isolated baseline contained no process records, and no business action was attempted.

The failure screenshot and error context are preserved under [attempt assets](assets/inv01-negative-stock-auth-invocation-01/). The corrected invocation passes `E2E_AUTH_ACTORS=system_admin,warehouse_staff,finance_officer` to Compose explicitly. This is not counted as a business journey result.
