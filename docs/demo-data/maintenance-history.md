# Maintenance history layer

`maintenance-history` adds a small service-backed set of completed and live maintenance work. It depends on maintenance master data, opening spare-part stock, production planning, and workforce identities. All work orders, logs, breakdown records, part requests, stock movements, handovers, audit rows, and document numbers are produced by the application's current services and event path.

## Seeded scenarios

The layer creates or reuses five work orders: one in each current `MaintenanceWorkOrderStatus` (`open`, `assigned`, `in_progress`, `completed`, and `cancelled`). It selects actual due calendar PM schedules and service-eligible assets; it does not insert work-order or downtime rows directly.

| Scenario | State | Story and continuation |
|---|---|---|
| Calendar PM | Open | A calendar schedule that is due on the prior business day becomes an open preventive work order. The maintenance supervisor can open the work queue and assign it. |
| Mold inspection | Assigned | A scheduled cooling-circuit inspection is assigned to Maintenance Tech 1 through the supervisor assignment flow. The order uses a separate mold from the cancelled example. |
| Historical breakdown repair | Completed | A machine breakdown is raised through `MachineService` and the durable status-event path. The assigned technician starts work, records findings, requests a heater band, and completes the warehouse handover. After a passing functional check and parts reconciliation, the current configured close-out path completes the order. |
| Withdrawn tooling inspection | Cancelled | A preventive mold order is assigned to Maintenance Tech 1. After that employee's actual configured shift ends, the supervisor inspects and reconciles parts through `MaintenanceWorkOrderService`, then cancels with a reason. No parts are issued. |
| Live breakdown | In progress | A separate machine breakdown is raised through `MachineService`; its event listener creates downtime and a corrective order. Maintenance Tech 2 is assigned, starts the repair, records an initial finding, and requests a filter that remains awaiting warehouse confirmation. The machine is held in maintenance and its downtime remains open for continuation during the demo. |

At the explicit smoke-test anchor `--as-of=2026-11-16`, the completed repair is dated 42 business days earlier. Cancellation and assignment events, the due PM, and the live breakdown are dated relative to the prior business days. Production capacity reservations and configured holidays are respected when selecting a live breakdown target. The default command anchor remains the run date in Asia/Manila; the smoke-test date is not a defense-date commitment.

### Live continuation

- **Maintenance Tech 2:** open Maintenance → My Work Orders → the in-progress breakdown. Review the logged finding and requested filter. Continue the work log after Warehouse confirms the request, reconcile the handed-over part, record a passing result, then complete or submit for verification if the current closed-loop setting requires it.
- **Warehouse Staff:** open the Maintenance part-request / material-handover queue for the requested filter. Confirm the one-unit handover at location `D-01`; the service records the stock movement, usage link, and handover.
- **Maintenance Supervisor:** open the maintenance queue for the due calendar PM and the assigned mold order. Review the completed breakdown's actor-attributed history and the cancelled order's inspection evidence.
- **Production:** the live breakdown machine has no pending or confirmed capacity reservation that would be interrupted by its downtime. The existing production plan remains available on its own selected equipment.

Use the real role account listed by `demo:credentials`; this module note does not define passwords. Current open maintenance records keep service-generated notices. Completed and cancelled historical actions run inside notification suppression, so they do not leave stale inbox notices.

## Domain paths and integrity

- `MachineService::transitionStatus()` emits the application's durable machine-status event. The scoped synchronous queue runs the normal breakdown listener, which creates the downtime row and corrective MWO through the maintenance service.
- `MaintenanceWorkOrderService` handles assignment, start, work logs, reconciliation, completion, and cancellation. The cancellation scenario follows the supervisor's inspection rule and only acts after the assigned technician's configured shift has ended.
- `MaintenancePartRequestService::request()` and `confirm()` invoke the current spare-part usage, `StockMovementService`, and material-handover paths. The layer uses an existing active spare item with at least one usable unit at `D-01`.
- `DemoSeedContext::at()` controls historical creation, assignment, work, verification/completion, and audit timestamps in Asia/Manila and restores the prior clock in `finally`.
- Stable descriptions and fixed request operation keys let a full `demo:seed --only=maintenance-history` dependency replay reuse the same records. The live breakdown selector avoids production capacity reservations. Existing compatible BOM routes remain valid when their machine is temporarily unavailable for maintenance (F-035; separate draft PR #542).

## Verification and limits

The focused integration test passes (1 test / 28 assertions, 2:05, 103 MiB) against `ogami_test`. It verifies all five enum states, a due open schedule, current pending part request, completed work log and downtime, stock movement and handover links, timestamp bounds, no production-capacity conflict for the live breakdown, and a full dependency replay fingerprint.

A guarded `--scale=small --seed=20261116 --as-of=2026-11-16` console run selected 12 layers including dependencies and completed in about 100 seconds at 74.5 MiB peak. The same command replay completed in about 11 seconds at 66.5 MiB peak. Both runs targeted only `ogami_test`; the documented `ShiftSeeder` prerequisite was invoked because reset currently skips operational reference seeders (F-023). The actual demo database, snapshot, and production environment were not targeted.

Read-only reconciliation found five marked/due orders with one per status, two linked breakdown downtime rows (one closed at 50 minutes and one still open), two part requests (`handed_over` and `requested`), one issued unit with ₱850.00 synthetic cost, one linked material handover, and 18 MWO audit rows. The one-unit issue is reflected in stock levels: all 52 stock item/location balances reconcile to the movement ledger, with zero mismatches and zero negative levels. The current breakdown machine has no conflicting capacity reservation. The SHA-256 fingerprint across orders, logs, downtime, requests, usage, movements, and handovers remained `69bd76505be5541598a57b55dd62e6d63aaa20daf6cb53f61a69514b3761202c` after replay.

The scenario count is intentionally small at every scale. It does not create a full 12–24 month maintenance/downtime history, completed calendar-PM history, a calibration event workflow, shot-threshold work order, maintenance budget or asset-cost history, or dashboard-specific analytics snapshots. Spare-part prices and work notes are synthetic examples. Maintenance dashboards beyond data returned by the current orders, schedule, and downtime queries still need page-level verification in the overall Phase 6 pass.
