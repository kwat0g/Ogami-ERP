# HR08-TERMINATION — attempt 02: baseline seed interrupted

**Overall result:** Environment interruption before Playwright started. This attempt has no journey result and does not count as a completed or product-blocked variant.

- **Environment:** The stack-aware supervisor observed a fresh 300-second period with no E2E Compose containers and no PHP test process. It started only the isolated `ogami-erp-e2e-session` project and waited for its services to become healthy.
- **Baseline:** `runner test --grep HR08-TERMINATION` began its normal fresh baseline. `migrate:fresh --seed` completed migrations and entered the default seeders. The child runner received SIGTERM while `HolidaySeeder` was running; the supervisor recorded exit `-15` and brought down only the isolated E2E project.
- **Playwright:** The Playwright runner/container was not reached. No browser authentication, journey step, transactional UI action, screenshot, or journey context was produced.
- **Post-run check:** At 17:55:26 UTC, no E2E Compose containers or host PHP test process were visible. The supervisor did not report that its PHP-test detector fired. The system kernel log was inaccessible (`dmesg: Operation not permitted`), and Docker's event stream only showed isolated-container shutdown after the child exit; the signal source is unknown.

The dedicated E2E database may contain a partial baseline, but the next guarded `test` command performs `migrate:fresh --seed` again before Playwright. No development or production database was accessed. No business transaction ran. This is an unexplained environment interruption; do not count it as test evidence for HR08-TERMINATION.

**Classification:** Environment interruption under `ENV-E2E-008`. No application code changed. A fresh stack-free and PHP-test-free 300-second interval is required before another attempt.
