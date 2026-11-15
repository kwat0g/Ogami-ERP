# HR04-REJECT-HR — HR rejects after Department Head approval

**Date:** 2026-11-04 UTC  
**Environment:** Guarded `ogami-erp-e2e` project; fresh baseline and 37 isolated actors  
**Result:** **2/2 Playwright tests passed** (selected-actor auth setup and one process journey); Employee, Department Head, and HR Officer authenticated through the real login UI  
**Journey duration:** 45.2 seconds; total command about 1 minute  
**Journey run ID:** `6b6bbb74-53fe-4f6a-8516-e50f09ef5964`  
**Created record:** `LR-202611-0001` (`pending_dept → pending_hr → rejected`)

## Process walkthrough

All transactional actions used the UI, each actor's own browser session, and the documented order. The journey started from a fresh E2E baseline with an opening Vacation Leave entitlement.

1. **Employee — Profile menu → Leaves → New request:** filed one future business day of Vacation Leave with a run-scoped reason. The UI returned HTTP 201. The request entered `pending_dept`, both approval rows were pending, and the Employee's balance and attendance were unchanged.
2. **Department Head — Dashboard → Human Resources → Leave Management signal → Awaiting my action → Approve:** reopened the sidebar section, followed its actionable signal and approved the request in the confirmation dialog. The request moved to `pending_hr`; the Department Head approval row recorded this actor as approved, and the request left the Department Head's actionable queue. The Employee's open detail updated live.
3. **HR Officer — Dashboard → Human Resources → Leave Management signal → Awaiting my action → Reject:** followed HR's own badge and queue, entered a run-scoped rejection reason and confirmed through the UI. The request moved to `rejected`; the HR approval row recorded the HR actor as rejected. The Employee's open detail updated live with the rejection and reason.

## Verified effects and invariants

- **Approval history and audit:** read-only checks confirmed the ordered Department Head approval and HR rejection rows, the Department Head approver field, and the HR rejection audit actor. The request's HR approver field remains null on this rejection path; the approval ledger and audit log identify the HR actor.
- **Balance and attendance:** Vacation Leave used/remaining values were unchanged, and no `on_leave` attendance row was created.
- **Notifications:** `leave.submitted` went only to eligible Department Heads; `leave.pending_hr` went only to eligible HR approvers; `leave.rejected` went only to the Employee. Links referenced the request public ID.
- **Available actions and badges:** Employee had no Approve or Cancel action after rejection. The request left the HR queue, and Department Head and HR actionable leave badge counts returned to zero.
- **Email and diagnostics:** Mailpit message IDs for intended recipients were unchanged. Employee, Department Head and HR pages passed console, uncaught-exception, failed-request and HTTP 5xx checks.
- **Documents and finance:** no PDF/export, payroll entry or journal is produced by this rejection path.

## Annotated screenshots

- [Employee files request](assets/hr04-reject-hr-full-run/walkthrough/001-employee-files-a-vacation-leave-request.png)
- [Department Head follows badge and approves](assets/hr04-reject-hr-full-run/walkthrough/002-department-head-follows-the-leaves-badge-and-approves.png)
- [HR follows badge and rejects](assets/hr04-reject-hr-full-run/walkthrough/003-hr-rejects-through-the-leaves-badge.png)
- [Employee sees rejection reason and unchanged balance](assets/hr04-reject-hr-full-run/walkthrough/004-employee-sees-leave-rejection-reason-and-unchanged-balance.png)
- [Journey context](assets/hr04-reject-hr-full-run/journey-context.json)
- [Playwright process results](assets/hr04-reject-hr-full-run/process-results.json)

## Process as observed

```mermaid
sequenceDiagram
    actor EMP as Employee
    actor DH as Department Head
    actor HR as HR Officer
    participant UI as OGAMI ERP UI
    participant DB as Leave workflow
    EMP->>UI: Profile → Leaves: submit one-day VL
    UI->>DB: Create request and two approval rows
    DB-->>DH: leave.submitted to eligible department audience
    DH->>UI: Follow sidebar signal and approve
    UI->>DB: Approve Department Head stage
    DB-->>HR: pending_hr; leave.pending_hr to eligible HR audience
    HR->>UI: Follow sidebar signal and reject with reason
    UI->>DB: Reject HR stage
    DB-->>DB: status=rejected; record HR approval and audit actor
    DB-->>EMP: leave.rejected; no balance or attendance change
    UI-->>EMP: Open detail updates live with reason
```

## Coverage and remaining work

This completes `HR04-REJECT-HR`. Insufficient balance, cancellation at eligible stages, self-approval, wrong-department authorization, invalid transitions, and payroll consumption remain planned. The earlier collapsed-sidebar test issue is documented in [attempt 01](2026-11-04-HR04-REJECT-HR-attempt-01.md) and resolved in E2E navigation. No application code changed.
