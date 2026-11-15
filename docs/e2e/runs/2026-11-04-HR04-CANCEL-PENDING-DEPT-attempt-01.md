# HR04-CANCEL-PENDING-DEPT — cancellation returns HTTP 500

**Date:** 2026-11-04 UTC  
**Environment:** Guarded `ogami-erp-e2e` project; fresh baseline and isolated role accounts  
**Result:** **1 selected-role auth setup passed; the business journey failed at its final step** (47.9 seconds; command 68.6 seconds)  
**Journey run ID:** `e7219cf6-981a-404a-ba0c-47956c506495`  
**Created record:** `LR-202611-0001`, reason `E2E-e7219cf6-981 leave cancellation journey`  
**Actors:** Employee created and cancelled the request in the Employee session; Department Head found it through the live sidebar badge and opened it as an observer.

## Process walkthrough

1. **Employee — Profile → Leaves → New request:** submitted a one-day Vacation Leave request through the UI. The request entered `pending_dept` and the DB snapshot confirmed the approval rows were pending.
2. **Department Head — Dashboard → Human Resources → Leave Management signal → Awaiting my action:** followed the live badge and opened the pending request. The Department Head's approval action was available; no approval was taken.
3. **Employee — request detail → Cancel → confirmation:** confirmed cancellation through the UI. The cancel endpoint returned HTTP 500, so the journey stopped immediately and no later assertions or actions were run.

## Failure and verified state

- **Expected:** HTTP 200 and request transition `pending_dept → cancelled`; both current pending approval rows become superseded; no leave balance or attendance change.
- **Observed:** `PATCH /api/v1/leaves/requests/j16KyzV0Xl/cancel` returned HTTP 500. Laravel raised `Illuminate\Database\LazyLoadingViolationException` while a global `ApprovalRecord` observer tried to read the polymorphic `approvable` relation on a leave approval row. The stack reaches `LeaveRequestService.php:546` while superseding pending approval rows and `SalaryAdjustmentApprovalRecordRealtimeObserver.php:31` while reading `$record->approvable`.
- **Rollback check:** a read-only DB transaction found the request still `pending_dept`, with `cancelled_by` and `cancelled_at` null. Both approval rows remained `pending` and `is_current=true`; no matching cancellation notification existed. This confirms the failing cancellation transaction rolled back without partial cancellation state.
- **Root-cause evidence:** [captured API exception excerpt](assets/hr04-cancel-pending-dept-attempt-01/api-exception.log); observer registered globally in `api/app/Providers/AppServiceProvider.php:655`; leave service updates the approval records in `api/app/Modules/Leave/Services/LeaveRequestService.php:539-549`.
- **Classification / severity:** Defect, **High** — owner cancellation cannot complete while a leave request is pending. Finding [FND-HR-001](../FINDINGS.md#fnd-hr-001--leave-cancellation-500s-when-retiring-pending-approval-records), process gap `GAP-HR-04-01`.

## Evidence

- [Failure screenshot](assets/hr04-cancel-pending-dept-attempt-01/test-failed-1.png)
- [Department Head live-badge walkthrough](assets/hr04-cancel-pending-dept-attempt-01/walkthrough/002-department-head-follows-the-live-badge-and-opens-the-pending-request.png)
- [Employee submission walkthrough](assets/hr04-cancel-pending-dept-attempt-01/walkthrough/001-employee-files-a-vacation-leave-request.png)
- [Journey context](assets/hr04-cancel-pending-dept-attempt-01/journey-context.json)
- [Playwright process results](assets/hr04-cancel-pending-dept-attempt-01/process-results.json)
- [Trace](assets/hr04-cancel-pending-dept-attempt-01/trace.zip)

The cancellation assertion remains active and expects HTTP 200. No retry was made, the test was not weakened or skipped, and no application code was changed.
