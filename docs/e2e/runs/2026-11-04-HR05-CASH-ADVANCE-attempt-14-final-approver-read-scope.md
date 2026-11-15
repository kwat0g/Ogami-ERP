# HR05-CASH-ADVANCE — attempt 14: final approver read scope

**Result:** All four transactional steps completed through the owning role's UI. The final invariants then failed because the Vice President's open loan detail began receiving HTTP 403 after the final approval changed the loan to `active` (FND-HR-006). The cash-advance process state and journal completed; the journey is not counted fully exercised while the diagnostic failure remains.

- **Run ID:** `21bd631e-8d34-4a35-9fb9-6c5b96df6ae5`
- **Started:** 2026-11-04 22:26:49 UTC
- **Runtime:** 49.3 seconds for the Chromium journey; one worker, zero retries, headless Chromium.
- **Environment:** E2E flag and `ogami_e2e` database; browser base URL `https://nginx` is in the explicit allowlist. Because the normal Compose project was repeatedly terminated by the session supervisor, this invocation used temporary Compose metadata `ogami-erp-e2e-session` while reusing only the dedicated E2E network and named volumes. Playwright's environment guard passed. This workaround is recorded under ENV-E2E-002; the normal runner path remains affected.
- **Actors:** Employee, Department Head, Finance Officer, Vice President, and HR Officer in separate browser contexts using their own seeded UI-login sessions.
- **Run-scoped input:** `CA-202611-0001`, ₱1,000.00 cash advance, two repayment periods.

## Steps and outcomes

| Step | Actor | UI action | Result |
|---|---|---|---|
| 1 | Employee | Profile menu → Loans → Apply for a loan → Cash Advance; submit the request | HTTP 201; `pending`; three approval rows pending; no disbursement journal. Finance FYI reached both configured Finance accounts. HR's already-open loan list received the row and opened its detail. |
| 2 | Department Head | Sidebar → Approval Queue → My action required; find and approve the cash advance | First approval row became approved by Department Head; loan remained `pending`; Finance was the current step. |
| 3 | Finance Officer | Follow the in-app loan approval notification; approve | Second row became approved by Finance; loan remained `pending`; Vice President was the current step. |
| 4 | Vice President | Follow the in-app loan approval notification; approve | Third row became approved by Vice President; loan became `active`; disbursement journal was posted. Employee and HR observer views showed Active without reload. The Vice President's own detail then received repeated 403 responses and showed “Loan not found” with permission toasts. |

## Verified effects

- Read-only database verification found one active loan (`id=1`, `CA-202611-0001`), ₱1,000.00 principal, two periods remaining, and disbursement journal ID 1.
- The three ordered approval rows were `approved` by the Department Head, Finance Officer, and Vice President respectively. This observes the LoanStatus transition `pending → active` and each ApprovalRecord transition `pending → approved` through the UI-owned actions.
- Journal 1 was `posted`, with ₱1,000.00 total debit and credit and Vice President as `posted_by`.
- The final queued `loans.approved` notification reached only the Employee; the test found no `approval.completed` loan notice. Mailpit had no new participant email, consistent with the source's in-app-only hand-offs.
- The already-open Employee self-service row and HR detail showed Active without refresh. Both lacked further approval/withdraw actions.
- The final diagnostic invariant recorded four `GET /api/v1/loans/j16KyzV0Xl` responses with HTTP 403 in the Vice President context after that context's approval `PATCH` succeeded. Finance, Department Head, Employee, and HR request traces did not show this denial.
- No PDF or export is produced by this process path.

## Failure and source evidence

The page continued polling the same record after its own final approval. `LoanAccessPolicy::isGlobal()` includes `system_admin`, `finance_officer`, and `hr_officer`, but not `vice_president`; `isChainParticipant()` only grants non-owner access while the loan status is `pending`. Once the final approval changed the status to `active`, the detail request was denied even though the SPA route and API route are gated by the VP's `loans.view` permission. The backend denial is therefore inconsistent with the open UI route and the VP's own completed decision. Whether final approvers should retain historical read access is recorded as an owner question; the proposed default is read-only access to records they acted on.

The run remains failing and the assertion was not weakened. No application code was changed.

## Observed process

```mermaid
sequenceDiagram
    actor EMP as Employee
    actor DH as Department Head
    actor FIN as Finance Officer
    actor VP as Vice President
    participant HR as HR Observer
    participant UI as Loan detail UI
    participant API as Loan API
    participant DB as Read-only verifier
    EMP->>UI: Submit cash advance through Self-service Loans
    UI->>API: Create request
    API-->>UI: 201; pending
    DH->>UI: Find through Approval Queue; approve
    UI->>API: Approve current step
    API-->>UI: pending; Finance step
    FIN->>UI: Follow notification; approve
    UI->>API: Approve current step
    API-->>UI: pending; VP step
    VP->>UI: Follow notification; approve
    UI->>API: Approve final step
    API-->>UI: active; journal posted
    HR-->>HR: Active appears without refresh
    EMP-->>UI: Active appears without refresh
    UI->>API: Refresh VP loan detail
    API-->>UI: 403 after active transition
    DB-->>DB: Confirm approvals and balanced posted journal
```

The [sanitized trace, failure screenshots, journey context, and four-step walkthrough](assets/hr05-cash-advance-attempt-14/) contain the evidence. See [FND-HR-006](../FINDINGS.md#fnd-hr-006--final-loan-approver-loses-record-read-access-after-decision) and [attempt 13's environment interruption](2026-11-04-HR05-CASH-ADVANCE-attempt-13-playwright-interruption.md).
