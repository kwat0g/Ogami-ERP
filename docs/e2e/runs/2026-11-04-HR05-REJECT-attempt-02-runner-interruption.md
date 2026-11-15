# HR05-REJECT — attempt 02: Playwright process interruption

**Result:** The fresh-baseline Playwright command printed that it was running one test, then exited with code 130 before producing a Playwright result or journey context. This is an environment interruption under [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated), not a process pass or product failure.

- **Execution:** Fresh E2E schema/reference seed and 37 isolated actor accounts completed; Employee, Department Head, and HR Officer signed in through the real UI. The exact `HR05-REJECT` test was then started in headless Chromium with one worker and no retries.
- **Evidence after interruption:** A read-only query against the isolated `ogami_e2e` database found no loan whose purpose contained this variant's unique rejection-verification marker. No matching business transaction was created. The partial Playwright invocation emitted no durable journey context or test-result report.
- **Follow-up:** The isolated baseline was reset before the next business attempt. The test was run separately from setup/orchestration commands.

No application change was made. This interruption does not count toward the HR-05 variant outcome or transition coverage.
