# HR05-CASH-ADVANCE — attempt 10 (final notification type)

- **Date:** 2026-11-04 UTC
- **Classification:** E2E test issue, TST-HR-064; final notification remains unverified in this run
- **Journey:** `HR05-CASH-ADVANCE`, tenth browser invocation
- **Actors:** Employee; Department Head; Finance Officer; Vice President; HR Officer observer, each in an isolated session
- **Command:** `E2E_AUTH_ACTORS=employee,department_head,finance_officer,vice_president,hr_officer node ../scripts/e2e/runner.mjs test-split --grep HR05-CASH-ADVANCE`
- **Result:** Fresh baseline, typecheck, and all five real-UI logins passed. All four process steps executed through the owning actor's UI. Submission and Finance notification checks, the Department Head Approval Queue hand-off, Finance and VP notification hand-offs, each approval/audit actor, the Employee live Active row, the HR live detail, and the balanced posted journal/VP poster assertions passed. The final invariant failed because it queried `approval.completed` for a loan and found no row.
- **Source review:** The loan outcome listener is `NotifyOnLoanDecided`; it emits `loans.approved` or `loans.rejected` and implements `ShouldQueue`. Generic `ApprovalService::NOTIFY_OUTCOME` excludes `loan`. Attempt 08's read-only follow-up also found `loans.approved` addressed to the Employee. Attempt 10 did not verify that queued notification after delivery; the test needs to wait for the Loans-specific event.
- **Run data:** Playwright context `d8911ff0-161c-4554-a72f-61a14226b61a`; loan `CA-202611-0001`; public loan ID `j16KyzV0Xl`; principal ₱1,000.00; two payroll periods.

## Step results

| Step | Actor | Result |
|---|---|---|
| 1. Submit cash advance | Employee; HR Officer observer | **Passed.** UI creation, ordered pending chain, bounded Finance FYI poll/exact recipients, no journal, and observer live update. |
| 2. Find and approve first step | Department Head | **Passed.** Approval Queue hand-off, UI approval, linked approval row and actor audit. |
| 3. Follow Finance hand-off and approve | Finance Officer | **Passed.** Notification hand-off, UI approval, linked approval row/audit and recipient checks. |
| 4. Follow VP hand-off and approve | Vice President | UI action and server/UI final-state invariants passed, including Employee and HR live updates, all approvals, balanced journal, null `created_by`, and VP `posted_by`. The last assertion searched for the wrong notification type. The journey-actor Mailpit and diagnostics checks were not reached. |

## Evidence and correction

- [Sanitized trace, failure context, six screenshots, walkthrough images, and journey context](assets/hr05-cash-advance-attempt-10/).
- Change the final recipient assertion to poll for `loans.approved` and assert the exact Employee recipient. Assert no generic `approval.completed` loan notice is present after that event arrives.
- No application code changed. Transactional steps were performed through the UI; database reads remain verification-only.
