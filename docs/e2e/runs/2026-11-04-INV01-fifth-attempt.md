# INV-01 fifth recorded process run

**Date:** 2026-11-04 UTC  
**Environment:** Dedicated `ogami-erp-e2e` Compose project; fresh isolated baseline  
**Process:** INV-01 — high-value inbound warehouse adjustment and Finance approval  
**Result:** Failed before form submission. Exact-name lookup found no visible menu item while its section was collapsed; its bounded accessible-name fallback still timed out after section interaction.

## Run result

Playwright used headless Chromium with one worker and zero retries. Support TypeScript compilation passed, and the login setup passed for **35/35** actors. The journey failed in step 1 before opening the adjustment form. The Finance discovery and approval steps were blocked. No transactional adjustment was created.

The locator output names the failed accessible-name fallback. The baseline UI shows the Warehouse section collapsed on the role dashboard, with Stock Adjustments as a child link. Seeded role permissions still include `inventory.adjust`, and the earlier second attempt reached and passed the whole Warehouse step. This remains a test-navigation issue, not evidence of a product access-control defect.

## Test correction

The navigation helper now resolves the real sidebar anchor by its configured `href`, confirms exactly one anchor exists, expands the owning section through its UI button when needed, then clicks that anchor. The process route is still reached by clicking the visible menu link; no deep-link navigation is introduced. The purchasing helper uses this approach for internal module links and retains its accessible-label path for the supplier portal.

