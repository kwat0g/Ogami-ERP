# HR02-HAPPY — attempt 03 (auth setup DNS failure during runner interruption)

- **Date:** 2026-11-04 UTC
- **Classification:** Environment issue (E2E runner / service shutdown)
- **Command:** `E2E_AUTH_ACTORS=hr_officer,system_admin node scripts/e2e/runner.mjs test --grep HR02-HAPPY`
- **Environment:** Guarded `ogami-erp-e2e` Compose project; fresh schema migration and baseline seeding completed. Two selected actors were requested.
- **Result:** Playwright reported `auth.setup` failed for `hr_officer` at `page.goto('/sign-in')` with `net::ERR_NAME_NOT_RESOLVED` for `https://nginx/sign-in`. Auth setup did not finish; the dependent HR02 journey did not run. No `journey-context.json` was produced, no business step was confirmed, and no posting/application transaction was created.
- **Container evidence:** The E2E Compose services received shutdown signals at the same time the Playwright runner returned exit 143. PostgreSQL/API exited cleanly; SPA exited 143 and Reverb/scheduler exited 131. The one-off Playwright container emitted a normal test-failure result before removal. The cause of the service shutdown and the DNS error is not established. A queue-dss container exited 137 with `OOMKilled=false`; no browser OOM is inferred.
- **Correction:** Preserve the artifact and record this occurrence under ENV-E2E-002. For the next invocation, start the guarded runner detached from the short-lived interactive command session so a session signal cannot tear down the Compose services while Playwright resolves `https://nginx`.
- **Evidence:** [auth error context, screenshots, and Playwright JSON result](assets/hr02-happy-attempt-03-dns-startup/). Auth setup tracing is disabled to avoid recording the test password.

This is an environment interruption, not a completed process attempt. It is not counted as a product finding or passed journey.
