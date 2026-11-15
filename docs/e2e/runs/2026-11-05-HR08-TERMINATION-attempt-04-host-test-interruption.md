# HR08-TERMINATION — attempt 04: host-test guard interrupted preflight

**Overall result:** Environment interruption after baseline seeding and before the TypeScript preflight or Playwright. No journey result was produced.

- **Environment:** The supervisor observed a fresh 300-second interval with no PHP test or E2E Compose containers, then started the isolated E2E project and waited for healthy services.
- **Baseline:** The normal guarded `test --grep HR08-TERMINATION` command completed fresh migrations, default seeding, the E2E reference-data seed, and creation of 38 E2E-only actors. The seed explicitly created no transactional demo records.
- **Guard interruption:** At 18:31:40 UTC the monitor detected `docker compose exec -T api php artisan test --filter=test_horizon_monitors_the_actual_outbox_queue_connection tests/Feature/Queue/QueueLaneAssignmentTest.php`. It terminated only the guarded runner with exit 91 and stopped the isolated E2E project.
- **Playwright:** The process stopped before the containerized TypeScript preflight. No authentication setup, browser, journey step, or transaction ran.
- **Post-run check:** No E2E containers or PHP test process were visible after teardown. The next guarded test invocation will recreate the E2E baseline before any journey.

No development database was used by this attempt, and no product behavior was exercised. This is an environment/run-coordination interruption under `ENV-E2E-008`; do not count it toward HR08-TERMINATION coverage.
