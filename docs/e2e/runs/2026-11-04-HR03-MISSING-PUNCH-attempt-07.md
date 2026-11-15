# HR03-MISSING-PUNCH — attempt 07

**Result:** The HR correction and Employee DTR live-update assertion passed, but the journey failed its following global Mailpit invariant. The final reload step was blocked by that test assertion.

- **Run ID:** 3531b991-0515-49be-bc23-710495c5cd70
- **Started:** 2026-11-04 15:38:52 UTC
- **Actors:** Employee and HR Officer in separate browser contexts.
- **Baseline:** Fresh isolated E2E reset; this run was not against the dev database.
- **Step evidence:** Employee time-in passed; HR found the missing-punch row and saved the correction; the Employee DTR step rendered the corrected time-out and 7.5 hours without a manual reload. The step then failed its unrelated no-email invariant. Journey step timing and failure are in the archived sanitized context and Playwright trace.
- **Invariant failure:** The assertion compared every Mailpit message ID. It found 12 new critical-stock alert messages addressed to unrelated seeded recipients, not the Employee or HR actors. The assertion therefore failed despite no attendance email being sent to either journey actor.
- **Limit:** The reload step did not execute. This run is evidence of the live DTR update only, not reload persistence or a clean final mail assertion.

Artifacts: [sanitized journey context, error context, screenshot, and Playwright trace](assets/hr03-missing-punch-attempt-07/).
