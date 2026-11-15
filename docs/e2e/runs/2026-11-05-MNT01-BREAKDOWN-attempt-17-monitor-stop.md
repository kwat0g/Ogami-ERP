# MNT01-BREAKDOWN — attempt 17: temporary watcher stopped the E2E stack

**Result:** The temporary process watcher stopped the E2E stack before any MNT01 process step could be confirmed. No journey coverage is credited.

- **Preflight:** No host PHP test process was detected during a 60-second quiet window. The guarded `test-split --grep MNT01-BREAKDOWN` command then began with the four selected actors configured.
- **Interruption:** During the synchronous guarded run, a temporary host-process watcher reported a PHP-test match and stopped the 12 containers belonging to the dedicated `ogami-erp-e2e-session` Compose project. The runner exited 137. The PHP-test process was not touched.
- **Progress evidence:** No fresh Playwright process result or journey context was produced; the existing Playwright result and MNT context retain attempt-16 timestamps. The selected actor storage-state files also retain their prior timestamps, so role login completion is not evidenced for this attempt. The exact synchronous runner subcommand at termination is unknown because its E2E container was stopped.
- **Watcher limitation:** The temporary watcher used substring matching on process command lines. Its own launching shell command contained the same PHP-test search strings, so the reported match may have been a self-match. A separate check with the guarded runner's PHP-process matcher after shutdown found no active PHP test. There is no independent evidence that a PHP test was active at the moment the watcher stopped E2E; a brief overlap cannot be ruled out.
- **Process effect:** No MNT01 transaction or UI business step is evidenced. Any partially reset E2E database state is disposable; the next attempt must start from a fresh guarded baseline.
- **Concurrency limit:** The watcher stopped the isolated stack, but its match is inconclusive and it cannot provide an atomic lock against an externally started PHP test. ENV-E2E-008 remains open.
- **Evidence:** [Sanitized runner output](assets/mnt01-breakdown-attempt-17/runner-output.zip). It passed `scripts/e2e/sanitize_traces.py --check` with zero findings.

**Classification:** Environment / run-coordination interruption. No application code changed.
