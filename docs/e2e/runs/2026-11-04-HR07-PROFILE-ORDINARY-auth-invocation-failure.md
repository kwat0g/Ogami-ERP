# HR07-PROFILE-ORDINARY — Auth selector invocation failure

**Date:** 2026-11-04 UTC  
**Classification:** Test invocation issue; business journey did not run  
**Environment:** Dedicated `ogami-erp-e2e` Compose project; freshly seeded baseline

The guarded runner's E2E TypeScript preflight passed. Playwright then ran one auth-setup test and one selected business test using a single worker. Auth setup failed immediately with `E2E_AUTH_ACTORS did not match seeded actors.` because the command did not pass the selected actor list through Compose interpolation. No role signed in, the business journey did not start, and no process action or transactional record was created.

The screenshot, error context, and Playwright JSON result are archived in [the attempt assets](assets/hr07-profile-ordinary-auth-selector-failure/). This is a recurrence of [TST-INV-014](../FINDINGS.md#tst-inv-014--direct-compose-invocation-omitted-the-targeted-actor-selector). The next attempt will start from a new seed and explicitly pass `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer`.
