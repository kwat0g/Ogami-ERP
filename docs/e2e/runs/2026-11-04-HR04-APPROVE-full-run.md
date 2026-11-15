# HR04-APPROVE — Employee leave request through Department Head and HR

**Date:** 2026-11-04 UTC  
**Environment:** Guarded `ogami-erp-e2e` project; fresh baseline and 37 isolated actors  
**Result:** **2/2 Playwright tests passed** (selected-actor auth setup and one process journey); 3/3 acting roles authenticated through the real login UI  
**Journey duration:** 56.2 seconds; command duration about 1.2 minutes  
**Journey run ID:** `584c5c15-1964-4e33-baa4-f6333f45cf9b`  
**Created record:** `LR-202611-0001` (`pending_dept → pending_hr → approved`)

## Process walkthrough

All transactional actions used the UI, the owning actor's own browser context, and the documented order. The process started from a freshly seeded baseline with no existing leave requests and an opening 15.0-day Vacation Leave entitlement.

1. **Employee — Profile menu → Leaves → New request:** filed one future business day of Vacation Leave with a run-scoped reason. UI returned HTTP 201 and opened the generated detail. The request entered `pending_dept`; both approval rows were pending; used/remaining balance and attendance were unchanged. Employee had Cancel available and no Approve action.
2. **Department Head — Dashboard → Human Resources → Leave Management signal → Awaiting my action → Approve:** the pre-opened dashboard and sidebar signal updated without refresh. Department Head found the unique request through the signal dialog, saw `Pending dept` with enabled Approve/Reject actions, and approved it through the confirmation dialog. The request moved to `pending_hr`; the Department Head row left that actor's actionable queue. Read-only checks confirmed the Department Head approver and the HR pending approval row. The Employee's open detail updated to `Pending hr` without refresh; balance and attendance remained unchanged.
3. **HR Officer — Dashboard → Human Resources → Leave Management signal → Awaiting my action → Approve:** HR received the next actionable badge, followed the sidebar signal to the request, saw `Pending hr` with enabled Approve/Reject actions, and approved through the confirmation dialog. The final status was `approved`; HR was recorded as the final approver. The Employee's open detail updated without refresh and showed both approval steps complete.

## Verified effects and invariants

- **Approval history and audit:** the two current approval records became approved in order. Read-only assertions matched the Employee, Department Head, and HR Officer to their respective create/approval audit entries.
- **Leave balance:** one day was consumed after final HR approval. The Employee detail showed 14.0 / 15.0 Vacation Leave days remaining/entitled; before approval the balance was 15.0 / 15.0.
- **Attendance:** no `on_leave` attendance existed while pending; one `on_leave` attendance row existed after approval.
- **Notifications and recipients:** `leave.submitted` went only to the eligible Department Head audience for the Employee's department; `leave.pending_hr` went only to eligible HR approvers; `leave.approved` went only to the Employee. All notification links contained the request's public ID.
- **Badges and hand-offs:** Department Head and HR found work through their own live sidebar badge/signal and queue links; the completed actor's actionable count returned to zero.
- **Email:** Mailpit message IDs for all intended recipients were unchanged; this leave workflow sent no email in this run.
- **Financial/document effects:** no journal, payroll, PDF, or export was produced by this approval path. Payroll consumption remains a separate downstream process. Supporting-document upload was not exercised.
- **Diagnostics:** all actor pages passed console, uncaught-exception, failed-request, and HTTP 5xx checks. No retries or sleeps were used.

The approved detail still exposes a Cancel button. Source permits owner cancellation at `pending_dept`, `pending_hr`, and `approved`; the approved path contains balance/attendance reversal logic. Separate `HR04-CANCEL-PENDING-DEPT`, `HR04-CANCEL-PENDING-HR`, and `HR04-CANCEL-APPROVED` journeys remain unrun, so each stage's UI hand-off and side effects are still unverified. This is not recorded as a defect on the happy path.

## Annotated screenshots

- [Employee files request](assets/hr04-approve-full-run/walkthrough/001-employee-files-a-vacation-leave-request.png)
- [Department Head finds and approves](assets/hr04-approve-full-run/walkthrough/002-department-head-follows-the-leaves-badge-and-approves.png)
- [HR finds and approves](assets/hr04-approve-full-run/walkthrough/003-hr-follows-the-leaves-badge-and-approves.png)
- [Employee sees final state, balance, and approval chain](assets/hr04-approve-full-run/walkthrough/004-employee-sees-hr-approval-and-final-leave-effects.png)
- [Journey context](assets/hr04-approve-full-run/journey-context.json)
- [Playwright process results](assets/hr04-approve-full-run/process-results.json)

## Process as observed

```mermaid
sequenceDiagram
    actor EMP as Employee
    actor DH as Department Head
    actor HR as HR Officer
    participant UI as OGAMI ERP UI
    participant DB as Leave workflow
    EMP->>UI: Profile → Leaves: submit 1-day VL
    UI->>DB: Create LR-202611-0001
    DB-->>UI: pending_dept; notify eligible DH audience
    UI-->>DH: Dashboard/sidebar badge updates live
    DH->>UI: Follow Leave Management signal and approve
    UI->>DB: Approve department step
    DB-->>UI: pending_hr; notify eligible HR audience
    UI-->>EMP: Detail updates live; balance unchanged
    UI-->>HR: Dashboard/sidebar badge updates live
    HR->>UI: Follow Leave Management signal and approve
    UI->>DB: Approve HR step
    DB-->>UI: approved; consume 1 VL day; mark attendance on_leave
    UI-->>EMP: Final status, balance, and approval chain update live
```

## Coverage and remaining work

This run completes only the `HR04-APPROVE` happy-path variant. Department Head and HR rejection, insufficient-balance handling, cancellation at eligible stages, payroll consumption, invalid transitions, self-approval, and direct-API authorization checks remain planned. No application behavior changed.
