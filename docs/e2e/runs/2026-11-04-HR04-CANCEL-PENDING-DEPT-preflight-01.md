# HR04-CANCEL-PENDING-DEPT — preflight stopped on a TypeScript type mismatch

**Date:** 2026-11-04 UTC  
**Classification:** Test issue (TST-HR-024)  
**Environment:** Guarded `ogami-erp-e2e` project  
**Result:** Playwright did not start; no actor authenticated and no business action ran.

## Evidence and outcome

The guarded runner stopped while compiling `hr-leave.process.spec.ts`:

```text
e2e-process/hr-leave.process.spec.ts(1284,37): error TS2345:
Argument of type 'string' is not assignable to parameter of type 'LeaveCancellationStage'.
```

The test helper declared `currentStatus()` as `string`, although its branches return only the three cancellation stages and the notification invariant requires `LeaveCancellationStage`. The seeded E2E baseline remained transaction-free because Playwright setup never ran.

## Correction

Changed the helper's return annotation to `LeaveCancellationStage`. `npm run typecheck:e2e` then completed without TypeScript errors. The browser journey will run separately from the unchanged fresh baseline.
