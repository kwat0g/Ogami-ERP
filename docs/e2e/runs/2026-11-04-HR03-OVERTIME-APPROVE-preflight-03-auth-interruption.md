# HR03-OVERTIME-APPROVE — preflight attempt 03 (auth interruption)

**Result:** The real UI auth setup timed out waiting for the Employee sign-in response after 20 seconds. The Employee form remained in its submitting state. The overtime journey did not begin and no transactional record was created by this invocation.

- **Journey:** `HR03-OVERTIME-APPROVE`
- **Selected actors:** Employee and Department Head; the Employee sign-in was first and did not complete.
- **Invocation:** `E2E_AUTH_ACTORS=employee,department_head node scripts/e2e/runner.mjs test --grep HR03-OVERTIME-APPROVE`
- **Observed:** `page.waitForResponse` did not observe `/api/v1/auth/sign-in` within 20 seconds. The browser remained on `/sign-in` with the Submit button busy. The cause may have been before the sign-in request (the UI primes Sanctum CSRF first); the retained evidence does not identify the failing request.
- **Follow-up:** A later Playwright result in the shared output directory signed in both selected actors successfully. The guarded `runner wait` also passed after the timeout, but the stack/runner interruption was not root-caused. This invocation is classified under the existing environment issue ENV-E2E-002.
- **Artifact limit:** Playwright uses one shared output directory. A subsequent run replaced this invocation's error context and screenshots before they could be archived; auth setup tracing is intentionally disabled so the disposable password is not recorded. This loss is also recorded under TST-INV-012. The terminal error and a screenshot were observed in the active session, but the screenshot was not retained.
- **Status:** No business action occurred. Preserve this failed setup result; do not count it as a completed or blocked business variant.
