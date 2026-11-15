# INV-01 attempt 6 — 2026-11-04

**Journey:** `INV01-HIGH-VALUE-APPROVAL`  
**Environment:** Dedicated `ogami-erp-e2e` / `ogami_e2e` only  
**Result:** Failed at step 2; Finance approval step blocked  
**Classification:** Test issue; no application behavior finding

## Execution

The 35-account UI login setup and platform checks passed. Warehouse used its own session to create and submit a ₱30,000 inbound adjustment for RM-001. The pending status, approval threshold, unchanged stock, audit actor, and exact Finance notification audience all passed the step-1 assertions.

Finance used its own session, followed the notification in the Notifications page, opened the linked Stock Adjustments record, and saw the pending status with enabled Approve and Reject actions. Step 2 then failed while preparing the Warehouse observer: the test called `selectOption('')` on a searchable combobox whose rendered control is an input, so Playwright rejected the operation. The Finance approval and stock movement step was blocked by the process runner.

| Step | Outcome | Evidence |
|---|---|---|
| Warehouse creates and submits the high-value adjustment | Passed | UI-created record is pending; read-only invariants passed; no stock movement |
| Finance follows the approval notification | Failed during observer setup | Notification link and linked record were found through the UI; Approve and Reject were enabled; invalid native-select interaction stopped verification |
| Finance approves; Warehouse observes live stock transition | Blocked | Prior step failed; not attempted |

## Follow-up

The E2E interaction now opens the Status combobox, selects its visible `Status: All` option, and asserts the filter clears before checking the observer row. This preserves the real UI interaction and the no-refresh assertion. The next run must start from a fresh E2E baseline because this attempt created a pending transactional record through the UI.

No application code was changed. The failed attempt does not count as a completed business journey.
