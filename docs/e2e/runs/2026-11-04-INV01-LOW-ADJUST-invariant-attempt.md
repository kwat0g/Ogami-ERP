# INV01-LOW-ADJUST — notification invariant attempt

**Date:** 2026-11-04 UTC  
**Journey:** `INV01-LOW-ADJUST`  
**Environment:** Dedicated `ogami-erp-e2e` / `ogami_e2e` only  
**Result:** Failed at the final read-only notification invariant after the UI transaction and live observer checks passed

## Steps

| Step | Actor | Result |
|---|---|---|
| Finance opens Stock Adjustments and remains on the list as an observer | `finance_officer` | Passed. No adjustment with this run's reason existed before submission. |
| Warehouse creates and submits a ₱120 inbound adjustment for 1 kg of RM-001 | `warehouse_staff` | UI submission passed. The record appeared on Finance's already-open list without a refresh, displayed Approved, and exposed no Approve, Reject, or Withdraw action. The read-only state assertion confirmed the approved record and one `adjustment_in` movement for 1 kg / ₱120. |
| Check notification and audit invariants | `warehouse_staff` | The journey stopped because its notification query matched 12 notices from other subject types sharing the adjustment's numeric ID. No subsequent journey action was attempted. |

## Investigation

The initial query filtered only `notifications.data.subject_id`. The newly created adjustment had numeric ID `1`; the 12 matching notices belonged to BOM cost updates, low-stock alerts, and pricing review. A read-only query constrained by the stock-adjustment subject type returned zero notifications for this adjustment. A separate read-only check confirmed the audit actor was the Warehouse account. The low-value branch in `api/app/Modules/Inventory/Services/StockAdjustmentService.php` posts stock immediately and sends approval notices only in the above-threshold branch.

This is test issue **TST-INV-006**, not a product finding. Both notification assertions now include the exact stock-adjustment subject type. The journey must be rerun from a fresh E2E baseline before this correction is considered resolved.

## Evidence

- [Finance observer before submission](assets/inv01-low-adjust-invariant-attempt-2026-11-04/001-finance-opens-stock-adjustments.png)
- [Finance sees the approved adjustment without refresh](assets/inv01-low-adjust-invariant-attempt-2026-11-04/003-finance-sees-approved-adjustment.png)
- Playwright failure screenshots: [failure 1](assets/inv01-low-adjust-invariant-attempt-2026-11-04/failure-01.png), [failure 2](assets/inv01-low-adjust-invariant-attempt-2026-11-04/failure-02.png), [failure 3](assets/inv01-low-adjust-invariant-attempt-2026-11-04/failure-03.png)
- The raw trace remains in the ignored local output at `spa/test-results/process/inventory.process-INV01-LO-abec4-nd-Finance-observes-it-live-chromium-process/trace.zip`; it is not copied into documentation because it can contain authenticated browser state.
- The read-only database check found status `approved`, value ₱120, one stock movement (`adjustment_in`, quantity `1.000`, total cost `120.00`), zero notifications with the matching stock-adjustment subject type, and the Warehouse audit actor.

No application code was changed. The transaction was created and submitted through the Warehouse UI; no API or database mutation was used.
