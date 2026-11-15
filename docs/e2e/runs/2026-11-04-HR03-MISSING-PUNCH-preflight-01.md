# HR03-MISSING-PUNCH — preflight attempt 01

**Result:** TypeScript preflight failed before Playwright setup. No actor logged in and no process record was created.

- **Failure:** The last step action returned `page.reload()`'s `Promise<Response | null>`, but `runProcessStep` requires an action that resolves to `void`.
- **Correction:** Awaited the reload inside a block-bodied async function so the step action resolves to `void`.
- **Evidence:** [guarded runner log](assets/hr03-missing-punch-preflight-01/runner.log); `spa/e2e-process/hr-attendance.process.spec.ts`.
- **Classification:** Test issue TST-HR-032. No application behavior was exercised.

