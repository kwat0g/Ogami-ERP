# HR05-CASH-ADVANCE — attempt 05 preflight interruption

- **Date:** 2026-11-04 UTC
- **Classification:** Environment issue (ENV-E2E-002); no HR05 journey result
- **Journey:** `HR05-CASH-ADVANCE`, fifth browser-runner invocation (no browser launched)
- **Command:** `E2E_AUTH_ACTORS=employee,department_head,finance_officer,vice_president,hr_officer node ../scripts/e2e/runner.mjs test-split --grep HR05-CASH-ADVANCE`

## Result

The first invocation was refused by the runner because the isolated E2E stack was not running; no reset or business action occurred. Guarded `up` and `wait` restored healthy E2E services. A second invocation began `migrate:fresh --force --seed` and stopped during migration 0262 while creating `material_review_records`. Docker events at 21:26:28 UTC show the E2E services receiving stop signals; queue workers later required SIGKILL. PostgreSQL logged an administrative fast shutdown while the migration's `CREATE TABLE` statement was active. This is an interrupted baseline, not a migration defect inferred from the incomplete statement.

No actor authentication, Playwright test, transactional business action, or process step ran. The runner did not produce a complete baseline. ENV-E2E-002 tracks this recurring service-shutdown source; the next attempt must first restore and verify the guarded stack and complete a fresh seed.
