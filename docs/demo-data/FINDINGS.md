# Discovery findings

Initial observations captured 2026-11-14. Historical evidence is retained; Phase 2 status is noted for findings addressed by the tooling branch.

| ID | Finding | Evidence/impact |
|---|---|---|
| F-001 | Default PHP memory insufficient | Fresh demo failed at 128M in RealisticDataSeeder; 768M completed in 3m17.69s, ~304MiB RSS. |
| F-002 | Configured ogami DB is not a validated demo snapshot | Read-only checks found 4 provenance-orphan invoices, 4 paid/partial invoices without collections, 37 failed jobs, 3 passed inspections without sample measurements. |
| F-003 | Fresh legacy data fails process/analytics | No delivery invoice link, full production-to-cash chain or supplier QC history; eight empty widgets, no KPI points, no detector runs. |
| F-004 | Historical timestamps inaccurate | No controlled clock; past business dates have seed-run created/approval/audit times. 2,619 holiday attendance rows. |
| F-005 | Fake identities not safe enough | Employee email .local; phones look routable; ID values lack demo marker. |
| F-006 | Payroll gaps | Absence/unpaid leave do not reduce basic; leave field zero; net clamps to zero; statutory loan types unsupported. |
| F-007 | Policy tables incomplete | No 2026 contribution table, minimum-wage rule, or 2025/2027 holidays. |
| F-008 | Runtime mismatch/no catcher | Host local, container production with cached config; SMTP/Redis/Reverb active; no catcher. |
| F-009 | Incomplete guard | Initial state: selective guard; local no DB allowlist; no central DatabaseSeeder guard. Phase 2 adds a shared exact DB/host guard, checks `DatabaseSeeder` before writes, guards direct legacy demo seeder entries, and requires the runner's side-effect scope for local/demo direct calls. |
| F-010 | Missing process/master coverage | No production WOs/schedules, calibration, training, skills, spare use; 15 molds; one pending approval. Direct transaction writes exist. |
| F-011 | Reference set uneven | Root DB: 202 employees, 12 customers, 16 vendors, 29 items, 9 products, 36 machines, 15 molds. |
| F-012 | DatabaseSeeder is not a safe demo command | Initial state: optional legacy path, guard gap and default memory failure. Phase 2 prevents local/demo use of its legacy demo path outside the runner. New commands currently stop because no replacement data layers are registered. |
| F-013 | DSS demo scenario test references an unregistered detector | Reproduced 2026-11-14 with `php artisan test tests/Feature/DSS/DssDemoScenarioSeederTest.php`: `DssDemoScenarioSeeder` runs `dss.work_order_intervention`, but `DetectorRegistry` throws `Unknown detector`; the test then cannot find the expected recommendation. No workaround or app fix applied. |
| F-014 | Staged layer registration could unlock a partial destructive reset | Adding the first layer makes the registry non-empty, which previously let `demo:reset` pass preflight and run `migrate:fresh` before the planned dataset existed. Addressed in the tooling branch by a code-level `demo.reset_ready` gate defaulting to false; it must remain false until all required layers exist and verification passes. |

For later findings include source path, reproduction, impact and workaround approval state. F-013 is unrelated to Phase 2 guard/tooling changes and needs a separate app-fix decision before the DSS storyline can be seeded through its detector flow.
