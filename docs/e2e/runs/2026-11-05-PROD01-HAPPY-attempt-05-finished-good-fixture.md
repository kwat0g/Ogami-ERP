# PROD01-HAPPY — Attempt 05: E2E baseline omitted the finished-good item identity

**Date:** 2026-11-05 UTC  
**Outcome:** Test-harness failure in the read-only baseline assertion, before the first process step  
**Classification:** Test issue TST-PROD-005  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Fresh guarded E2E baseline after a 300-second host PHP-test quiet window; headless Chromium with tablet/touch emulation; one worker; no PHP test process detected during the run.

## Result

The guarded baseline and browser setup completed, and the journey started. Its read-only WB-001 fixture query found the active BOM and component rows, but returned `finished_item_id = null`; the exact precondition failed at `expect(fixture.finished_item_id).toBeTruthy()`.

The failure occurred before any process UI action. No stock balance, work order, inspection, production output, or other transactional record was created by the journey.

## Cause and correction

`ProductSeeder` and `BomSeeder` create the CRM product and its active BOM. `InventoryItemSeeder` creates raw-material, packaging, and spare-part identities, while the existing idempotent `ManufacturedProductInventorySeeder` creates the matching finished-good inventory identities for active BOM-backed products. The guarded `E2EAccountSeeder` called `E2EReferenceDataSeeder`, but that E2E-only seeder did not invoke the manufactured-product identity seeder.

`E2EReferenceDataSeeder` now invokes `ManufacturedProductInventorySeeder` after its explicit E2E-environment guard. This supplies reference/master item identities only. It creates no stock balance or process transaction; each journey still creates stock through the Warehouse UI. A fresh-baseline run is required to verify the correction.

## Evidence

- [Playwright error context](assets/prod01-happy-attempt-05/error-context.md)
- [Failure screenshot](assets/prod01-happy-attempt-05/test-failed-1.png)
- [Sanitized trace](assets/prod01-happy-attempt-05/trace.zip)
- Baseline call: `api/database/seeders/E2EAccountSeeder.php`
- E2E-only reference seeder: `api/database/seeders/E2EReferenceDataSeeder.php`
- Existing identity seeder: `api/database/seeders/ManufacturedProductInventorySeeder.php`
- Journey precondition: `spa/e2e-process/production-stock.floor.spec.ts`
