# Finished goods orderable stock

## Why this layer exists

Inventory on-hand is not automatically available for a customer order. The current app's supply service requires a finished-good inventory mapping and an active product inspection specification, plus a completed stock-purpose work order, a good output batch, a passing outgoing inspection against the current spec revision, an inventory receipt with resolved GL handoff, and free physical stock after reservations, holds, expiry, and safety stock.

The earlier partial production scenario correctly remained in progress and therefore could not supply a portal order. This layer adds sellable production batches without changing that live-start scenario.

## What the layer does

`FinishedGoodsOrderableStockLayer` is registered after `production-execution-history` in `api/config/demo.php`. For every active finished-good item that already has positive stock it:

1. Confirms the active CRM product mapping and current inspection-spec revision are ready for stock production.
2. Creates an idempotent stock-purpose work order through `StockProductionPlanningService`.
3. Uses the capacity planner, production crew assignment, work-order lifecycle, warehouse handover, and routing-operation services.
4. Records output through `WorkOrderOutputService` using the app's configured finish-first `produced_count` payload, then completes production through `WorkOrderService`.
5. Lets the app's outgoing-QC handoff create the inspection, records full-batch confirmed-check coverage through `WorkOrderFinalInspectionService`, and records independent QC review.
6. Verifies the posted receipt/GL handoff and available quantity through `SalesOrderSupplyService`.
7. Verifies the actual catalog payload through `CustomerPortalService::catalog()` for all ten requested customer portals, including positive availability and a usable price for every product already authorized in that customer's catalog.

The layer does not edit stock balances, create outgoing inspections directly, or widen customer/product access. Existing customer-specific catalogs are retained. Across the ten catalogs, all 16 finished goods are authorized for at least one customer.

## Seed quantities and idempotency

The target is 80 pieces at `small`, 250 at `demo`, and 500 at `full`, plus the item's configured safety stock. A work order is capped at 5,000 pieces by the app's stock-production service. The deterministic request key is derived from the product code and as-of date; the output uses its own stable idempotency key. Replaying the same layer resumes or verifies the existing work order rather than adding another batch.

## Live defense database result — 2026-11-15

The focused run targeted PostgreSQL `ogami` from an isolated CLI container with `APP_ENV=demo`, `DEMO_SEEDING_ENABLED=true`, `DEMO_SEEDING_ALLOW_NON_DEMO_DATABASE=true`, and exact database/host allowlists for `ogami` and `db`. Mail, broadcast, queue, and cache drivers were suppressed by the demo side-effect scope. PHP's memory limit was 768 MiB. No reset, migration, settings seeder, or full PHP suite was run.

The run reported 16 finished-good items with positive stock, 16 active product mappings, 16 products with positive QC-released supply, and 16 products available with valid prices across the authorized customer portals. Portal catalog counts were:

| Customer account | Catalog products | With stock and price |
| --- | ---: | ---: |
| Mitsuba Philippines Corp. Plant 3 | 12 | 12 |
| Mitsuba Philippines Corp. Plant 2 | 12 | 12 |
| Mitsuba Philippines Corp. Plant 1 | 12 | 12 |
| Hiblow Philippines Inc. | 8 | 8 |
| Sanac Philippines Inc. | 8 | 8 |
| Yazaki EDS Manufacturing, Inc. | 8 | 8 |
| Yazaki-Torres Manufacturing Inc. | 12 | 12 |
| YGP PTE. LTD. Yazaki Global Purchasing | 16 | 16 |
| OGAMI CORPORATION JAPAN | 16 | 16 |
| Honda Philippines Inc. | 16 | 16 |

The production API image did not include Faker, which `DemoSeedContext` requires before a layer starts. The first isolated invocation stopped before writes. The successful invocation mounted the existing development vendor directory read-only; this environment limitation is tracked in [FINDINGS.md](FINDINGS.md#f-050-production-api-image-omits-faker-required-by-the-guarded-demo-cli).

## Scope and remaining process coverage

This makes every finished good in its authorized customer catalog selectable with positive available stock and a valid quote. It does not pre-submit a customer order. The user can place the order from a portal account and continue the Sales approval workflow live. Deliveries, proof of delivery, invoice, collection, return, and credit-note history remain outside this stock layer and are tracked separately under F-045.

The implementation was checked with PHP syntax validation and `git diff --check`. No PHP tests or browser walkthrough were run for this focused repair.
