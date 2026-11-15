# HR04-REJECT-HR — attempt 01 stopped at a collapsed sidebar hand-off

**Date:** 2026-11-04 UTC  
**Classification:** Test navigation issue (TST-HR-023)  
**Environment:** Guarded `ogami-erp-e2e` project; fresh baseline and 37 isolated actors  
**Result:** Selected-actor auth setup passed; the Employee step passed; the Department Head step failed before action; the HR step was blocked. No retry.  
**Journey run ID:** `f7a9de23-252a-4e85-b6af-84216a42269e`

## Outcome

Employee, Department Head, and HR Officer authenticated through the real login UI. Employee created `LR-202611-0001` through self-service; it entered `pending_dept`, the approval rows were pending, and the submission notification reached the eligible Department Head audience. The test stopped while looking for the nested Leave Management signal button. The Department Head dashboard showed the request in Pending Approvals and the Human Resources sidebar badge showed one item, but the section was collapsed. No approval or rejection action was submitted. HR rejection was blocked.

The remaining pending request existed only in the disposable E2E database and was removed by the fresh baseline before the next run.

## Evidence

- [Journey context](assets/hr04-reject-hr-attempt-01/journey-context.json)
- [Failure screenshot: collapsed Human Resources section and pending request](assets/hr04-reject-hr-attempt-01/test-failed-3.png)
- [Other failure screenshots](assets/hr04-reject-hr-attempt-01/)
- [Archived Playwright trace](assets/hr04-reject-hr-attempt-01/trace.zip)
- [Employee step screenshot](assets/hr04-reject-hr-attempt-01/walkthrough/001-employee-files-a-vacation-leave-request.png)

## Correction

The HR rejection branch searched for the sidebar signal before reopening the temporary Human Resources section. It now calls the existing helper that expands Human Resources, hovers Leave Management, follows the exact signal button and opens the actionable queue. The next run uses a freshly seeded baseline and continues to require the real badge-to-queue hand-off. No application code changed.
