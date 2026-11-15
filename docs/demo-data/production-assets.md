# Production asset master data

`production-assets` is a Phase 3 reference-data layer. It creates machine, mold, asset-register, mold-compatibility, and mold shot-maintenance schedule records. It intentionally does not create production runs, breakdown work orders, calendar PM work orders, depreciation postings, or stock transactions; those belong to later transactional layers.

## Volumes by scale

| Scale | Injection machines | Molds | Auxiliary asset-register entries |
|---|---:|---:|---:|
| `small` | 4 | 16 | 3 |
| `demo` | 30 | 60 | 12 |
| `full` | 36 | 96 | 12 |

Each injection machine receives one machine-category asset. Each mold receives one mold-category asset. Molds are distributed across the current 16-product synthetic catalog, with backup tooling on repeat production families. Tonnage compatibility is written through `MoldService::syncCompatibility`; every seeded mold must match at least one machine in the selected scale.

## Domain services and generated history

- `MachineService::create` creates injection presses with the current machine fields and running-hour register.
- `MoldService::create` creates tooling, its creation history row, and the app's shot-based preventive-maintenance schedule.
- `MoldService::syncCompatibility` stores mold-to-press compatibility.
- `AssetService::create` assigns asset numbers from the application's `asset` sequence.
- `AssetService::associate` links each machine or mold to its corresponding asset-register record.
- Every operation runs under the requested as-of clock. Mold history, schedule timestamps, asset timestamps, and machine running-hour update time use that clock; acquisition and commissioning dates are earlier dates relative to it.

The layer restores soft-deleted matching machines, molds, and assets using their service restore actions. On rerun, it reuses records by machine/mold code or a stable synthetic asset description. It compares compatibility IDs before syncing so an unchanged rerun does not touch each mold again.

## Synthetic estimates and operational status

Machine availability is set to the current schema's 16 hours/day default. Running-hour totals are synthetic opening estimates. Tonnage compatibility bands, cavity counts, cycle times, shot limits, useful lives, and salvage values are also illustrative; production and tooling engineering have not supplied a released asset/tooling register. Acquisition costs use a documented demo formula: presses use `PHP 3,500,000 + PHP 18,000 × tonnage`; mold values use `PHP 600,000 + PHP 150,000 × cavities` plus a size-class allowance; auxiliary values are explicit synthetic planning estimates in the seeder. These are not quotations, invoices, depreciation postings, or approved capital values.

The reference layer starts machines idle and molds available. Later process layers must create the valid work-order, breakdown, and PM transitions needed to demonstrate running, maintenance, breakdown, offline, in-use, and retired states. One synthetic mold, `M-WB-001`, opens at 85% of its shot-maintenance interval as a documented live-demo alert candidate.

The current maintenance domain's `MaintainableType` and work-order request accept only `machine` and `mold`. Auxiliary chiller, compressor, dryer, loader, granulator, temperature controller, robot, conveyor, crane, and forklift rows therefore appear in the asset register only; they cannot yet receive maintenance schedules or work orders through the app's domain flow. No polymorphic workaround or direct insert is used. This app coverage gap is recorded as F-015 in [FINDINGS.md](FINDINGS.md).

Machine and mold capital values are demo assumptions, not source-of-truth accounting data. This is recorded as F-016; replace them with owner-provided asset register values before presenting costs or depreciation as factual. Tool compatibility and process measurements are demo assumptions recorded as F-017; confirm them with tooling and production engineering before presenting throughput or mold capability as plant facts.
