# HR05-LOAN-AT-THRESHOLD — attempt 01: final approver read scope

**Result:** All five transactional steps and their business assertions passed through the owning roles' UI sessions. The run then failed its clean-page diagnostic because the Vice President's still-open loan detail received repeated HTTP 403 responses after final approval. This is the existing FND-HR-006 read-scope finding; the variant is blocked at final diagnostics and is not counted as complete.

- **Journey run ID:** `96e0a953-d097-4bed-87cf-3d05f4702b5e`
- **Started:** 2026-11-05 00:10:03 UTC
- **Runtime:** 50.7 seconds for the Chromium journey; one worker, zero retries, headless Chromium. Six selected actors authenticated through the real login UI before the journey.
- **Environment:** E2E guard passed for the dedicated `ogami_e2e` database and allowlisted `https://nginx`. A fresh baseline was seeded; the guarded split runner authenticated actors, restarted only the isolated E2E services, then ran the single Chromium journey.
- **Actors:** Threshold Employee (`employee__loan_threshold`), Department Head, HR Officer, Finance Officer, and Vice President in separate browser contexts. The Employee page remained open as the second viewer.
- **Run-scoped request:** `LN-202611-0001`, `₱50,000.00`, two pay periods, purpose `E2E-96e0a953-d09 hr05-loan-at-threshold company loan`.

## Steps and outcomes

| Step | Actor | UI action | Result |
|---|---|---|---|
| 1 | Threshold Employee | Profile menu → Loans → Apply for a loan → Company Loan | HTTP 201; loan `pending`; Department Head, HR, Finance, and VP approval rows all pending; no journal. Finance submission FYI went to both Finance Officers. No step-1 action-required notice was sent. |
| 2 | Department Head | Sidebar → Approval Queue → My action required → loan → Approve | Passed; Department Head row approved by that actor. Loan remained pending; HR became next and received the step-2 notice. |
| 3 | HR Officer | Notification bell → step-2 approval notice → Approve | Passed; HR row approved by HR. Loan remained pending; both Finance Officers received the step-3 notice. |
| 4 | Finance Officer | Notification bell → step-3 approval notice → Approve | Passed; Finance row approved by Finance. Loan remained pending with VP current; no journal was posted yet. VP received the step-4 notice. |
| 5 | Vice President | Notification bell → step-4 approval notice → Approve | Passed; VP row approved by VP; loan became active and the VP posted the balanced disbursement journal. Employee's open self-service row updated to Active without refresh. The VP page then received repeated 403s while refreshing the completed loan detail. |

## Verified effects

- Read-only E2E database checks confirmed `LN-202611-0001` is an active `company_loan` for `50000.00`, requested by the dedicated threshold Employee, with journal ID 1.
- The current approval rows were Department Head, HR, Finance, and VP, each `approved` by the matching role account. The exact-threshold VP row remained pending until Finance approved, then the VP followed the real notification hand-off.
- Journal `JE-202611-0001` is `posted`, with Vice President as poster and ₱50,000.00 debit equal to ₱50,000.00 credit. The per-step assertions also confirmed no journal before the VP's final approval.
- Notification assertions passed for both Finance submission FYIs, no initial Department Head action notice, HR after Department Head, Finance after HR, VP after Finance, and the final `loans.approved` notice only to the Employee. No generic `approval.completed` notice or participant email was present. Loan links pointed to the same record.
- The Employee's already-open Company Loan row changed from Pending to Active without refresh. The final VP session raised five console errors for `GET /api/v1/loans/j16KyzV0Xl` with HTTP 403 and showed the permission error/Loan not found screen after its successful approval.
- No PDF/export is produced by this loan path.

The rejected final diagnostic is consistent with [FND-HR-006](../FINDINGS.md#fnd-hr-006--final-loan-approver-loses-record-read-access-after-decision): the VP is admitted to the route and has `loans.view`, but `LoanAccessPolicy` stops treating the actor as a chain participant after the loan becomes active. No application code or assertion was changed. The failure screenshots, sanitized Playwright trace, journey context, error context, and five step screenshots are in [the attempt artifact directory](assets/hr05-loan-at-threshold-attempt-01/). The trace redaction check found no remaining credential values.

## Observed process

```mermaid
sequenceDiagram
    actor EMP as Threshold Employee
    actor DH as Department Head
    actor HR as HR Officer
    actor FIN as Finance Officer
    actor VP as Vice President
    participant UI as Loan UI
    participant DB as Read-only verifier
    EMP->>UI: Submit exact-threshold ₱50,000 Company Loan
    UI-->>EMP: Pending; all four approval rows pending
    DH->>UI: Find through Approval Queue; approve
    UI-->>HR: Step 2 action notice
    HR->>UI: Follow notice; approve
    UI-->>FIN: Step 3 action notice
    FIN->>UI: Follow notice; approve
    UI-->>VP: Step 4 action notice
    VP->>UI: Follow notice; approve
    UI-->>EMP: Active; VP posts balanced journal
    EMP-->>UI: Open self-service row updates to Active
    UI->>UI: VP detail polling receives HTTP 403 after decision
    DB-->>DB: Confirm exact threshold, approvals, actors and journal
```
