# E2E run note — 2026-11-03, auth setup latency

Suite: guarded `node scripts/e2e/runner.mjs test`; Chromium headless, one worker. The E2E-only baseline was freshly reset. No PHP suite or shared/dev database was used.

## Outcome

- Auth setup reached the warehouse-scoped department-head account after prior actor sign-ins. Its complete sign-in test step took 12.25 seconds.
- The auth setup used the shared 8-second URL assertion and failed while the page still showed the pending sign-in state. Nginx recorded the UI-triggered `/api/v1/auth/sign-in` response as HTTP 200. This is currently classified as an E2E timeout/test-environment latency finding; the UI landing result was not verified.
- The procurement journey did not start in this run. No transactional record was created.
- Platform controls had passed in the prior completed run (gated clock, read-only DB reader, Mailpit).

## Evidence and follow-up

- [Masked login screenshot](2026-11-03-auth-setup-blocked.png) shows the form pending at failure.
- Auth setup now waits for the UI-triggered sign-in response and allows a bounded 20-second redirect assertion. The response must still be HTTP 200 and the correct landing page must still appear.
- Raw trace bundles from this auth failure were removed because Playwright DOM snapshots can retain the disposable E2E password. The failure screenshot is retained; auth setup trace handling is being changed to avoid credential capture.

## Earlier PR01 harness evidence

- First run timed out on a label locator before any transaction was created. The form exposed the required select through its accessible role; the locator was corrected.
- Second run created a Draft PR through the requester UI with RM-001, quantity 2, and ₱240.00 estimated total. The test then read a stale form heading because its URL matcher accepted `/new`. The E2E baseline was reset before further attempts.
- Those were test-harness defects, not application findings. No process step beyond draft creation has completed.
