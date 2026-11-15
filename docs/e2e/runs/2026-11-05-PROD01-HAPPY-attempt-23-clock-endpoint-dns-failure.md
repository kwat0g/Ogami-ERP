# PROD01-HAPPY — Attempt 23: E2E clock endpoint DNS failure

**Date:** 2026-11-05 UTC  
**Outcome:** Auth setup passed; the browser journey failed before its first process step  
**Classification:** Environment issue, ENV-E2E-002  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Fresh guarded E2E baseline, TypeScript preflight, six selected real-UI logins, one headless Chromium worker, split-run service restart. The five-minute host PHP-test quiet interval completed.

## What ran

The E2E baseline and account seed completed. TypeScript preflight passed, and the Playwright auth setup signed in all six selected actors through the real UI. The split-run restart completed and the services passed health checks.

The single browser journey then failed at its first setup call, `freezeClock()`: Playwright's API request context reported `getaddrinfo EAI_AGAIN nginx` for `POST https://nginx/api/v1/_e2e/clock`. The clock request did not reach the API. No role page was opened, no process step ran, and no business transaction was created. All 21 journey steps remained unexecuted.

The PHP-test monitor reported no interruption. Docker events show SIGQUIT/SIGTERM cleanup signals to the isolated E2E services after the browser error; the source of those signals is unknown. No OOM event was found. The stack containers are removed and the dedicated E2E volumes are preserved.

## Result and next check

This is an environment failure, not a process outcome or application defect. The isolated runner has previously shown intermittent resolution failures for the `nginx` service alias. To test the same journey without the split-run network teardown/recreation between auth and the journey, attempt 24 will use the guarded `test --grep PROD01-HAPPY` command. That command still seeds a fresh baseline, runs the setup project, and executes one journey with one worker. The DNS root cause remains open under ENV-E2E-002.

## Artifacts

- [Sanitized Playwright error context](assets/prod01-happy-attempt-23/error-context.md)
- [Sanitized trace](assets/prod01-happy-attempt-23/trace.zip)
- [Failure screenshot](assets/prod01-happy-attempt-23/test-failed-1.png)
- Supervisor log: `/tmp/ogami-prod01-attempt-23-supervised.log`
- The runner's final trace scan reported zero credential-like values.
