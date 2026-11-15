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
- Session created at 09:15, frozen at 09:30, and line counted at 10:00 Asia/Manila on `--as-of`, or on the prior business day when as-of falls on a weekend or holiday

The active count tolerance is read from the application's `inventory.stock_count.variance_tolerance_pct` setting. Seeding stops if the 10% variance no longer requires a review or if the current UI action calculator no longer offers approval to the reviewer. No approval limit is changed. The separate high-value stock-adjustment threshold remains at the app's configured value; its default is disabled, and the Phase 1 approval-limit question remains open.

## Live walkthrough

Log in as Warehouse Staff, open Inventory → Stock Counts, and open `DEMO-CYCLE-COUNT-LIVE:ZONE-A`. Point out that RM-002's counted quantity is below system quantity and that the line awaits independent verification while the rest of the zone count remains open. Log in as the System Administrator and approve the RM-002 variance. Return as Warehouse Staff to record the remaining physical counts. A different user can complete the session only after every line is counted or verified; the scenario intentionally stays open so the presenter can choose whether to show approval alone or continue the count workflow.

The service path supports live continuation without a direct status edit. Approval is performed through `StockCountService::approveVariance()` in the UI. Completing a count will create the inventory adjustment movement through the service's reconciliation path. Completing the session is optional for this walkthrough because the remaining lines intentionally need physical counts.

## Replay and verification

The layer uses a stable title marker. A replay validates the session status, zone, creator, timestamps, RM-002 count quantity and variance, remaining pending lines, audit event, and available independent-approval action. It fails if the scenario has been continued or otherwise edited, preserving the replayed dataset instead of rewinding live work.

The feature test also verifies that rerunning the layer adds no session, count lines, audit rows, movements, or stock-level changes. Global demo verification and browser click-path verification remain pending until the integrated dataset is assembled.

## Verification result

`InventoryCountLiveScenarioLayerTest` passes (1 test / 15 assertions, 1:30.927, 101 MiB). Pint passes on the changed PHP files, and targeted PHPStan reports no errors. A guarded console seed after rebuilding the exact `ogami_test` target completed its nine selected/dependency layers in 53.0 seconds at 70.5 MiB peak; the identical replay completed in 6.6 seconds at 64.5 MiB peak. The first console attempt stopped at workforce setup because `DEMO_ACCOUNT_PASSWORD` was missing; the retry supplied a demo-only password. The focused test refreshed the test schema, so the system and operational reference seeders were restored before another guarded seed; all nine layers then completed in 30.9 seconds at 70.5 MiB peak, and the final replay completed in 6.8 seconds at 64.5 MiB peak. The final database contains one `in_progress` session (`SC-202611-0001`), 20 count lines, one counted RM-002 variance (900.000 system, 810.000 counted, −90.000 / 10.00%), 19 pending lines, and no count adjustment movement. These smoke runs used only the allowlisted `ogami_test`, not the demo database, and did not run alongside the full PHP suite. Global `demo:verify` and browser click-path verification remain pending.
