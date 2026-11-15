# HR02-HAPPY — attempt 07 (Playwright container interrupted after auth setup)

- **Date:** 2026-11-04 UTC
- **Classification:** Environment issue (E2E Compose interruption)
- **Command:** `E2E_AUTH_ACTORS=hr_officer,system_admin node scripts/e2e/runner.mjs test --grep HR02-HAPPY`; guarded `up` and `wait` had restored a healthy stack before invocation.
- **Environment:** Dedicated `ogami-erp-e2e` Compose project; fresh migration and E2E baseline; one headless Chromium worker.
- **Result:** Fresh baseline and selected actor setup completed. Playwright reported the `auth-setup` test passed for HR Officer and System Admin and began the two-test run. The E2E services and one-off Playwright container then received stop signals before the HR02 journey emitted a result. No business step, journey-context update, or new `process-results.json` was produced. The existing process-results JSON belongs to attempt 05 and is not evidence for this attempt.
- **Container evidence:** Docker events at 19:22:29 UTC show stop signals to the E2E services and the one-off Playwright container. Container status inspection showed normal exit for API/database and signal-derived exits for SPA/Reverb/scheduler; queue-main exited 137 after SIGKILL. No Docker OOM indication was present in the inspected status. The stop source remains unknown.
- **Data state:** A new fresh baseline had completed before Playwright setup. No HR posting or other transactional process record was created by this interrupted attempt.
- **Evidence:** [archived runner log](assets/hr02-happy-attempt-07-playwright-interrupted/runner.log). No trace, screenshot, or current Playwright result exists for the interrupted journey.

This recurrence is tracked under ENV-E2E-002. It is an environment interruption and does not count as a failed or passed business journey.
