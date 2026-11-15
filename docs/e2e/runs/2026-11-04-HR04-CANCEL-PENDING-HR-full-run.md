# HR04-CANCEL-PENDING-HR — Employee cancels after Department Head approval

**Date:** 2026-11-04 UTC  
**Environment:** Guarded `ogami-erp-e2e` project; fresh baseline and 37 isolated actors  
**Result:** **2/2 Playwright tests passed** (selected-actor auth setup and one process journey); Employee, Department Head, and HR Officer authenticated through the real login UI  
**Journey duration:** 51.2 seconds; total command 68.1 seconds  
**Journey run ID:** `0dbd82cf-f072-4bac-af98-bc71ca44a258`  
**Created record:** `LR-202611-0001`, `pending_dept → pending_hr → cancelled`

## Process walkthrough

All transaction changes used the SPA, each role's own session, and the source-defined order. This run started from a freshly migrated and seeded baseline.

1. **Employee — Profile → Leaves → New request:** submitted a one-day Vacation Leave request through the UI. It entered `pending_dept`; both approval rows were pending and the opening balance/attendance remained unchanged.
2. **Department Head — Dashboard → Human Resources → Leave Management signal → Awaiting my action:** followed the live signal, opened the request, and approved it. The request moved to `pending_hr`; the Department Head was recorded as approver and HR became the actionable role. The Employee's open detail updated live.
3. **HR Officer — Dashboard → Human Resources → Leave Management signal → Awaiting my action → request detail:** followed HR's own badge to the same record and left its decision pending. Approve and Reject were available to HR while the Employee still had Cancel.
4. **Employee — request detail → Cancel → confirmation:** cancelled the request through the confirmation dialog. The endpoint returned HTTP 200 and the request moved to `cancelled`. The Department Head's approval row remained approved/current; the pending HR row became superseded/non-current. Employee and HR open views updated to Cancelled without refresh.

## Verified effects and invariants

- **Status, actors and audit:** read-only DB assertions confirmed `pending_dept → pending_hr → cancelled`, the cancellation actor is the Employee, the Department Head is the approver, and the cancellation audit entry has the Employee actor. HR has no recorded approval.
- **Approval history:** step 1 remained `approved` by the Department Head; step 2 changed from `pending` to `superseded` with `is_current=false`. HR's Approve/Reject controls disappeared after cancellation.
- **Balance and attendance:** used/remaining Vacation Leave balance stayed at `0 / 15`; no `on_leave` attendance row was created for the requested date.
- **Notifications:** the only request notifications were `leave.submitted` to the eligible Department Head and `leave.pending_hr` to the eligible HR Officer, each linked to the request. No cancellation notice was emitted. The exact-recipient checks passed.
- **Badges, live updates, and email:** Employee and HR views updated without refresh; Department Head and HR actionable signal counts returned to zero. Mailpit IDs for intended recipients were unchanged. All three page diagnostic checks passed.
- **Documents and finance:** no PDF/export, payroll posting, or journal is produced by this pending-stage cancellation path.

## Annotated screenshots

- [Employee files the request](assets/hr04-cancel-pending-hr-full-run/walkthrough/001-employee-files-a-vacation-leave-request.png)
- [Department Head follows the badge and approves](assets/hr04-cancel-pending-hr-full-run/walkthrough/002-department-head-follows-the-badge-and-approves.png)
- [HR follows its badge and opens the pending request](assets/hr04-cancel-pending-hr-full-run/walkthrough/003-hr-follows-the-badge-and-opens-the-pending-request.png)
- [Employee cancels at pending HR](assets/hr04-cancel-pending-hr-full-run/walkthrough/004-employee-cancels-while-pending-hr.png)
- [Journey context](assets/hr04-cancel-pending-hr-full-run/journey-context.json)
- [Playwright process results](assets/hr04-cancel-pending-hr-full-run/process-results.json)

## Process as observed

```mermaid
sequenceDiagram
    actor EMP as Employee
    actor DH as Department Head
    actor HR as HR Officer
    participant UI as OGAMI ERP UI
    participant DB as Leave workflow
    EMP->>UI: Profile → Leaves: submit one-day VL
    UI->>DB: Create pending request and DH/HR approval rows
    DB-->>DH: leave.submitted to eligible Department Heads
    DH->>UI: Follow badge and approve
    UI->>DB: Advance pending_dept → pending_hr
    DB-->>HR: leave.pending_hr to eligible HR officers
    HR->>UI: Follow badge and open request; do not decide
    EMP->>UI: Confirm Cancel from own detail
    UI->>DB: Cancel while pending_hr
    DB-->>DB: pending_hr → cancelled; retire pending HR row
    DB-->>EMP: Employee detail updates live; no balance or attendance effect
    DB-->>HR: Open HR detail updates live; actionable badge clears
```

## Coverage and remaining work

This completes `HR04-CANCEL-PENDING-HR`. `HR04-CANCEL-PENDING-DEPT` reached the UI but failed with HTTP 500 under [FND-HR-001](../FINDINGS.md#fnd-hr-001--leave-cancellation-500s-when-retiring-pending-approval-records); its transaction rollback is documented in the [attempt report](2026-11-04-HR04-CANCEL-PENDING-DEPT-attempt-01.md). `HR04-CANCEL-APPROVED`, insufficient balance, self-approval, wrong-department authorization, invalid-transition API checks, and payroll consumption remain planned. No application code changed.
