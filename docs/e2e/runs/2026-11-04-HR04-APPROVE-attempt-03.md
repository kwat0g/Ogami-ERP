# HR04-APPROVE — Required date label stopped form entry

**Date:** 2026-11-04 UTC  
**Classification:** Test issue (TST-HR-017)  
**Environment:** Guarded `ogami-erp-e2e` Compose project; fresh baseline with opening leave entitlements  
**Result:** Selected-actor auth setup passed; the Employee step stopped before submission. No retry.  
**Journey run ID:** `aa2b4a77-97b4-470e-9c76-3eee8c1446ad`

## Outcome

The Employee, Department Head, and HR Officer signed in through their own UI sessions. The Employee opened the leave request form through Profile → Leaves. The anchored combobox locator selected the exact Vacation Leave option, then the exact-label Start date locator timed out. No date was entered and the request was not submitted. The Department Head and HR steps were blocked in the journey context.

No transactional record or process side effect was created. This rerun used a newly seeded database; its journey context is archived under the run ID shown above.

## Evidence

- [Failure screenshots](assets/hr04-approve-attempt-03/)
- [Trace](assets/hr04-approve-attempt-03/trace.zip)
- [Error context](assets/hr04-approve-attempt-03/error-context.md)
- [Journey context](assets/hr04-approve-attempt-03/journey-context.json)
- [Playwright results](assets/hr04-approve-attempt-03/process-results.json)

The next locator uses anchored accessible-label prefixes for Start date and End date. It preserves the selected leave type and exact date values, and does not relax any process invariant.
