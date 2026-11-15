# HR05-LOAN-UNDER-THRESHOLD — attempt 06: notification matrix used the completed step

**Result:** Employee submitted the request and the Department Head found it in Approval Queue and approved it through the Department Head's own UI session. The loan remained `pending`; read-only checks showed Department Head `approved`, HR and Finance `pending`, and VP `skipped`. The journey then failed because the test expected no notice for step 2 after the Department Head decision. The actual `approval.action_required` notification identifies step 2 and reached HR.

- **Environment:** Fresh guarded baseline; five selected roles authenticated through the UI; one headless Chromium worker, zero retries.
- **Read-only evidence:** The approval notification dedupe key ends in step order `2` and its recipient is the seeded HR Officer. The workflow action and next-role hand-off both behaved as configured.
- **Correction:** Assert notification recipients by pending workflow step: no step-1 notice; after DH, HR is notified at step 2; after HR, the Finance role audience is notified at step 3. No notice is sent for skipped VP step 4.
- **Classification:** Test issue TST-HR-067; resolved in attempt 07. No product finding.

Evidence: [sanitized trace, screenshots and journey context](assets/hr05-loan-under-threshold-attempt-06/).
