# Maintenance and calibration master data

The `maintenance-master-data` layer covers preventive maintenance schedules and the Quality calibration register. It depends on `production-assets` and `workforce-identities`, so every plan points to a current machine or mold and audit actions have the seeded maintenance supervisor or QC inspector as the actor.

## Scale and records

| Scale | Machines / molds from the asset layer | Maintenance schedules | Calibration records |
|---|---:|---:|---:|
| small | 4 / 16 | 40 | 6 |
| demo | 30 / 60 | 180 | 12 |
| full | 36 / 96 | 264 | 18 |

Every machine receives a 30-day calendar inspection and a 500-running-hour screw/barrel inspection. Every mold receives a 90-day cleaning/cooling-channel inspection and a shot-count service using that mold's current `maintenance_frequency_shots` (falling back to `max_shots_before_maintenance`). The mold's own `MoldService` creates the initial shot schedule; this layer finds that exact auto-generated schedule and updates it with `MaintenanceScheduleService`, rather than creating a second shot plan. Calendar plans include overdue, due-soon, and future next-due dates relative to `--as-of`. Machine-hour and mold-shot plans have no calendar due date by application design; runtime or shot progress drives them.

Maintenance days, 500-hour intervals, and measuring-equipment frequencies are synthetic demo planning values, not released Ogami PM standards. Shot intervals follow the current mold master values, which are also synthetic estimates from `production-assets`. Auxiliary assets remain ineligible for maintenance schedules because current application target types are limited to machines and molds; see [F-015](FINDINGS.md#discovery-findings).

## Calibration register

The instrument catalog uses stable `CAL-*` demo codes and covers torque, dimensional, pressure, weight, temperature, flow, and force measurement equipment. Records are created or updated through `CalibrationService`, with the QC Inspector actor in the audit trail. Scale profiles retain the first 6, 12, or 18 definitions. The catalog includes active, due, overdue, and retired examples at every scale. Last and next dates are generated relative to `--as-of`; due status is derived by the application from `quality.calibration.due_window_days`, not hard-coded in the database. Retired instruments retain an expired historical schedule to show that their status remains retired.

Calibration frequencies (180, 365, or 730 days), equipment names, and locations are synthetic examples; the app has no released instrument register or frequency catalog. `calibration_records` has no foreign key to the asset register, so these measuring instruments are represented only in the calibration module. No calibration work history or certificate attachments are created by this layer.

## Verification

`MaintenanceMasterDataLayerTest` checks the scale-small record counts and interval mix, due-date spread, all four calibration statuses, as-of timestamps, actor-attributed audit rows, and an identical second run. The focused layer test passed (1 test / 18 assertions); related maintenance schedule and calibration service tests passed (29 tests / 151 assertions). A guarded small console smoke and same-options rerun passed on the exact allowlisted `ogami_test` database. The production-assets and workforce dependencies plus this layer completed at 62.5 MiB peak process memory; the new layer itself took 4.15 seconds on its first smoke run. Demo/full scale console writes remain unverified. The layer does not create work orders, breakdowns, spare parts, calibration events, or transaction history; those belong to later Phase 4 layers.
