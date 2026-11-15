# INV01-LOW-ADJUST — first business-step attempt

**Date:** 2026-11-04 UTC  
**Journey:** `INV01-LOW-ADJUST`  
**Environment:** Dedicated `ogami-erp-e2e` / `ogami_e2e` only; reset before the journey  
**Result:** Auth setup passed; journey failed at step 1 before any transaction was created

The Playwright run used headless Chromium with one worker and zero retries. Real UI login setup signed in **35/35** seeded actors. The journey then failed while Finance tried to open Stock Adjustments from the sidebar. The configured section argument was `Warehouse`, but Finance receives this menu link in the `Finance` section. The link existed but remained hidden inside the collapsed Finance section. No adjustment form was opened or submitted; the Warehouse business step was blocked. No transactional record was created.

The locator now expands the Finance section through its visible sidebar button. This is recorded as test issue TST-INV-005, not a product finding. The exact navigation entry is derived from `spa/src/components/layout/Sidebar.tsx`, where Finance's `visibleSections` adds the Stock Adjustments item to the Finance section.

An earlier infrastructure invocation exited 130 before Playwright emitted a result. A subsequent guarded Playwright launch reached auth setup but could not resolve `nginx`; after restarting the dedicated stack, an in-container DNS preflight resolved `nginx`, and this recorded business-step attempt proceeded. Those environment attempts created no transactional data. See ENV-E2E-002 in [FINDINGS.md](../FINDINGS.md).

**Evidence:** Playwright failure screenshots and `trace.zip` are retained under the ignored `spa/test-results/process/inventory.process-INV01-LO-abec4-nd-Finance-observes-it-live-chromium-process/` output directory. The trace is not copied into documentation because it can contain authenticated browser state.
