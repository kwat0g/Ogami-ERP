# INV01-NEGATIVE-STOCK — Second browser attempt

**Date:** 2026-11-04 UTC  
**Classification:** Test issue; journey incomplete  
**Environment:** Dedicated `ogami-erp-e2e` Compose project; fresh `ogami_e2e` baseline  
**Playwright:** Auth setup passed; business journey failed in 45.1 seconds, one worker, no retries  
**Run ID:** `7b2de0e9-62a0-4270-ab2e-d56d48132d43`

## Steps

| # | Actor | UI step | Result |
|---|---|---|---|
| 1 | `finance_officer` | Finance → Stock Adjustments before work exists | Passed. No matching run marker existed. |
| 2 | `warehouse_staff` | Create and post 1 kg RM-001 through Stock Adjustments | Passed. UI showed Approved; read-only DB assertion confirmed one movement and 1.000 kg on hand. |
| 3 | `warehouse_staff` | Open a new outbound adjustment and choose RM-001 | Incomplete due to a strict helper-text locator expecting a straight apostrophe. The visible UI uses a typographic apostrophe. The preceding accessible location-value assertion passed, and the screenshot shows the location prefilled. The attempted quantity was not entered and no outbound submission occurred. |

The previously reported hidden-native-select locator is resolved: the custom combobox's visible selected value was successfully asserted. The remaining failure was the redundant helper-message punctuation match. The test now uses an accessible text regex to account for the rendered apostrophe while still requiring the helper text. A fresh baseline is required because the UI-created prerequisite stock exists in this run.

## Failure evidence

The full Playwright [trace](assets/inv01-negative-stock-attempt-03-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/trace.zip), [journey context](assets/inv01-negative-stock-attempt-03-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/journey-context.json), screenshots, network recording, and error context are archived.

![Outbound adjustment form with 1 kg available and the location selected](assets/inv01-negative-stock-attempt-03-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/test-failed-3.png)
