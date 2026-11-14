# Demo seeder architecture and command reference

Status: Phase 2 tooling implementation. The approved phase plan is in [01-data-plan.md](01-data-plan.md). No transactional demo data layer is registered yet, so `demo:seed` and `demo:reset` fail before data writes or destructive database commands.

## Layer model

Demo data is assembled in dependency-ordered layers registered in `config/demo.php` under `layers`. Each layer implements `DemoSeedLayer`; the `DemoSeeder` base class is available for Laravel-style seeder classes. A layer declares a stable key, the module names it covers, dependencies, and a `run(DemoSeedContext $context)` method.

`DemoSeedLayerRegistry` validates configured layers and dependency keys, rejects duplicates and cycles, and topologically orders the selected layers. `--only=<layer-or-module>` selects matching layers and includes their dependencies. Empty registration is an intentional safety stop while the approved data layers have not been built.

`DemoSeedRunner` checks the production guard itself, resolves the selected layers, disables the active connection's query log, and executes each layer with a stable seed derived from the global seed and layer key. It reports layer start/finish events with elapsed time and peak PHP memory, collects cycles between layers, and wraps failures with the layer key. `DemoRecordRegistry` stores lightweight model-class/primary-key references between layers rather than retaining complete model collections.

`DemoSeedContext` contains the parsed as-of date, scale, seed, filter and no-history option; a fixed-seed `en_PH` Faker generator; the shared record registry; and helpers for acting as a user, controlling mutable and immutable Carbon clocks, and faking notification delivery around historical actions. Each helper restores prior state in `finally`. Historical layers should call `withoutNotifications()` around old transitions so domain listeners still run without leaving stale inbox notifications; live scenario layers use normal notification delivery for currently open work. Layers must use the clock helper around historical domain actions; the runner does not move application time globally because overdue and dashboard behavior follows runtime time.

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

Browser fixtures that still depend on the legacy `DemoAccountSeeder` use `api/tests/Browser/demo_seed_helpers.php`. The helper requires a disposable `ogami_test_*` database, explicitly allowlists that exact database for the process, and runs the seeder inside the side-effect scope. Migrating E2E baselines to the shared `--scale=small` layers remains open until those concrete layers exist.

## Commands

| Command | Current behavior |
|---|---|
| `php artisan demo:seed [--as-of=YYYY-MM-DD] [--scale=small\|demo\|full] [--seed=N] [--only=layer-or-module] [--no-history]` | Parses options, checks the guard and layer plan, then runs registered layers. It currently stops with “No demo seed layers are registered yet.” |
| `php artisan demo:reset [--as-of=...] [--scale=...] [--seed=...] [--no-history]` | Checks the guard and layer plan before `migrate:fresh`. With no registered layers it stops before destructive work. Once layers exist, it applies migrations, runs system baseline seeding without legacy demo/reference payloads, then runs the demo layers. |
| `php artisan demo:snapshot [--file=name.dump]` | Guarded PostgreSQL custom-format `pg_dump`; writes a SHA-256 manifest alongside the dump. Password is passed through the child process environment, not command arguments or manifest. |
| `php artisan demo:restore --file=name.dump` | Guarded restore to the same allowlisted DB name only; validates filename, directory, matching manifest, and checksum before `pg_restore --single-transaction`. |
| `php artisan demo:credentials` | Lists internal and portal accounts whose email ends in `.test`; displays only the configured shared demo password, if present. |
| `php artisan demo:verify` | Planned for Phase 6; not implemented in this tooling unit. |

Snapshots are stored in `DEMO_SNAPSHOT_DIRECTORY` or `api/storage/app/demo-snapshots`. Use backups only for the isolated allowlisted demo database. The restore command replaces database objects in that database.

## Legacy seeder boundary

`DatabaseSeeder` checks the guard before any baseline writes when `app.seed_demo_data` is enabled. Every direct legacy demo seeder entry point also checks the shared guard. In `local` and `demo` environments, that assertion additionally requires the active side-effect scope, so direct `db:seed --class=...DemoSeeder` calls and `DatabaseSeeder` with `SEED_DEMO_DATA=true` stop before writes; use the guarded demo commands instead. The test environment permits direct test seeder calls only against the exact `ogami_test*` allowlist, and PHPUnit pins mail, broadcast, queue and cache to fake/local drivers. The new commands do not call legacy demo seeders as a fallback. System/reference seeders remain available through the existing baseline pathway; the new data layers will replace demo fixtures module group by module group after their process behavior is verified.

Current code moves `DemoBomCostBasisSeeder` from the reference-only list to the demo-only list because its rows are demo-specific. This is the only `DatabaseSeeder` grouping change in Phase 2.

## Current verification boundary

Targeted tests cover environment/database/host guards, controlled time and acting-user restoration, stable Faker output, layer dependency ordering, runner failure/config restoration, command preflight, and snapshot manifest/checksum handling. This unit did not run `migrate:fresh`, reset a database, create a real snapshot, run the full suite, or seed demo data. Those remain explicit verification tasks after the data layers and isolated demo database are ready.
