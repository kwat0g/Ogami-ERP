# PROD01-HAPPY — Attempt 07: incorrect heading expectation stopped PPC before work-order creation

**Date:** 2026-11-05 UTC  
**Outcome:** Four steps passed; PPC page assertion failed; 16 dependent steps blocked  
**Classification:** Test issue TST-PROD-006; external-test interruption also recorded under ENV-E2E-008  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Fresh guarded E2E baseline; six selected actors authenticated through the real login UI; headless Chromium with tablet/touch emulation; one worker. A host PHP test started during the test-split wrapper and the supervisor stopped only the isolated E2E runner and containers.

## Steps and actors

| Step | Actor | Result |
|---|---|---|
| Assign Production shift coverage to the E2E operator | HR Officer | Passed through Attendance & DTR → Shifts → Bulk assign |
| Post baseline RM-001 stock | Warehouse Staff | Passed through Stock Adjustments; status, actor, movement count, balance, and audit count assertions passed |
| Post baseline RM-010 stock | Warehouse Staff | Passed through Stock Adjustments; exact movement and balance assertions passed |
| Post baseline PKG-001 stock | Warehouse Staff | Passed through Stock Adjustments; exact movement and balance assertions passed |
| Create the WB-001 stock work order | PPC Head | Failed before clicking Produce for stock; subsequent 16 steps were marked blocked |

The four completed steps created only the intended E2E shift assignment and Warehouse stock-adjustment transactions through their owners' UIs. Their journey screenshots are retained in the walkthrough assets.

## Failure

PPC navigated via Production → Production Work Orders. The page loaded and showed the `Produce for stock` action, but the test expected a heading named `Production Work Orders`. The rendered page heading is `Work orders`, which is the literal `PageHeader` title in `spa/src/pages/production/work-orders/index.tsx`. Playwright recorded one failed test after 54.1 seconds. No work-order form was opened and no work order was created.

The test expectation is corrected to the exact visible heading, `Work orders`. The route, navigation path, and subsequent exact `Produce for stock` assertions remain intact. This correction does not loosen a business-state assertion or change application code.

## Environment interruption and artifacts

The supervisor subsequently detected a host PHP test during the single-journey wrapper. It terminated the runner and stopped the 12 `ogami-erp-e2e-session` containers; the external PHP test was left untouched. The monitor did not retain its command. The Playwright test output and journey context had already recorded the PPC failure. The runner was terminated before its automatic trace-cleanup hook, so the trace was sanitized manually: 2,651 credential-like values were redacted, then the scanner reported zero remaining matches. No E2E container remains running. Another fresh baseline is required for the next attempt.

- [Journey context: 4 passed, 1 failed, 16 blocked](assets/prod01-happy-attempt-07/journey-context.json)
- [Playwright error context and PPC page snapshot](assets/prod01-happy-attempt-07/error-context.md)
- [Sanitized trace](assets/prod01-happy-attempt-07/trace.zip)
- [Screenshots and step walkthrough](assets/prod01-happy-attempt-07/)
- Frontend heading source: `spa/src/pages/production/work-orders/index.tsx`
