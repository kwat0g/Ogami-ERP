# HR04-CANCEL-APPROVED — Leaves page bundle exceeded the route-ready timeout

**Date:** 2026-11-04 UTC  
**Environment:** Guarded `ogami-erp-e2e` project; fresh baseline and isolated E2E actors  
**Result:** **1 selected-role auth setup passed; the journey failed before its first transactional step** (journey 17.6 seconds; total command 34.1 seconds)  
**Record created:** none; the Employee never submitted a leave request

## What happened

The Employee navigated through Profile → Leaves. The browser reached `/self-service/leaves`, but the expected **My Leave Requests** heading did not render within the helper's 8-second assertion timeout. The screenshot shows the shared page header and an empty main region. The test stopped before creating a journey context or transactional record; Department Head approval, HR approval, and cancellation did not run.

## Evidence and classification

- The Playwright trace shows the lazy-loaded `leaves-Cj53DmgJ.js` script request began at `11:55:04.121Z`, completed successfully with HTTP 200 after **8.38 seconds**, and the route still had in-flight data requests when the test context closed.
- Nginx logged those post-navigation API calls as client-aborted HTTP 499s after the browser closed, including leave types, leave balances, request options, self-service requests, and business policies. No server 5xx was observed for this journey.
- **Classification:** Test issue TST-HR-027 — the local 8-second heading timeout was shorter than the measured lazy-route load in the constrained, cold E2E stack. No process action or application defect was established.
- **Correction:** `openSelfServiceLeaves()` now waits up to 20 seconds for the same exact heading using Playwright's web-first locator assertion. This is a bounded, condition-based wait informed by the 8.38-second trace measurement; it adds no fixed sleep and changes no product assertion.
- **Environment:** the container memory caps were respected; sampled API, DB, Nginx, SPA, and queue-main usage was below their limits. This was a cold route-load latency, not an OOM event.

## Evidence

- [Blank main-region screenshot](assets/hr04-cancel-approved-attempt-03/test-failed-1.png)
- [Playwright error context and DOM snapshot](assets/hr04-cancel-approved-attempt-03/error-context.md)
- [Playwright process results](assets/hr04-cancel-approved-attempt-03/process-results.json)
- [Trace](assets/hr04-cancel-approved-attempt-03/trace.zip)

No test retry occurred. A fresh-baseline run is required to complete the approved-cancellation variant.
