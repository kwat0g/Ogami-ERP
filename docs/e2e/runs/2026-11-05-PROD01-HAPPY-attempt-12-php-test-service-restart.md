# PROD01-HAPPY — Attempt 12: host PHP test interrupted post-login service restart

**Date:** 2026-11-05 UTC  
**Outcome:** Fresh E2E baseline and six real-UI sign-ins passed; the host test stopped the service restart before the journey  
**Classification:** Environment issue ENV-E2E-008  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Guarded attempt after an uninterrupted 300-second quiet interval.

## Result

The isolated stack started and `migrate:fresh --seed` completed, including the E2E reference data and all 38 isolated E2E actors. The auth-setup project signed in all six selected roles through the login UI (`hr_officer`, `warehouse_staff`, `ppc_head`, `production_manager`, `qc_inspector`, and `employee`); Playwright reported one setup test passed.

`test-split` then stopped and restarted services while preserving the E2E database and storage. During this post-auth restart/readiness stage, the host-test monitor detected a PHP test, terminated the E2E runner process group, and stopped the four remaining running containers with the dedicated `ogami-erp-e2e-session` label. The external test was left untouched. The monitor did not retain its command.

The production business journey was never launched. No work order, opening-stock adjustment, shift assignment, or other process transaction was created in this attempt. No journey context, screenshot, or trace was produced. The isolated stack is down; the next attempt will start from a fresh baseline.

## Evidence

- Guarded supervisor output: `/tmp/ogami-prod01-attempt-12-supervised.log`
- `E2EReferenceDataSeeder` and `E2EAccountSeeder` completed; 38 actors were seeded.
- Playwright auth setup: 1 passed; all six selected actors signed in through the real login UI.
- The container shutdown was limited to the remaining dedicated E2E project services; persistent E2E volumes were preserved.
