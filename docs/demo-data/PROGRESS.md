# Demo data rebuild progress

- Phase 0 complete: [00-discovery.md](00-discovery.md), with supporting event and relationship inventories.
- Phase 1 approved by the user. Proposed volumes and scenarios are approved as the planning baseline; unresolved statutory, payroll, date, shift, and counterparty questions remain open in [01-data-plan.md](01-data-plan.md).
- Phase 2 architecture/tooling is in progress on branch `feat/demo-seeder-tooling`; implementation details are in [02-architecture.md](02-architecture.md).
- No new demo data has been seeded. The registered layer list is deliberately empty until Phase 3/4 data layers are implemented.
- Legacy demo seeders remain in place for test/backward compatibility and are not used as the new pipeline. Their Phase 0 audit disposition remains the basis for later replacement work.
- Findings: [FINDINGS.md](FINDINGS.md).
- FK inventory: [SCHEMA-RELATIONSHIPS.md](SCHEMA-RELATIONSHIPS.md).

Discovery evidence: 23 modules, 284 public tables (202 non-empty), 794 foreign keys, 369 route paths, 76 widgets, and 11 KPI definitions. The legacy demo seed failed at default memory; fresh analytics/process verification reported six categories, and configured-DB read-only verification reported four. Details and limitations are in [00-discovery.md](00-discovery.md) and [FINDINGS.md](FINDINGS.md).

Phase 2 focused tests pass for the guard, context, layer registry, runner, commands, snapshot manager, legacy demo seeder entry points, demo setup, and BOM cost basis: 64 tests / 250 assertions. The latest guard/runner/production-assertion rerun after the final scope changes passed 32 tests / 81 assertions. Browser fixture PHP files lint clean; browser E2E was not run. One adjacent DSS demo-seeder regression test remains failing because it invokes an unregistered detector; see [FINDINGS.md](FINDINGS.md#discovery-findings). No full PHP suite, `migrate:fresh`, real snapshot, or demo dataset seed was run in this tooling unit. Existing unrelated untracked `.github/hooks/` and `docs/e2e/` remain untouched.

Next: finish Phase 2 review and PR; then implement Phase 3 reference/master-data layers. Payroll/statutory-dependent records remain gated on the open policy inputs. Do not run `demo:reset` until concrete layers exist and the target database is confirmed allowlisted.
