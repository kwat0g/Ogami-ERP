# INV01-NEGATIVE-STOCK — Third browser attempt

**Date:** 2026-11-04 UTC  
**Classification:** Test issue; UI prevention observed but journey incomplete  
**Environment:** Dedicated `ogami-erp-e2e` Compose project; fresh `ogami_e2e` baseline  
**Playwright:** Auth setup passed; business journey failed in 46.3 seconds, one worker, no retries  
**Run ID:** `7e800eee-f568-4f32-a964-ecd8f9b465f6`

## Steps

| # | Actor | UI step | Result |
|---|---|---|---|
| 1 | `finance_officer` | Finance → Stock Adjustments before work exists | Passed. No matching run marker existed. |
| 2 | `warehouse_staff` | Create and post 1 kg RM-001 through Stock Adjustments | Passed. The UI showed Approved; read-only DB assertion confirmed one movement and 1.000 kg on hand. |
| 3 | `warehouse_staff` | Enter an outbound adjustment for 1.001 kg | The UI displayed “Only 1 kg can be removed from this location” and showed Review adjustment disabled. The test expected the text to include `1.000`; the actual UI formats whole kilograms as `1`. Thus the test stopped on a presentation-format assertion before its remaining disabled-button and database checks. No outbound submission was initiated. |

This run confirms the visible UI prevents the over-available request, but the journey is not marked complete because the final assertions did not finish. The assertion now matches the source-derived quantity formatting for this RM-001 baseline and retains the full availability/error check. A fresh baseline is required because this run posted prerequisite stock through the UI.

## Failure evidence

The archived [Playwright trace](assets/inv01-negative-stock-attempt-04-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/trace.zip), [journey context](assets/inv01-negative-stock-attempt-04-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/journey-context.json), screenshots, and error context preserve the failed assertion. The accessibility snapshot includes the quantity error and a disabled Review adjustment button.

![The UI limits removal to 1 kg and disables Review adjustment](assets/inv01-negative-stock-attempt-04-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/test-failed-3.png)
