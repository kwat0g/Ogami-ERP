# MNT01-BREAKDOWN — attempt 19: stopped after a PHP test process appeared

**Result:** The isolated baseline and selected-role UI auth setup passed. The host-process monitor then detected a PHP test and stopped the E2E stack before any MNT01 business step was confirmed. No journey coverage is credited.

- **Preflight:** A five-minute ancestry-aware quiet window completed. The guarded runner's process matcher also found no active PHP test before startup.
- **Setup:** The isolated E2E stack started and became ready. The guarded `test-split` completed a fresh baseline, the process TypeScript preflight, and the selected auth setup. Playwright reported one passing auth setup test; HR Officer, Maintenance Technician, Maintenance Supervisor, and Production Manager each logged in through the UI and their disposable storage states were refreshed.
- **Interruption:** During the remainder of the `test-split` sequence, the ancestry-aware monitor matched a host process using the same PHPUnit/Laravel-test patterns as the guarded runner. It stopped the 13 containers in the dedicated E2E Compose project. The host PHP test was left untouched. The runner exited 143. The exact subcommand at detection—service restart/readiness or the beginning of the MNT journey—was not established.
- **Journey evidence:** `process-results.json` contains only the passing auth setup test. No fresh MNT journey result, journey context, or MNT test artifact exists. No MNT01 transaction or business UI action is evidenced.
- **Evidence:** [Sanitized runner output and derived auth summary](assets/mnt01-breakdown-attempt-19/). The runner archive passed `scripts/e2e/sanitize_traces.py --check` with zero findings. Disposable storage-state files were not archived.

**Classification:** Environment / run-coordination interruption, ENV-E2E-008. A brief overlap between the E2E command and host PHP test cannot be excluded. No application code changed.
