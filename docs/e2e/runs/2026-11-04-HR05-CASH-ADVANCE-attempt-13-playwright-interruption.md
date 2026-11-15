# HR05-CASH-ADVANCE — attempt 13: Playwright startup interruption

**Result:** Environment interruption after Playwright announced one worker, before it emitted the selected journey title or a step result (exit 130).

- **Setup:** The guarded E2E stack was started and reported healthy. The prior attempt had already freshly seeded the isolated database and signed in the five selected actors through the UI.
- **Observed:** The one-off Playwright container printed `Running 1 test using 1 worker`, then received a signal before a journey context or result was written. No step completion can be confirmed; transactional activity from the interrupted process could not be independently established before the environment was reset.
- **Classification:** Environment issue, tracked by ENV-E2E-002. No application defect is inferred from this attempt.
- **Recovery:** Attempt 14 performed a new `migrate:fresh --seed` and E2E account seed before starting the journey, so this attempt's data could not carry over.

No process coverage is claimed for this invocation.
