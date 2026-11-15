# HR04-APPROVE — Leave-type locator stopped before submission

**Date:** 2026-11-04 UTC  
**Classification:** Test issue (TST-HR-017)  
**Environment:** Guarded `ogami-erp-e2e` Compose project; fresh 37-actor baseline with opening leave entitlements  
**Result:** Selected-actor auth setup passed; the first journey step failed before submission. No retries.  
**Journey run ID:** `cf61faa4-02da-4364-8f2f-69bad1fd8ab7`

## Outcome

The Employee, Department Head, and HR Officer signed in through their own UI sessions. The baseline entitlement precondition passed after the E2E fixture correction. The Employee navigated through Profile → Leaves, opened “File Leave Request,” and the visible form showed the Leave type, date, document, half-day, reason, and submit controls. The test's exact-label `getByLabel('Leave type')` locator did not resolve the select, so it stopped before entering data or submitting.

The saved context marks the Department Head and HR approval steps blocked. No API mutation, request, approval, notification, audit row, email, or attendance change occurred.

## Evidence

- [Journey screenshot](assets/hr04-approve-attempt-02/test-failed-2.png)
- [Trace](assets/hr04-approve-attempt-02/trace.zip)
- [Error context](assets/hr04-approve-attempt-02/error-context.md)
- [Saved journey context](assets/hr04-approve-attempt-02/journey-context.json)
- [Playwright process results](assets/hr04-approve-attempt-02/process-results.json)

The locator is scoped to the visible request dialog and uses the accessible combobox name with a prefix to allow the required-field marker in the rendered name. The intended option remains the exact “Vacation Leave” value. The corrected locator is in the next attempt; the process assertion is unchanged.

## Coverage

No leave status transition was exercised. The approved UI journey remains incomplete until a clean-baseline run passes submission, both approval hand-offs, and the resulting balance/attendance assertions.
