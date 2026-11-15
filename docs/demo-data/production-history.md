# Production history layer

The `production-history` layer stages a small set of current production work through the current stock-planning, capacity-planning, assignment, confirmation, and cancellation services. Its dependencies are `opening-stock-balances`, `quality-inspection-specs`, and `workforce-identities`.

| Scenario | Seeded record | State at the as-of date | Demonstration value |
|---|---|---|---|
| Live planning | 180-piece FC-001 stock work order | Planned; capacity proposal pending | PPC reviews a proposed machine/mold slot and confirms the assigned crew later |
| Live confirmed run | 240-piece FC-001 stock work order | Confirmed; machine, mold, material reservations, and one-person crew assigned | Production can review execution readiness and continue into Start when the approved shift window is active |
| Historical withdrawal | 120-piece FC-001 stock work order | Cancelled before production start | Shows cancellation audit/history without issuing materials or creating output |

Request UUIDs are fixed per scenario, so stock planning returns the same work order on replay. The capacity planner's active reservation is reused on rerun. Work orders carry BOM-derived material requirements; stock shortages follow the application's stock-production planning path, which can create the associated draft purchase request. The confirmed scenario reserves material through the work-order lifecycle service. The layer does not insert work orders, schedules, crews, or reservations directly.

All service actions use `DemoSeedContext::at()` for timestamps. Historical stock-plan creation and its notices are suppressed; the live crew assignment is allowed to create its normal in-app crew notification. No mail is enabled by this layer.

## Current workflow limits

This increment does not stage production output, inspection results, routing-operation completion, machine downtime, or OEE history. Work-order `in_progress`, `paused`, `completed`, and `closed` states and operation execution states remain unseeded until the production execution history increment can satisfy material, schedule, crew-presence, in-process QC, and warehouse handover gates through current services. The records in this layer provide planned, confirmed, and cancelled work orders plus pending and confirmed capacity reservations.

The 180/240/120 piece targets are synthetic demonstration quantities, not customer forecasts. The layer uses FC-001 because the current BOM, routing, mold, machine compatibility, finished-good item, and inspection specification are all present in the approved reference layers.
