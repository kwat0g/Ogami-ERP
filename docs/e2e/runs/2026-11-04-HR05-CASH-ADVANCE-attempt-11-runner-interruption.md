# HR05-CASH-ADVANCE — attempt 11: runner interruption

**Result:** Environment interruption before the business journey. The selected role login project passed, but the runner was terminated during its E2E service restart and emitted no Chromium journey result (exit 143).

- **Baseline:** `migrate:fresh --seed` and the E2E account seeder completed; 37 isolated actors were present and no transactional demo records were seeded.
- **Actors:** Employee, Department Head, Finance Officer, Vice President, and HR Officer all signed in through the real UI and saved role sessions.
- **What ran:** `runner.mjs test-split --grep HR05-CASH-ADVANCE` performed the baseline reset and auth setup, then restarted the dedicated E2E services while preserving volumes.
- **What did not run:** No Chromium process-step title, journey context, or business action was emitted. This attempt is not counted as process coverage.
- **Classification:** Environment issue, tracked by ENV-E2E-002. The shutdown source remains unknown.

No application code or journey assertions changed. The later attempt 14 used a fresh baseline.
