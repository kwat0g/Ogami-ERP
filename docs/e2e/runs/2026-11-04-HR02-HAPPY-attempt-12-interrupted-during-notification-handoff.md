# HR02-HAPPY — attempt 12 (interrupted during notification hand-off)

- **Date:** 2026-11-04 UTC
- **Classification:** Environment interruption (ENV-E2E-002); partial process run, no product finding inferred
- **Journey:** `HR02-HAPPY`, 12th invocation
- **Actors:** HR Officer, public applicant, System Admin observer
- **Command:** `E2E_AUTH_ACTORS=hr_officer,system_admin node scripts/e2e/runner.mjs test --grep HR02-HAPPY` (guarded fresh baseline; headless Chromium, one worker)
- **Run IDs:** Playwright run `97f5c371f401`; context `4dbd6e54-d4cc-4d88-bd1b-f1662a910902`
- **Result:** Selected HR Officer and System Admin UI sign-ins passed. The first three of ten journey steps passed. Step 4 began and reached the System Admin observer's Applications list, but the run was interrupted while waiting for the run-scoped candidate row. Steps 5–10 did not run. This is not a completed journey and does not count toward variant coverage.

## Steps

| Step | Actor | Result | Evidence |
|---|---|---|---|
| 1. Create a run-scoped draft posting | HR Officer | **Passed.** Created `JP-202611-0001` through Recruitment UI. Draft status, one opening, creator, unique title, and audit invariant passed. | Walkthrough screenshot `assets/hr02-happy-attempt-12-environment-interrupt-after-step-03/walkthrough/001-hr-creates-a-run-scoped-draft-posting-in-recruitment.png`; journey context. |
| 2. Publish posting | HR Officer | **Passed.** UI action changed posting to open and set the posted timestamp; checks and audit invariant passed. | Walkthrough screenshot `.../walkthrough/002-hr-publishes-the-posting.png`; trace. |
| 3. Apply through public Careers | Public applicant | **Passed.** Candidate `E2E Candidate 97f5c371f401` applied through the public UI. The public submission returned 201; application `JA-202611-0001`, tracking code `RCT-EQNU3K`, database assertions, confirmation email, and page diagnostics passed. | Walkthrough screenshot `.../walkthrough/003-candidate-finds-the-live-posting-and-applies-through-careers.png`; journey context; trace/network archive. |
| 4. HR follows notification; System Admin observes application | HR Officer, then System Admin | **Interrupted.** HR opened Notifications from the sidebar and followed the `New Job Application` notification to the correct application, which showed New with `Move to Screening` enabled. System Admin used its own session and Recruitment → Applications, reached the Applications heading, then waited for the run-scoped candidate row. The archived network trace records `GET /api/v1/hr/recruitment/applications` returning 200, but the row assertion did not finish and the response body was not retained. The trace snapshot at interruption showed the Applications page loading skeleton. No conclusion is drawn about observer visibility or real-time behavior. | Archived trace and network files under `assets/hr02-happy-attempt-12-environment-interrupt-after-step-03/traces/`. |
| 5–10. Advance through Screening, Interview, Offer, and Hired; candidate tracking | HR Officer / public applicant | **Blocked by interruption.** Not started. | No later step state or screenshot exists. |

## Environment evidence

The Playwright run and dedicated services stopped while step 4 was active. Container inspection showed the E2E services exited (including SPA 143, Reverb/scheduler 131, and queue-dss 137); inspected `OOMKilled` flags were false. The shutdown source remains unknown. The latest `process-results.json` was not produced for this attempt and is not used as evidence. Attempt 12's context and trace were archived before another run could overwrite them.

Afterward, the isolated stack was restored without resetting its volume. A read-only database query reconfirmed `JA-202611-0001` at stage `new`, linked to the `open` posting. The stack received shutdown signals again at 20:06:58 UTC, about 2m54s after `runner up` at 20:04:04 UTC; the only intervening database operation was the read-only query. Docker's bounded event output identifies the stopped containers and signals but not the initiating process. This is additional ENV-E2E-002 evidence, not another journey run. A fresh baseline is required before the next journey attempt.

## Coverage and data

- **Journey:** 3/10 steps passed, step 4 interrupted, 6 later steps blocked. No final-state assertions were reached; the HR02 happy-path variant remains incomplete.
- **Data:** Posting `JP-202611-0001` and application `JA-202611-0001` existed in the attempt's isolated baseline. A subsequent fresh baseline is required before another attempt. No transactional data was created or advanced by API/DB.
- **Findings:** ENV-E2E-002 updated with this recurrence. No new product defect or process gap is inferred. TST-HR-053 (external OSM diagnostics) and TST-HR-054 (auth 401 allowlist) are resolved by this fresh-baseline run: candidate-step diagnostics and both selected actor sign-ins passed.
- **No retry, assertion weakening, or application code change was made.**

## Artifacts

- [Archived trace, network evidence, context, and three walkthrough screenshots](assets/hr02-happy-attempt-12-environment-interrupt-after-step-03/)
- Journey context: `assets/hr02-happy-attempt-12-environment-interrupt-after-step-03/journey-context.json`
