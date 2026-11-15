# HR05-REJECT — attempt 03: Department Head rejection returns HTTP 500

**Result:** Employee submission, the Department Head Approval Queue hand-off, and the rejection form's required-reason validation passed through their respective UI sessions. The Department Head submitted a rejection with a reason, but `PATCH /api/v1/loans/{publicId}/reject` returned HTTP 500. The transaction rolled back, leaving the loan pending and all three approval records pending. The rejection variant is blocked by **FND-HR-007 / GAP-HR-05-02**.

- **Journey run ID:** `abd1a1b4-430e-48d8-a069-0a43c41df580`
- **Started:** 2026-11-04 23:02:37 UTC
- **Runtime:** 25.9 seconds for the selected Chromium journey; one worker, zero retries, headless Chromium.
- **Environment:** `E2E_ENV=true`, database `ogami_e2e`, browser base URL `https://nginx` passed the process environment guard. The dedicated volumes and network were used under temporary Compose metadata `ogami-erp-e2e-session` due ENV-E2E-002; this exception is recorded and is not a fix for the runner issue.
- **Actors:** Employee, Department Head, HR Officer observer, each using an isolated UI-login session.
- **Run-scoped transaction:** `CA-202611-0001`, ₱1,000 cash advance, purpose `E2E-abd1a1b4-430 cash advance rejection verification`.

## Steps and outcomes

| Step | Actor | UI action | Result |
|---|---|---|---|
| 1 | Employee | Profile menu → Loans → Apply for a loan → Cash Advance | Passed. UI returned HTTP 201; loan was `pending` with DH → Finance → VP approval rows pending and no disbursement journal. Finance submission FYI recipients and the HR observer's live record passed. |
| 2 | Department Head | Sidebar → Approval Queue → My action required → loan detail → Reject; enter reason and confirm | The role found the request through the real queue hand-off. The rejection form required a reason and its Confirm reject button changed from disabled to enabled after entry. The UI's PATCH returned HTTP 500; no decision was committed. |
| 3 | Employee/HR observer | Observe rejected status and outcome notices | Blocked by the HTTP 500. No rejection state or outcome notice was created. |

## Verified effects and failure evidence

- Read-only database verification found the loan still `pending`; its Department Head, Finance, and VP approval rows all remained `pending`, with no approver or rejection reason. No disbursement journal, `approval.rejected` / `loans.rejected` outcome notice, or Department Head approval-record audit row existed after the failed action.
- The API log records `Illuminate\\Database\\LazyLoadingViolationException`: lazy loading `ApprovalRecord.approvable` is disabled. Marking a later approval row as `skipped` invoked `SalaryAdjustmentApprovalRecordRealtimeObserver::updated()`, which calls `stage()` and reads `$record->approvable`. That observer is registered globally for `ApprovalRecord` in `AppServiceProvider`; the exception at `ApprovalService::reject()` line 211 aborted the transaction and rolled back the preceding rejection update. The same observer root cause is present in leave cancellation finding FND-HR-001.
- The API responded 500 rather than a handled business-rule response. No application code was changed and no API/database mutation was used to perform the process step.
- Mailpit email outcome, rejection notification recipients after a committed rejection, the live rejected status, and later-step skip behavior could not be verified because the rejection transaction failed before those effects.

Evidence: [sanitized trace, screenshots, saved context, and walkthrough](assets/hr05-reject-attempt-03/); [curated API exception excerpt](assets/hr05-reject-attempt-03/api-exception.log). Source: `api/app/Modules/HR/Observers/SalaryAdjustmentApprovalRecordRealtimeObserver.php:19-36`, `api/app/Providers/AppServiceProvider.php:659`, `api/app/Common/Services/ApprovalService.php:199-214`, and `api/app/Modules/Loans/Services/LoanService.php:326-353`.

## Observed process

```mermaid
sequenceDiagram
    actor EMP as Employee
    actor DH as Department Head
    participant HR as HR observer
    participant UI as Loan UI
    participant API as Loan API
    participant DB as Read-only verifier
    EMP->>UI: Submit cash advance through self-service
    UI->>API: Create loan
    API-->>UI: 201; pending; DH step
    HR-->>HR: Pending loan appears without refresh
    DH->>UI: Find loan in Approval Queue
    DH->>UI: Enter rejection reason and confirm
    UI->>API: PATCH reject
    API-->>UI: 500 LazyLoadingViolationException
    API->>DB: Transaction rolls back
    DB-->>DB: Loan and three approvals remain pending
```

The journey remains failing at the rejecting step. No test assertion was weakened and no application fix was made.
