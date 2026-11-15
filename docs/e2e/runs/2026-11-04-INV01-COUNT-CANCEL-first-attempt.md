# INV01-COUNT-CANCEL — first attempt

**Date:** 2026-11-04 UTC  
**Environment:** Dedicated `ogami-erp-e2e` / `ogami_e2e` only; freshly reset before the run  
**Result:** Step 1 failed before session creation; observer and cancellation steps blocked

The real login setup passed for **35/35** seeded actors, and E2E support TypeScript compilation passed. The Warehouse actor reached the Stock Count view through Warehouse Map and opened the New count session modal. The test then timed out trying to resolve the modal's Scope control with `getByLabel('Scope', { exact: true })`. No session was submitted or created, so the baseline remained without a transactional count record. The observer and cancellation steps were blocked by the process runner.

The source renders Scope as a labeled native select. The exact reason the exact-label query did not resolve is not proven. The selector was changed to resolve the visible control by its combobox role and a `Scope` name prefix; it passed in the [complete clean-baseline run](2026-11-04-INV01-COUNT-CANCEL-full-run.md). No application behavior was changed and no product defect is inferred from this test issue.
