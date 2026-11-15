# HR04-REJECT-DEPT — Department Head rejects a leave request

**Date:** 2026-11-04 UTC  
**Environment:** Guarded `ogami-erp-e2e` project; fresh baseline and 37 isolated actors  
**Result:** **2/2 Playwright tests passed** (selected-actor auth setup and one process journey); Employee, Department Head, and HR Officer authenticated through the real login UI  
**Journey duration:** 32.3 seconds; total command 46.8 seconds  
**Journey run ID:** `a6b2b3dc-1f0a-4851-a017-47855319ca80`  
**Created record:** `LR-202611-0001` (`pending_dept → rejected`)

## Process walkthrough

1. **Employee — Profile menu → Leaves → New request:** filed one future business day of Vacation Leave. The request entered `pending_dept`; both approval rows were pending, balance and attendance were unchanged, and the Employee had no Approve action.
2. **Department Head — Dashboard → Human Resources → Leave Management signal → Awaiting my action → Reject:** the dashboard and badge updated live. Department Head opened the unique queue item, entered the run-scoped rejection reason in “Reason for rejection,” and confirmed through the UI. The request moved to `rejected`; its Department Head approval row was `rejected` and its HR row was `skipped`. The request left the Department Head queue and the HR badge remained empty.

## Verified effects and invariants

- **Actor and reason:** read-only checks confirmed the exact rejection reason and the Department Head as the rejected approval-step actor/audit actor. The leave request's dedicated department approver field remained null on this rejection path; the approval ledger identifies the rejecting actor.
- **Balance and attendance:** used/remaining Vacation Leave were unchanged, and no `on_leave` attendance row was created.
- **Notifications:** only eligible Department Heads received `leave.submitted`; the Employee alone received `leave.rejected`; no `leave.pending_hr` was created. Both links referenced the request.
- **Employee view:** the open Employee detail updated to Rejected without refresh, displayed the rejection reason, and exposed neither Approve nor Cancel.
- **Email and diagnostics:** Mailpit IDs for intended recipients were unchanged. Employee, Department Head, and HR pages passed console, uncaught-exception, failed-request, and 5xx checks.

## Annotated screenshots

- [Employee files request](assets/hr04-reject-dept-full-run/walkthrough/001-employee-files-a-vacation-leave-request.png)
- [Department Head rejects from the badge queue](assets/hr04-reject-dept-full-run/walkthrough/002-department-head-rejects-through-the-leaves-badge.png)
- [Employee sees rejection and unchanged balance](assets/hr04-reject-dept-full-run/walkthrough/003-employee-sees-leave-rejection-reason-and-unchanged-balance.png)
- [Journey context](assets/hr04-reject-dept-full-run/journey-context.json)
- [Playwright results](assets/hr04-reject-dept-full-run/process-results.json)

## Process as observed

```mermaid
sequenceDiagram
    actor EMP as Employee
    actor DH as Department Head
    participant UI as OGAMI ERP UI
    participant DB as Leave workflow
    EMP->>UI: Profile → Leaves: submit one-day VL
    UI->>DB: Create leave request and two approval rows
    DB-->>DH: leave.submitted to eligible department audience
    DH->>UI: Follow sidebar badge and Awaiting my action
    DH->>UI: Enter rejection reason and confirm
    UI->>DB: Reject Department Head step
    DB-->>DB: status=rejected; HR step=skipped
    DB-->>EMP: leave.rejected; no balance or attendance change
    UI-->>EMP: Open detail updates live with reason
```

## Coverage and remaining work

This completes `HR04-REJECT-DEPT`. The HR rejection branch, insufficient balance, cancellation at eligible stages, invalid/self-approval actions, direct-API authorization checks, and payroll consumption remain planned. No application code changed.
