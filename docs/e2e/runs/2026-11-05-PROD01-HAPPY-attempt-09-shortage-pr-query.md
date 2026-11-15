# PROD01-HAPPY — Attempt 09: read-only shortage query used the wrong work-order key

**Date:** 2026-11-05 UTC  
**Outcome:** Four steps passed; the PPC UI created a planned work order, then a read-only test query failed; 16 later steps blocked  
**Classification:** Test issue TST-PROD-007  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Fresh guarded E2E baseline, six real-UI role logins, headless Chromium with tablet/touch emulation, one worker. The journey itself completed without a host PHP-test interruption.

## Steps and actors

| Step | Actor | Result |
|---|---|---|
| Assign Production shift coverage to the E2E operator | HR Officer | Passed through Attendance & DTR → Shifts → Bulk assign |
| Post baseline RM-001 stock | Warehouse Staff | Passed through Stock Adjustments; status, actor, movement, balance and audit assertions passed |
| Post baseline RM-010 stock | Warehouse Staff | Passed through Stock Adjustments; exact movement and balance assertions passed |
| Post baseline PKG-001 stock | Warehouse Staff | Passed through Stock Adjustments; exact movement and balance assertions passed |
| Create the WB-001 stock work order | PPC Head | UI creation and planned-state assertions passed; the step failed in a subsequent read-only fixture query |
| Production Manager confirmation through final QC and ledger reconciliation | Production Manager, Warehouse Staff, Production Employee, QC Inspector | Blocked by step 5; 16 ordered dependent steps did not run |

The four complete steps created the shift assignment, three opening-stock adjustments, and the production work order through the owning roles' UIs. No process record was created or advanced through API or database mutation.

## Failure and read-only diagnosis

PPC navigated through Production → Production Work Orders, selected Produce for stock, and created `WO-202611-0001` for WB-001. The UI action succeeded and the in-journey read-only checks confirmed status `planned`, stock purpose, PPC creator, no machine or mold, and the expected material-plan line count. The next read-only assertion queried `purchase_requests.work_order_id`, which is not a column, so PostgreSQL stopped that assertion and the process runner marked the step failed.

The migration `api/database/migrations/2026_10_09_220000_add_stock_production_and_order_allocations.php` and `PurchaseRequest` model define the actual relationship as `purchase_requests.stock_production_work_order_id`. A separate diagnostic read-only query after the run confirmed the created work order remained `planned`, had three material lines and zero shortage purchase requests. This diagnostic is retained as evidence but does not convert the Playwright step to passed: its configured invariant did not execute successfully, and the remaining hand-offs were blocked.

The query has been corrected to filter by `request.stock_production_work_order_id`; the exact expected shortage count remains zero. No application behavior or business assertion changed. A new fresh-baseline run is required to verify the correction.

## Artifacts

- [Sanitized Playwright trace, journey context and failure screenshots](assets/prod01-happy-attempt-09/)
- [Step error context](assets/prod01-happy-attempt-09/error-context.md)
- [Journey context: 4 passed, 1 failed, 16 blocked](assets/prod01-happy-attempt-09/journey-context.json)
- E2E trace sanitizer reported zero credential-like values remaining after redaction.

No product defect is inferred from this run. No application code changed.
