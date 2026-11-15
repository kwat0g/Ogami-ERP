# INV-01 fourth recorded process run

**Date:** 2026-11-04 UTC  
**Environment:** Dedicated `ogami-erp-e2e` Compose project; a fresh isolated baseline was seeded before the run  
**Process:** INV-01 — high-value inbound warehouse adjustment and Finance approval  
**Result:** Failed before form submission. The selector used only a prefix accessible-name pattern within the Modules navigation and did not resolve the Warehouse child link after section expansion.

## Run result

The runner returned exit code 1. Playwright used headless Chromium, one worker, zero retries; support TypeScript compilation passed and login setup passed for **35/35** seeded actors. The journey failed in step 1 before opening the adjustment form. No transaction was created; the two remaining steps were blocked.

This is the same E2E selector issue tracked in [TST-INV-002](../FINDINGS.md#test-and-environment-issues), not an RBAC finding. The seeded Warehouse role holds `inventory.adjust`; prior INV-01 attempt 2 successfully completed the Warehouse step using the original exact-name role locator. The helper now prefers that exact accessible name and falls back to a bounded name pattern only when a badge changes the name.

## Steps

| # | Actor | UI path / action | Result |
|---:|---|---|---|
| 1 | `warehouse_staff` | Dashboard → Warehouse → Stock Adjustments | Failed at sidebar link discovery before entering the form. |
| 2 | `finance_officer` | Work → Notifications → matching notice | Blocked. |
| 3 | `finance_officer` | Linked adjustment row → Approve | Blocked. |

The test failure output identifies the failed selector. No browser trace content or authentication data is reproduced here. No API/database mutation or application code change occurred. The corrected exact-first selector requires a new clean-baseline run.

