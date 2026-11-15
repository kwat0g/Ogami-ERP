# INV-01: Warehouse/location scope boundary — blocked

**Result:** Playwright auth setup passed for `warehouse_staff`; the business test was **skipped by `test.fixme`** with GAP-PERM-01 before UI action. Run time was 6.8 seconds, with 0 unexpected and 0 flaky tests. This is a blocked journey, not a pass.

## Evidence from code and E2E reference data

- `api/database/seeders/WarehouseSeeder.php` creates a single Main Warehouse (`MW`), five zones, and 72 locations.
- `api/database/seeders/E2EAccountSeeder.php` creates three `warehouse_staff` actors, all assigned department `WH`; it defines no warehouse/location assignment.
- A read-only query against the dedicated E2E database observed **1 warehouse, 72 locations, 3 warehouse staff, and 1 distinct Warehouse department**.
- `api/app/Modules/Inventory/Requests/StoreStockAdjustmentRequest.php` authorizes creation by the role permission `inventory.adjust` and validates only that the location exists.
- `api/app/Modules/Inventory/Controllers/StockAdjustmentController.php` lists adjustments without an actor/location scope filter. `TransferOrderController` validates existing source/destination locations, while `TransferOrderService` checks creator/executor separation; neither has a warehouse assignment relation to enforce.
- `StockCountController` validates count scope and that a zone belongs to its warehouse, but code/seeder discovery found no user-to-warehouse assignment against which to enforce an actor boundary.

The current source supports role-level inventory permissions. It does not define whether every Warehouse role is intentionally allowed across all locations or whether access should be restricted by warehouse/department. That business rule must be confirmed before this variant can assert an expected denial. No product security finding is asserted until that rule is known.

Playwright JSON results and last-run status are archived under [attempt assets](assets/inv01-scope-blocked-2026-11-04/). The test performed no UI action and created no transactional record.

## Review question

Should `warehouse_staff` permissions be organization-wide across all warehouses/locations, or should users be assigned to a warehouse, zone, or location? The catalog default is role-wide access because that is the only behavior represented by current source and reference data. If scoped access is required, implement it only through the approved fix loop, then test both UI visibility and backend denial with distinct assigned actors.
