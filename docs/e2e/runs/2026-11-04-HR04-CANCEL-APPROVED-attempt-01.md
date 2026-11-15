# HR04-CANCEL-APPROVED — first attempt counted a soft-deleted attendance row

**Date:** 2026-11-04 UTC  
**Environment:** Guarded `ogami-erp-e2e` project; fresh baseline and 37 isolated actors  
**Result:** **1 selected-role auth setup passed; the journey failed in its final read-only invariant** (journey 57.3 seconds; command 81.3 seconds)  
**Journey run ID:** `c7b7ddcd-b8f0-4ee1-9737-60fa3243d7df`  
**Created record:** `LR-202611-0001`, `pending_dept → pending_hr → approved → cancelled`

## Process walkthrough

1. **Employee — Profile → Leaves → New request:** submitted a one-day Vacation Leave request through the UI; it entered `pending_dept`.
2. **Department Head — Dashboard → Human Resources → Leave Management signal → Awaiting my action:** followed the badge and approved through the UI; status advanced to `pending_hr`.
3. **HR Officer — Dashboard → Human Resources → Leave Management signal → Awaiting my action:** followed HR's badge and approved through the UI; status became `approved`, one day was consumed, and an attendance row was marked `on_leave`.
4. **Employee — request detail → Cancel → confirmation:** cancelled through the UI. The API returned HTTP 200, the request became `cancelled`, the Employee cancellation actor/time were present, and balance assertions confirmed the day was restored. The journey then failed at the attendance-count assertion before its remaining invariants ran.

## Failure and root cause

- **Expected:** no active `on_leave` row remains for the cancelled request date.
- **Observed:** the read-only helper counted one row because its SQL included soft-deleted attendance rows. A separate read-only query confirmed the row had `deleted_at` set to the cancellation timestamp, with `remarks = leave:LR-202611-0001`; `LeaveRequestService::unmarkAttendance()` intentionally soft-deletes the exact leave-created row. The request was `cancelled`, balance was restored to used `0.0` / remaining `15.0`, and both approval rows remained approved by their respective actors.
- **Classification:** Test issue TST-HR-025, not an application defect. The helper must count only `attendance.deleted_at IS NULL` rows when asserting active attendance. This keeps the expected invariant unchanged: cancelled leave must not remain active in attendance.
- **Unverified in this attempt:** notification audience, Mailpit, sidebar badge counts, and live post-cancel observer assertions did not run because the `verify` phase stopped at the earlier attendance assertion. These remain required in the clean-baseline completion run.
- **Application code:** unchanged.

## Evidence

- [Failure screenshot](assets/hr04-cancel-approved-attempt-01/test-failed-1.png)
- [Department Head approval walkthrough](assets/hr04-cancel-approved-attempt-01/walkthrough/002-department-head-follows-the-badge-and-approves.png)
- [HR approval walkthrough](assets/hr04-cancel-approved-attempt-01/walkthrough/003-hr-follows-the-badge-and-approves.png)
- [Employee submission walkthrough](assets/hr04-cancel-approved-attempt-01/walkthrough/001-employee-files-a-vacation-leave-request.png)
- [Playwright failure context](assets/hr04-cancel-approved-attempt-01/error-context.md)
- [Journey context](assets/hr04-cancel-approved-attempt-01/journey-context.json)
- [Playwright process results](assets/hr04-cancel-approved-attempt-01/process-results.json)
- [Trace](assets/hr04-cancel-approved-attempt-01/trace.zip)

The attempt was not retried. A test-helper correction will be followed by a clean-baseline rerun; no assertion will be removed or weakened.
