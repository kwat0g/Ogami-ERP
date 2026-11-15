# Inventory cycle-count live scenario

Implementation is on branch `feat/demo-inventory-count-scenario`, stacked on the O2C history branch.

## Seeded starting state

The `inventory-count-live-scenario` layer depends on `opening-stock-balances` and `workforce-identities`. It uses the real `StockCountService` transitions to create a Zone A count session, start the session, and record a physical count for the seeded raw material `RM-002`.

- Session title: `DEMO-CYCLE-COUNT-LIVE:ZONE-A`
- Session status: `in_progress`
- RM-002 line: `counted`, with a 10% shortage against the captured system quantity
- Other lines with stock in the zone: remain `pending`
- Count actor: `warehouse.staff@accounts.ogami-demo.test`
- Independent reviewer: `system.admin@accounts.ogami-demo.test`
- Finalizer after all lines are counted: `warehouse.supervisor@accounts.ogami-demo.test`
- Session created at 09:15, frozen at 09:30, and line counted at 10:00 Asia/Manila on `--as-of`, or on the prior business day when as-of falls on a weekend or holiday

The active count tolerance is read from the application's `inventory.stock_count.variance_tolerance_pct` setting. Seeding stops if the 10% variance no longer requires a review or if the current UI action calculator no longer offers approval to the reviewer. No approval limit is changed. The separate high-value stock-adjustment threshold remains at the app's configured value; its default is disabled, and the Phase 1 approval-limit question remains open.

## Live walkthrough

Log in as Warehouse Staff, open Inventory → Warehouse Map at `/inventory/warehouse-map`, switch to the **Stock Count** view, and open `DEMO-CYCLE-COUNT-LIVE:ZONE-A`. Point out that RM-002's counted quantity is below system quantity and that the line awaits independent verification while the rest of the zone count remains open. Log in as the System Administrator and approve the RM-002 variance. Return as Warehouse Staff to record the remaining physical counts. Then log in as the Warehouse Supervisor and complete the session. The app requires a third user who neither created, counted nor approved a line; the seeded supervisor account is that finalizer.

The service path supports live continuation without a direct status edit. Approval is performed through `StockCountService::approveVariance()` in the UI. Completing a count creates the inventory adjustment movement through the service's reconciliation path. The feature test proves the approval, remaining-count, and finalization flow using the three separate seeded actors.

## Replay and verification

The layer uses a stable title marker. A replay validates the session status, zone, creator, timestamps, RM-002 count quantity and variance, remaining pending lines, audit event, and available independent-approval action. It fails if the scenario has been continued or otherwise edited, preserving the replayed dataset instead of rewinding live work.

The focused tests verify idempotent replay and continue the workflow through the real service path: the System Administrator approves RM-002, Warehouse Staff counts the remaining lines, and the distinct Warehouse Supervisor completes the session, posts the 90-unit `adjustment_out`, and leaves the bin at 810 units.

## Verification result

The focused tests pass (5 tests / 303 assertions): the inventory test covers the parked state, replay, independent approval, remaining counts, finalization and stock reconciliation; workforce tests cover safe identities and the revised 21 / 201 / 241 employee roster. Pint passes on all changed PHP files and targeted PHPStan reports no errors. The final guarded console smoke used only the exact allowlisted `ogami_test`: it rebuilt system references, ran all nine selected/dependency layers in about 83 seconds at 72.5 MiB peak, and parked one `in_progress` count with one RM-002 10% short count and 19 pending lines. The as-of date was 2026-11-15 (Sunday), so the count is dated Friday 2026-11-13. No demo database, full PHP suite, browser click-path, or global `demo:verify` was run.
