# INV01-NEGATIVE-STOCK — First browser attempt

**Date:** 2026-11-04 UTC  
**Classification:** Test issue; journey incomplete  
**Environment:** Dedicated `ogami-erp-e2e` Compose project; fresh `ogami_e2e` baseline  
**Playwright:** Auth setup passed; business journey failed in 41.9 seconds, one worker, no retries  
**Run ID:** `0109ef34-402e-41f8-8e28-6eb3d0c20f5e`

## Steps

| # | Actor | UI step | Result |
|---|---|---|---|
| 1 | `finance_officer` | Finance → Stock Adjustments before work exists | Passed. The list had no matching run marker; read-only DB assertion confirmed no process record. |
| 2 | `warehouse_staff` | Create and post 1 kg RM-001 via Warehouse → Stock Adjustments | Passed. UI showed the adjustment as approved. Read-only assertion confirmed one adjustment movement and 1.000 kg on hand. |
| 3 | `warehouse_staff` | Open a new outbound adjustment and select RM-001 | Incomplete due to a test locator issue before the attempted quantity was entered. The UI had auto-filled the stocked location as `MW-A-A-01 · Main Warehouse`; the test incorrectly searched for a native `<option>` below the accessible combobox, although the custom `SearchableSelect` renders that input separately. No outbound request was submitted. |

The business journey reached 2/3 planned steps. The required negative quantity check did not run. This is not evidence of a product defect. The UI-created prerequisite stock remained in this failed run's baseline; the next attempt uses a fresh reseed.

## Failure evidence

The error screenshot shows the correct outbound form and available quantity (1 kg), with the location auto-selected. The step failed only because the locator inspected the hidden native-select subtree instead of the visible combobox value.

![Outbound form with the stocked location auto-filled and 1 kg removable](assets/inv01-negative-stock-attempt-02-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/test-failed-3.png)

The archived [journey context](assets/inv01-negative-stock-attempt-02-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/journey-context.json), [Playwright trace](assets/inv01-negative-stock-attempt-02-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/trace.zip), screenshots, and network recording are retained. The locator now asserts the visible combobox value and the UI's “Filled from the item's stocked location” message.
