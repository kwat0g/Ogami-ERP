# MNT01-BREAKDOWN — attempt 18: isolated stack terminated during startup

**Result:** Environment interruption before the MNT01 journey. No process coverage is credited.

- **Preflight:** The ancestry-aware process monitor observed a five-minute window with no active PHP test. A separate check using the guarded runner's PHP-process matcher also found none.
- **Command:** A supervised sequence began the dedicated E2E stack startup, health wait, and then planned one `test-split --grep MNT01-BREAKDOWN` run. The supervisor process exited with signal-derived status 143 before it emitted a completed-stage summary. It did not report a PHP-test match.
- **Container evidence:** Docker events show the dedicated E2E services receiving stop/kill signals and being destroyed at approximately 05:28 UTC. The exact initiating process and the precise runner subcommand at shutdown are unknown. The isolated E2E stack is stopped; no host test process was touched.
- **Journey evidence:** No new Playwright result, MNT journey context, or actor storage state was produced. No MNT01 transaction or UI business step is evidenced. Any partial E2E database state is disposable and the next attempt must begin with a fresh guarded baseline.
- **Evidence:** [Sanitized runner output and E2E container events](assets/mnt01-breakdown-attempt-18/runner-and-container-events.zip). The archive passed `scripts/e2e/sanitize_traces.py --check` with zero findings.

**Classification:** Environment interruption, ENV-E2E-002. No application code changed.
