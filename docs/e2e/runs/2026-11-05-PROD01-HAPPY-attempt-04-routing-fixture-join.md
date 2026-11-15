# PROD01-HAPPY — Attempt 04: baseline query used the wrong routing foreign key

**Date:** 2026-11-05 UTC  
**Outcome:** Test-harness failure in read-only baseline query, before the first process step  
**Classification:** Test issue TST-PROD-003  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Fresh guarded E2E baseline; headless Chromium with tablet/touch emulation; one worker; no PHP test process detected during the 300-second quiet window.

## Result

Chromium launched, and the read-only employee profile lookup passed. The next read-only fixture query failed while finding the WB-001 setup time:

```text
ERROR: column operation.product_routing_id does not exist
```

The `routing_operations` table stores its product-routing foreign key as `routing_id`. The failure occurred before the first process UI action. No business record or process side effect was created, and this run did not exercise a production transition.

## Correction

The fixture query now joins `routing_operations.routing_id` to `product_routings.id`, matching `api/database/migrations/0256_create_routing_operations_table.php` and the `RoutingOperation` model. No application code changed. The next attempt will use another fresh baseline and retain this failure in the run history.

## Evidence

- [Playwright error context](assets/prod01-happy-attempt-04/error-context.md)
- [Failure screenshot](assets/prod01-happy-attempt-04/test-failed-1.png)
- [Sanitized trace](assets/prod01-happy-attempt-04/trace.zip)
- Schema/model: `api/database/migrations/0256_create_routing_operations_table.php`, `api/app/Modules/Production/Models/RoutingOperation.php`
- Test location: `spa/e2e-process/production-stock.floor.spec.ts`
