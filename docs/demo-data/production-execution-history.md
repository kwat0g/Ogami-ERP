# Production execution history

The `production-execution-history` layer adds one traceable FC-001 stock run through the current planning, capacity, crew, work-order, attendance-attestation, start-up QC, warehouse handover, trial-output, and production-output paths. It depends on `production-history` and is ordered before maintenance history.

## Historical partial-run story

The layer plans a 100-piece synthetic stock run 35 business days before `--as-of`, confirms a capacity proposal as the PPC actor, assigns the active production recorder, and starts under the production manager's recorded crew-presence attestation. Warehouse confirms actual material handovers to the assigned recorder. It follows `WorkOrder::usesFinishFirstQc()` and does not change feature settings:

- In the configured finish-first workflow, Production records an 80-piece `produced_count` batch through `WorkOrderOutputService`; the application does not create in-process QC for that path.
- In the standard QC workflow, Production records the exact startup sample quantity, QC measures the spec rows and completes the passing result, QC settles the accepted sample, and Production records a 75-piece good batch. The startup sample plus output yield 80 accepted pieces.

Both paths leave the work order in progress with an unsettled 20-piece target balance. If finish-first is enabled while the active routing still requires in-process QC and routing execution is enabled, the application's own compatibility gate may reject the start; the layer does not override that gate.

The production output service creates its document batch, movement, quality hold, GL handoff and mold shot evidence. With the seeded chart-of-accounts configuration, the small console run generated balanced posted journals for the material issues and production receipt. The OEE service reports the output against the assigned machine and mold cycle time. No work-order status, output, inspection, stock movement, material handover, or capacity row is inserted directly by this layer.

## Live continuation

- Open Production → Work Orders and find the FC-001 work order parked in progress.
- Review its startup inspection, accepted trial batch, production output batch and finished-goods receipt.
- Continue reporting the remaining target through the assigned production crew after the demo operator confirms today's attendance.
- Open the OEE report for the historical run date and assigned machine to show output, cycle-time and quality inputs.

The scenario intentionally leaves its machine and mold owned by an in-progress order, as the application's execution model does. The production planner, maintenance-history layer and OEE checks must account for that occupied resource. It is one execution example, not the requested full 12–24 month production and OEE trend. Full outgoing inspection, order-linked production, all operation states, downtime Pareto, and broader status coverage remain later work.

## Verification

The focused integration test passes (1 test / 14 assertions), including same-layer replay fingerprinting. Pint and targeted PHPStan pass. A guarded `--scale=small --as-of=2026-11-16 --seed=20261116` console seed completed the 11 selected/dependency layers in about 1m30 at 76.5 MiB peak; the identical replay completed in about 11 seconds at 66.5 MiB. Read-only reconciliation found 0 stock-to-ledger mismatches across all items, 0 unbalanced journals, 0 negative stock levels, and confirmed all three material issues were handed over. The scenario follows the active finish-first QC feature setting, so it has no in-process inspection. Only the isolated `ogami_test` database was used.

All names, quantities and inspection observations are synthetic. Inspection measurement values use the nominal values in the synthetic reference specification, not an external customer drawing.
