# HR05-CASH-ADVANCE — attempt 06 (queued submission notice timing)

- **Date:** 2026-11-04 UTC
- **Classification:** E2E test issue, TST-HR-059; no application finding assigned
- **Journey:** `HR05-CASH-ADVANCE`, sixth browser invocation
- **Actors:** Employee; Department Head; Finance Officer; Vice President; HR Officer observer, each in an isolated session
- **Command:** `E2E_AUTH_ACTORS=employee,department_head,finance_officer,vice_president,hr_officer node ../scripts/e2e/runner.mjs test-split --grep HR05-CASH-ADVANCE`
- **Result:** Fresh migration and reference/E2E seeding completed; all five actors signed in through the real UI. Employee submitted a cash advance through Self-service Loans (HTTP 201). The request was `pending`, its three approval rows were pending in order, no disbursement journal existed, and the open HR observer saw and opened the request without a reload. The immediate read-only notification query returned no `loans.submitted` rows, so step 1 failed at the recipient invariant and steps 2–4 were blocked.
- **Read-only follow-up:** After the test ended, a transaction explicitly marked `READ ONLY` found both expected Finance FYI notifications for this loan. Their database timestamps were in the same second as submission. The listener `NotifyOnLoanSubmitted` implements Laravel `ShouldQueue`, so this run's invariant queried before the queue worker delivered the messages. No approval or other loan mutation was performed.
- **Run data:** Playwright context `82f7cf45-9be3-4e71-92b2-82e6f5e1d13b`; loan `CA-202611-0001`; public loan ID `j16KyzV0Xl`; principal ₱1,000.00; two payroll periods.

## Step results

| Step | Actor | Result |
|---|---|---|
| 1. Submit cash advance | Employee; HR Officer observer | UI creation, pending status, ordered approval rows, no journal, and live observer update passed. The immediate Finance-recipient assertion ran before the queued notices appeared and failed. A later read-only query found both intended Finance recipients. |
| 2. Find and approve current step | Department Head | **Blocked.** Not run; the Approval Queue hand-off remains unverified under TST-HR-058. |
| 3. Follow Finance hand-off and approve | Finance Officer | **Blocked.** |
| 4. Follow VP hand-off and approve | Vice President | **Blocked.** |

## Evidence and correction

- The test now polls the read-only notification state for the configured Finance recipient count, with a bounded 15-second timeout, before asserting the exact recipient set. This is an explicit async condition for a queued side effect, not a fixed sleep or retry of the journey.
- [Sanitized trace, failure context, screenshots, and journey context](assets/hr05-cash-advance-attempt-06/).
- No application code changed. The failed run's pending transaction remains isolated in the disposable E2E database and is cleared by the next fresh baseline.
