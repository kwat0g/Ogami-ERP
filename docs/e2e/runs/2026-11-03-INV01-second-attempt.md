# INV-01 second process run

**Date:** 2026-11-03 UTC  
**Environment:** Dedicated `ogami-erp-e2e` Compose project; database `ogami_e2e`; fresh baseline reset before the run  
**Process:** INV-01 — high-value inbound warehouse adjustment and Finance approval  
**Result:** Warehouse step passed in full. Finance hand-off navigation failed because the test locator required an exact accessible name that the live unread-count badge changes. The approval step was blocked.

## Run result

The focused run used headless Chromium, one worker, and zero retries. Role-login setup passed for **35/35** actors. Overall, the runner reported **2 passed and 1 failed** (auth setup plus one business journey). Within the three-step process journey, step 1 passed; step 2 failed while locating the Notifications sidebar link; step 3 was blocked. This was a test selector issue, not a permission denial or application finding. See [TST-INV-002](../FINDINGS.md#test-and-environment-issues).

## Steps and actors

| # | Actor | UI path / action | Expected | Result |
|---:|---|---|---|---|
| 1 | `warehouse_staff` | Warehouse → Stock Adjustments → New adjustment → submit inbound RM-001, 250 kg at ₱120/kg | Pending; no stock change until approval; exact notification audience and audit actor | Passed. UI row displayed Awaiting Finance approval. Read-only checks confirmed pending status, ₱30,000 value, no movement ID, zero on hand, threshold ₱25,000, the exact intended Finance/system-admin recipient set, and Warehouse audit actor. |
| 2 | `finance_officer` | Work shortcut group → Notifications → matching adjustment notice | Finance finds work from its notification and opens the linked record | Failed before clicking. The unread-count badge changes the link's accessible name, so the test's `exact: true` role-name locator did not match. The screenshot shows the Notifications link and badge on the Finance dashboard. |
| 3 | `finance_officer` | Linked adjustment row → Approve | Approved; one stock movement; live Warehouse observer update | Blocked because step 2 failed. |

## Side effects verified

- The transaction was created and submitted only through the Warehouse UI session.
- Before approval, the adjustment was pending, had no movement ID, and on-hand was zero.
- The adjustment value exceeded the configured ₱25,000 threshold, and the UI showed the Finance approval requirement.
- Read-only notification checks matched the expected Finance officer and system administrator recipients exactly; the notification links point to the adjustment route.
- The audit record identified the Warehouse actor.
- Finance approval, stock ledger movement, completion notification, live observer update, and final totals remain unverified.

## Test correction and artifacts

The sidebar helper now locates a link by its exact visible label within the link. It handles both normal module-section toggles and the top-level Work shortcut group, which has no expand button. The same test-only correction was applied to the purchasing helper because its Approval Queue link can also carry a badge. A fresh baseline reset is required before the next run.

The failed-run screenshot is attached in Playwright output as `test-failed-2.png`; the page shows the Finance dashboard with the Work shortcuts, Notifications unread count, and Approval Queue badge. No application code was changed.

