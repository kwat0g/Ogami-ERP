# HR05-LOAN-UNDER-THRESHOLD — attempt 07: completed through Finance; VP skipped

**Result:** All four process steps passed from a fresh isolated baseline. Employee submitted a Company Loan of ₱29,999.99, then Department Head, HR Officer and Finance Officer approved through their own sessions. The VP approval row was `skipped` at submission because the principal is below the seeded ₱50,000 threshold. The loan reached `active`; Finance posted one balanced disbursement journal, and the Employee's already-open row updated without refresh. No product finding was observed.

- **Journey run ID:** `3e1ff9c1-c0be-4ad0-973d-73bca4aa8206`
- **Started:** 2026-11-04 23:46:57 UTC
- **Runtime:** 50.1 seconds for the selected Chromium journey; one worker, zero retries, headless Chromium. Auth setup passed separately; total Playwright result: 2 passed.
- **Environment:** E2E environment guard passed for `E2E_ENV=true`, database `ogami_e2e`, and allowlisted `https://nginx`. Fresh guarded baseline; 37 isolated actors seeded; five selected actors logged in through the real UI.
- **Actors:** Employee, Department Head, HR Officer, Finance Officer, Vice President. The Employee's own session remained open as the non-approver live observer.
- **Run-scoped request:** `LN-202611-0001`, ₱29,999.99, two pay periods, purpose `E2E-3e1ff9c1-c0b company loan below VP threshold`.

## Steps and outcomes

| Step | Actor | UI action | Result |
|---|---|---|---|
| 1 | Employee | Profile menu → Loans → Apply for a loan → Company Loan | HTTP 201; loan `pending`; Department Head, HR and Finance rows pending; VP row `skipped` at creation; no journal. Finance submission FYI reached both configured Finance accounts. No initial `approval.action_required` notice was created; the Department Head found work through Approval Queue. |
| 2 | Department Head | Sidebar → Approval Queue → My action required → loan → Approve | Passed. Department Head row approved by that actor; loan remained `pending`; HR became next and received the step-2 action notice. |
| 3 | HR Officer | Notification bell → step-2 approval notice → Approve | Passed. HR row approved by HR; loan remained `pending`; both eligible Finance accounts received the step-3 action notice. |
| 4 | Finance Officer | Notification bell → step-3 approval notice → Approve | Passed. Finance row approved by Finance; VP remained skipped; loan became `active`; one balanced journal posted by Finance; Employee received the outcome notice. |

## Verified effects

- Read-only database checks confirmed principal and balance `29999.99`, total paid `0.00`, two pay periods remaining, zero payments, and final status `active`.
- The four current approval rows were DH `approved`, HR `approved`, Finance `approved`, VP `skipped`; the three approved steps each recorded the expected approver and audit actor.
- Finance submission FYI recipients were exactly both Finance Officers. Step 2 action notice went only to HR; step 3 only to the Finance role audience; no VP action notice existed. The final `loans.approved` notice went only to Employee; no generic `approval.completed` notice was present. All loan links targeted the same detail.
- No participant email was delivered to Mailpit. No journal existed before Finance's final approval.
- Final disbursement journal was `posted`, `created_by` null, `posted_by` Finance Officer, and header/line debit equaled credit.
- The Employee self-service Company Loan row changed from Pending to Active in the already-open page without reload. The VP's Approval Queue contained no card for the skipped step.
- No PDF/export is produced by this loan path. The zero-interest seeded setting left balance equal to principal.
- Exact ₱50,000 and above-threshold amounts remain untested: the E2E Employee baseline earns ₱30,000/month and the company-loan cap is one monthly salary. The approval threshold is sourced from `WorkflowSeeder`; a higher-salary E2E reference employee is needed for those separate variants.

The [journey context and four annotated step screenshots](assets/hr05-loan-under-threshold-attempt-07/) show the walkthrough. No application code was changed.

## Observed process

```mermaid
sequenceDiagram
    actor EMP as Employee
    actor DH as Department Head
    actor HR as HR Officer
    actor FIN as Finance Officer
    actor VP as Vice President
    participant UI as Loan UI
    participant DB as Read-only verifier
    EMP->>UI: Submit ₱29,999.99 Company Loan
    UI-->>EMP: Pending; VP threshold step skipped
    DH->>UI: Find through Approval Queue; approve
    UI-->>HR: Step 2 action notice
    HR->>UI: Follow notice; approve
    UI-->>FIN: Step 3 action notice
    FIN->>UI: Follow notice; approve
    UI-->>EMP: Active; Finance posts balanced journal
    VP-->>VP: No pending task; approval row skipped
    EMP-->>UI: Open self-service row updates to Active
    DB-->>DB: Confirm approvals, audits, notices and journal
```
