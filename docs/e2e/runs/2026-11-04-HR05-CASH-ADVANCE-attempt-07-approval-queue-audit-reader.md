# HR05-CASH-ADVANCE — attempt 07 (Approval Queue and approval audit)

- **Date:** 2026-11-04 UTC
- **Classification:** E2E test issue, TST-HR-060; no application finding assigned
- **Journey:** `HR05-CASH-ADVANCE`, seventh browser invocation
- **Actors:** Employee; Department Head; Finance Officer; Vice President; HR Officer observer, each in an isolated session
- **Command:** `E2E_AUTH_ACTORS=employee,department_head,finance_officer,vice_president,hr_officer node ../scripts/e2e/runner.mjs test-split --grep HR05-CASH-ADVANCE`
- **Result:** Fresh baseline, E2E typecheck, and all five real-UI sign-ins passed. Employee submitted cash advance `CA-202611-0001`. Step 1 passed: pending status, three ordered approval rows, no journal, exact queued Finance FYI recipients, and HR observer live list/detail update. The Department Head followed the in-app Approval Queue and approved through the UI. Read-only state confirmed the first approval row was `approved` by the seeded Department Head and the Finance/VP rows remained pending. The step then failed because the audit helper searched only `EmployeeLoan` audit rows and found no matching Department Head `updated` row.
- **Read-only follow-up:** `audit_logs` contains an `updated` row for the linked `App\Common\Models\ApprovalRecord`, attributed to `e2e+department_head@ogami-e2e.test` at the approval time. The application recorded the actor; the test reader excluded the relevant polymorphic model.
- **Run data:** Playwright context `e093fd97-bce1-4514-b876-c22a7efcc478`; loan `CA-202611-0001`; public loan ID `j16KyzV0Xl`; principal ₱1,000.00; two payroll periods.

## Step results

| Step | Actor | Result |
|---|---|---|
| 1. Submit cash advance | Employee; HR Officer observer | **Passed.** The Finance notice assertion waited for the queued delivery; exact two-recipient audience and observer update passed. |
| 2. Find and approve current step | Department Head | The queue hand-off, UI approval, pending status, ordered approval rows and approver identity passed. The step stopped at the audit reader's loan-only model filter. |
| 3. Follow Finance hand-off and approve | Finance Officer | **Blocked** by step 2 assertion failure. |
| 4. Follow VP hand-off and approve | Vice President | **Blocked** by step 2 assertion failure. |

The Approval Queue is a valid first-step hand-off even though no initial `approval.action_required` notification exists on the normal Department Head path. TST-HR-058 is resolved. The bounded wait for queued Finance notices passed on this fresh baseline, resolving TST-HR-059. TST-HR-060 updates audit verification to follow the linked ApprovalRecord.

## Evidence

- [Sanitized trace, failure context, six screenshots, and journey context](assets/hr05-cash-advance-attempt-07/).
- No application code changed. No Finance/VP approval, disbursement, payroll deduction, email, or final journal behavior was exercised.
