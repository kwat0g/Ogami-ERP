# Demo seeder architecture and command reference

Status: Phase 3 is in progress. The approved phase plan is in [01-data-plan.md](01-data-plan.md). `organization` seeds HR department and position references; `inventory-catalog` seeds UOM, item-category, warehouse, location, and raw-material masters; `finished-product-catalog` creates CRM products with matching finished-good inventory identities; `business-partners` creates fictional customer/vendor masters with customer product access and warranty options; `production-assets` seeds injection machines, molds, compatibility, machine/mold assets, and auxiliary asset-register entries; and `bom-routing-catalog` publishes costed BOM and routing masters for each finished product. The code-level `demo.reset_ready` gate remains false, so `demo:reset` stays disabled until the complete dataset is built and verified.

## Layer model

Demo data is assembled in dependency-ordered layers registered in `config/demo.php` under `layers`. Each layer implements `DemoSeedLayer`; the `DemoSeeder` base class is available for Laravel-style seeder classes. A layer declares a stable key, the module names it covers, dependencies, and a `run(DemoSeedContext $context)` method.

`DemoSeedLayerRegistry` validates configured layers and dependency keys, rejects duplicates and cycles, and topologically orders the selected layers. `--only=<layer-or-module>` selects matching layers and includes their dependencies. The registered layers currently cover `organization`, `inventory-catalog`, `finished-product-catalog`, `production-assets`, `bom-routing-catalog`, and `business-partners`. The finished-product layer depends on inventory UOMs and categories; production assets depend on both organization departments and finished products; BOM/routing masters depend on production assets (which transitively supplies inventory items, products, and departments); and business partners depend on finished products for current customer product access and warranty references.

`DemoSeedRunner` checks the production guard itself, resolves the selected layers, disables the active connection's query log, and executes each layer with a stable seed derived from the global seed and layer key. It reports layer start/finish events with elapsed time and peak PHP memory, collects cycles between layers, and wraps failures with the layer key. `DemoRecordRegistry` stores lightweight model-class/primary-key references between layers rather than retaining complete model collections.

`DemoSeedContext` contains the parsed as-of date, scale, seed, filter and no-history option; a fixed-seed `en_PH` Faker generator; the shared record registry; and helpers for acting as a user, controlling mutable and immutable Carbon clocks, and faking Laravel's notification facade around historical actions. Each helper restores prior state in `finally`. Some application services, including the pricing review flow, persist notification rows directly through `NotificationService`; the facade fake does not suppress those rows. Current BOM cost/price reviews are legitimate open work, but historical transaction layers must not assume this helper suppresses every notification path. This limitation is tracked as F-019. Live scenario layers use normal notification delivery for currently open work. Layers must use the clock helper around historical domain actions; the runner does not move application time globally because overdue and dashboard behavior follows runtime time.

`DemoSideEffectScope` temporarily switches every configured mailer transport to Laravel's in-memory array transport, every broadcast connection to the local log driver, every queue connection to synchronous execution, and every cache store to process-local array storage. It clears cached manager/connection instances before and after the scope and restores every prior config value in `finally`. Database-backed in-app notifications are controlled separately through the context helper. Real integrations must not be called from a demo layer.

## Write guard

All demo commands and legacy demo-seeder entry points call the shared `DemoSeedingGuard`. It refuses production and any environment other than `local`, `testing`, or `demo`; requires `DEMO_SEEDING_ENABLED=true`; requires an exact configured DB-name allowlist entry; permits `ogami_demo*` and `ogami_test*` in local mode, `ogami_demo*` in demo mode, and `ogami_test*` in testing; and checks the configured database host against the local-host allowlist. Production remains forbidden even if all flags match.

Set the variables only in an isolated demo environment. `.env.example` keeps the enable flag off and the allowlist empty:

```dotenv
DEMO_SEEDING_ENABLED=true
DEMO_SEEDING_DATABASE_ALLOWLIST=ogami_demo_thesis
DEMO_SEEDING_ALLOWED_HOSTS=localhost,127.0.0.1,::1,db
DEMO_ACCOUNT_PASSWORD=
DEMO_SNAPSHOT_DIRECTORY=
DEMO_PG_DUMP_BINARY=pg_dump
DEMO_PG_RESTORE_BINARY=pg_restore
```

Do not put production database names in the allowlist. The test bootstrap enables the guard only for its normalized `ogami_test*` database so tests can exercise guarded demo seeder code without widening the application database rule.

Browser fixtures that still depend on the legacy `DemoAccountSeeder` use `api/tests/Browser/demo_seed_helpers.php`. The helper requires a disposable `ogami_test_*` database, explicitly allowlists that exact database for the process, and runs the seeder inside the side-effect scope. Migrating E2E baselines to the shared `--scale=small` layers remains open until demo-account, transaction, and scenario layers are complete.

## Commands

| Command | Current behavior |
|---|---|
| `php artisan demo:seed [--as-of=YYYY-MM-DD] [--scale=small\|demo\|full] [--seed=N] [--only=layer-or-module] [--no-history]` | Parses options, checks the guard and layer plan, then runs registered layers. Current selections include `organization`, `inventory-catalog`, `finished-product-catalog`, `production-assets`, `bom-routing-catalog`, and `business-partners`; the later layers include their required dependencies. No opening balances or production history are created yet. |
| `php artisan demo:reset [--as-of=...] [--scale=...] [--seed=...] [--no-history]` | Checks the guard, the code-level readiness gate, and layer plan before `migrate:fresh`. The gate must stay false during staged implementation and may be enabled only after every required layer exists and `demo:verify` passes. |
| `php artisan demo:snapshot [--file=name.dump]` | Guarded PostgreSQL custom-format `pg_dump`; writes a SHA-256 manifest alongside the dump. Password is passed through the child process environment, not command arguments or manifest. |
| `php artisan demo:restore --file=name.dump` | Guarded restore to the same allowlisted DB name only; validates filename, directory, matching manifest, and checksum before `pg_restore --single-transaction`. |
| `php artisan demo:credentials` | Lists internal and portal accounts whose email ends in `.test`; displays only the configured shared demo password, if present. |
| `php artisan demo:verify` | Planned for Phase 6; not implemented in this tooling unit. |

Snapshots are stored in `DEMO_SNAPSHOT_DIRECTORY` or `api/storage/app/demo-snapshots`. Use backups only for the isolated allowlisted demo database. The restore command replaces database objects in that database.

## Legacy seeder boundary

`DatabaseSeeder` checks the guard before any baseline writes when `app.seed_demo_data` is enabled. Every direct legacy demo seeder entry point also checks the shared guard. In `local` and `demo` environments, that assertion additionally requires the active side-effect scope, so direct `db:seed --class=...DemoSeeder` calls and `DatabaseSeeder` with `SEED_DEMO_DATA=true` stop before writes; use the guarded demo commands instead. The test environment permits direct test seeder calls only against the exact `ogami_test*` allowlist, and PHPUnit pins mail, broadcast, queue and cache to fake/local drivers. The new commands do not call legacy demo seeders as a fallback. System/reference seeders remain available through the existing baseline pathway; the new data layers will replace demo fixtures module group by module group after their process behavior is verified.

Current code moves `DemoBomCostBasisSeeder` from the reference-only list to the demo-only list because its rows are demo-specific. This is the only `DatabaseSeeder` grouping change in Phase 2.

## Current verification boundary

Targeted tests cover environment/database/host guards, controlled time and acting-user restoration, stable Faker output, layer dependency ordering, runner failure/config restoration, command preflight, snapshot manifest/checksum handling, and idempotency for the current layers. The organization layer creates 15 departments and 37 positions without salary grades. The inventory layer creates 8 UOMs, 8 categories, 28 items, one warehouse, 6 zones, and 76 locations without stock balances. The finished-product layer creates 16 products and their 16 matching finished-good items through CRM and Inventory services. Business partners scale from 3 customers / 5 vendors in small mode to 10 / 18 in demo mode and 12 / 24 in full mode; customer codes, product access, and warranty options use the current Accounting/CRM service path. Production assets scale from 4 machines / 16 molds / 3 auxiliary assets in small mode to 30 / 60 / 12 in demo mode and 36 / 96 / 12 in full mode. The BOM/routing layer adds 16 costed BOMs / 44 component lines and 16 active routings / 48 operations through current services. A full focused run passed 15 tests / 503 assertions before the final partner email/tax-ID assertion additions; the final partner test file passes 2 tests / 152 assertions. The subsequent combined rerun hit a PostgreSQL deadlock and a missing table, and an independent `php artisan test` container plus an active migration query were visible on the shared `ogami_test` database. No further suite was started while that process was active. All completed tests used the isolated test database. No demo database reset, full dataset seed, real snapshot, or full PHP suite has been run.
