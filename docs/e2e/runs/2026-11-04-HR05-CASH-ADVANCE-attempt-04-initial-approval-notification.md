# HR05-CASH-ADVANCE — attempt 04 (initial approval notice expectation)

- **Date:** 2026-11-04 UTC
- **Classification:** Test expectation issue under investigation (TST-HR-058); no application finding assigned yet
- **Journey:** `HR05-CASH-ADVANCE`, fourth browser invocation
- **Actors:** Employee, Department Head, Finance Officer, Vice President, HR Officer observer; separate authenticated sessions
- **Command:** `E2E_AUTH_ACTORS=employee,department_head,finance_officer,vice_president,hr_officer node ../scripts/e2e/runner.mjs test-split --grep HR05-CASH-ADVANCE`
- **Result:** Fresh isolated baseline, E2E typecheck, and all five real-UI logins passed. The Employee submitted cash advance `CA-202611-0001` through Self-service Loans. Read-only checks confirmed the pending loan, all three pending approval rows, no disbursement journal, and the HR observer's live row/detail update. The Finance submission FYI recipient assertion passed with both configured Finance Officers. The next assertion found no `approval.action_required` row for the Department Head; the test stopped before that actor opened a work queue, so the Approval Queue fallback was not yet evaluated. Steps 2–4 remain blocked in this invocation.

## Source review

`ApprovalService::NOTIFY_ON_SUBMIT` excludes `loan`, while `NOTIFY_HANDOFF` includes it. `LoanService::request()` calls `notifyNextStep()` only inside the branch that skips a Department Head when no eligible Department Head exists. The normal Department Head first-step path leaves the approval row pending without that call. When an approver later advances the chain, `ApprovalService::approve()` sends the next-step notice.

The top-level sidebar link “Approval Queue” opens `/approvals`; its board queries active approval rows. The next run follows this in-app link as the Department Head and checks whether the pending loan appears under “My action required.” Until that UI hand-off is checked, this absence is recorded as observed behavior rather than a confirmed process blocker. If the queue exposes the loan, the journey can proceed through the real inbox; if it does not, the step is a process-blocking defect.

## Steps and artifacts

| Step | Actor | Result |
|---|---|---|
| 1. Submit cash advance | Employee; HR Officer observer | **UI action and state checks passed.** Loan creation, `pending` status, three pending approval rows, zero journal rows, Finance role FYI, and observer update passed. The initial Department Head notification lookup returned no rows and stopped the test. |
| 2. Find and approve current step | Department Head | **Not run.** The UI Approval Queue fallback is pending attempt 05. |
| 3. Follow Finance hand-off and approve | Finance Officer | **Blocked.** |
| 4. Follow VP hand-off and approve | Vice President | **Blocked.** |

- **Run context:** `585b25f2-badd-463c-a785-8fb1ebf0bb45`; loan `CA-202611-0001`; request hash ID `j16KyzV0Xl`.
- **Artifacts:** [sanitized trace, failure context, screenshots, and journey context](assets/hr05-cash-advance-attempt-04/).
- The trace archive was scrubbed of credential-bearing headers and URL parameters before retention. No approval, payroll deduction, email, or final journal behavior was exercised. No application code changed.
