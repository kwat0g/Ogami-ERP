# HR04-CANCEL-APPROVED — notification listener completed after the immediate assertion

**Date:** 2026-11-04 UTC  
**Environment:** Guarded `ogami-erp-e2e` project; fresh baseline and 37 isolated actors  
**Result:** **1 selected-role auth setup passed; the journey failed at the first post-submission async invariant** (journey 26.8 seconds; command 45.7 seconds)  
**Journey run ID:** `cb1e3faa-4ef9-4fa4-a8e1-bff7e1fcd3f8`  
**Created record:** `LR-202611-0001`, `pending_dept`

## Process walkthrough

1. **Employee — Profile → Leaves → New request:** submitted a one-day Vacation Leave request through the UI. The UI returned HTTP 201 and the record persisted at `pending_dept`.
2. The first step's notification invariant immediately read the notification table and saw no `leave.submitted` row. The test failed there; Department Head approval, HR approval, and cancellation were blocked by the Process Runner. No retry or follow-up UI action occurred.

## Failure and root cause

- **Expected:** the queued `NotifyOnLeaveSubmitted` listener persists the exact eligible Department Head notice before the recipient assertion completes.
- **Observed:** the immediate read-only query returned no notice and failed the test. A later read-only query found the expected `leave.submitted` notification addressed to `e2e+department_head@ogami-e2e.test`. The queue log shows `NotifyOnLeaveSubmitted` starting and completing at 19:49:39 Asia/Manila, after the step had already failed at 11:49:38.419Z.
- **Classification:** Test issue TST-HR-026 (async checkpoint). The persisted row had the expected type and recipient; no missing or misdirected notification was found. The test asserted queue-backed work before its listener finished.
- **Correction:** `noticeTypes()` now uses bounded `expect.poll` on the read-only notification rows until the expected type set appears; it then runs the same exact recipient and link assertions. This is a condition-based queue checkpoint with no fixed sleep.
- **Application code:** unchanged.

## Evidence

- [Failure screenshot](assets/hr04-cancel-approved-attempt-02/test-failed-1.png)
- [Journey context](assets/hr04-cancel-approved-attempt-02/journey-context.json)
- [Playwright process results](assets/hr04-cancel-approved-attempt-02/process-results.json)
- [Trace](assets/hr04-cancel-approved-attempt-02/trace.zip)
- [Queue timing excerpt](assets/hr04-cancel-approved-attempt-02/queue-notification.log)
- [Read-only persisted notification snapshot](assets/hr04-cancel-approved-attempt-02/notification-state.json)
- [Prior approved-stage run stopped at the separate soft-delete query issue](2026-11-04-HR04-CANCEL-APPROVED-attempt-01.md)

No step was skipped or retried. Both HR04-CANCEL-APPROVED test-helper issues are now corrected; a fresh baseline and one complete end-to-end run are still required.
