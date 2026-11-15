# HR05-CASH-ADVANCE — attempt 08 (final approval and live-view selector)

- **Date:** 2026-11-04 UTC
- **Classification:** E2E test issue, TST-HR-061; no application finding assigned
- **Journey:** `HR05-CASH-ADVANCE`, eighth browser invocation
- **Actors:** Employee; Department Head; Finance Officer; Vice President; HR Officer observer, each in an isolated session
- **Command:** `E2E_AUTH_ACTORS=employee,department_head,finance_officer,vice_president,hr_officer node ../scripts/e2e/runner.mjs test-split --grep HR05-CASH-ADVANCE`
- **Result:** Fresh baseline, typecheck, and all five real-UI sign-ins passed. Employee submitted through Self-service Loans. The exact queued Finance FYI assertion and HR observer live update passed. Department Head used the Approval Queue and approved; Finance followed its notice and approved; Vice President followed its notice and approved. The test confirmed the final `active` loan state and HR detail update. It then failed when the Employee list assertion searched for the internal loan number, which the self-service list does not display.
- **Read-only final state:** Loan `CA-202611-0001` is `active`; all three approval rows are approved by the expected actors. Journal `JE-202611-0001` is posted with debit and credit totals and line sums of ₱1,000.00. The VP is recorded as `posted_by`; source-linked journal entries intentionally leave `created_by` null. The `loans.approved` notice is addressed to the Employee. Mailpit and the test's final exact-notice invariants were not checked because the UI locator failed first.
- **Run data:** Playwright context `60f29b86-3a6f-4a9b-aa35-7ad4112e83d8`; loan public ID `j16KyzV0Xl`; principal ₱1,000.00; two payroll periods.

## Step results

| Step | Actor | Result |
|---|---|---|
| 1. Submit cash advance | Employee; HR Officer observer | **Passed.** UI creation, pending chain, queued Finance FYI recipients, no journal, and live HR list/detail passed. |
| 2. Find and approve first step | Department Head | **Passed.** The actor found the loan through the sidebar Approval Queue and approved through the UI. Approval and audit actor checks passed. |
| 3. Follow Finance hand-off and approve | Finance Officer | **Passed.** Finance followed the in-app notification and approved through its own session; state, approval/audit actor and audience assertions passed. |
| 4. Follow VP hand-off and approve | Vice President | **UI action and final server state passed.** The test saw `active`, all approval rows, a posted journal, and the HR detail update. The Employee page visibly showed Active without a reload, but the test locator filtered the row by a loan number that is not rendered in the self-service table. Final recipient, journal-posting actor, Mailpit, and diagnostics invariants were not reached. |

## Evidence and corrections

- [Sanitized trace, failure context, screenshots, walkthrough images, and journey context](assets/hr05-cash-advance-attempt-08/).
- The Employee page remained open from submission through final approval. Its failure screenshot shows the Cash Advance row with status Active. The test will identify the unique row in the Employee's **Active loans** region by the displayed type and assert its Active status.
- `JournalEntryService` sets `created_by` to null for source-linked entries and records the acting user as `posted_by`; the test will assert the source-linked posting actor accordingly.
- No application code changed. All transaction steps in this attempt were performed by the owning role through the UI; direct access was read-only verification only.
