# Demo production execution history

## Goal

Add a deterministic, service-backed production execution scenario that creates traceable output, follows the application's configured quality workflow, creates an inventory receipt, and provides non-empty OEE inputs while leaving the work order parked in a realistic in-progress state.

## Steps

- [x] Add a focused demo-layer integration test for execution state, output/inspection/receipt links, OEE data, time bounds, and replay stability.
- [x] Run the new test and capture its expected pre-implementation failure.
- [x] Implement a new production execution history layer using stock planning, capacity, work-order, QC, handover, and output services.
- [x] Register the layer after production planning and before maintenance history; document dependencies and scenario.
- [x] Run focused tests, formatter, static analysis, guarded small seed and replay on `ogami_test` only.
- [x] Reconcile output totals, stock ledger, inspection result and OEE; update findings/progress and open a stacked draft PR.

## Verification notes

- The focused feature test passes (1 test / 14 assertions). Pint and targeted PHPStan pass. The guarded small seed completed all 11 layers in about 1m30 at 76.5 MiB peak; same-options replay completed in about 11 seconds at 66.5 MiB peak.
- On the console's finish-first quality configuration, the work order is in progress at 80 / 100 accepted pieces, the receipt movement and four work-order movements have generated posted journals, and no in-process inspection is created. OEE reports 80 good / 0 rejected pieces at the configured 39-second ideal cycle. All stock balances reconcile to movement ledgers (0 mismatches), all journals balance (0 mismatches), all issued materials have confirmed handovers, and there are no negative stock levels.
- Console verification used only the exact `ogami_test` database. A fresh migration supplied the system baseline; the existing `ShiftSeeder` was run because operational reference rows are opt-in. No application transaction rows were inserted directly.

## Boundaries

- Do not change application service logic to ease seeding.
- Do not finish the work order in this increment; its partial run is a live continuation point.
- Do not write the demo database, run the full PHP suite, or run tests concurrently.
