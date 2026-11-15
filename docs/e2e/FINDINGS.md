# E2E findings

Runtime findings are recorded here before any application fix. Severity follows the catalog scale. Findings remain open until the user approves the Phase 7 fix loop and the affected journey passes end to end.

## High

### FND-HR-001 — Leave cancellation 500s when retiring pending approval records

- **Classification:** Defect (transactional process failure)
- **Process / journey:** HR-04, `HR04-CANCEL-PENDING-DEPT`
- **Step / role:** Employee cancels an owned request at `pending_dept` / `employee`
- **Expected:** The UI cancellation request returns HTTP 200, changes the request to `cancelled`, retires its current pending approval rows, and leaves balance/attendance unchanged.
- **Observed:** The UI `PATCH /api/v1/leaves/requests/{publicId}/cancel` returned HTTP 500. `LeaveRequestService::cancel()` updated a pending `ApprovalRecord`; the globally registered `SalaryAdjustmentApprovalRecordRealtimeObserver` accessed `$record->approvable`, triggering `LazyLoadingViolationException` because the relation was not eager loaded. The request row and both approval rows remained unchanged after the exception; a read-only transaction found status `pending_dept`, null cancellation fields, and pending/current approval rows. No cancellation notification was present.
- **Impact:** An Employee cannot complete cancellation while a leave request is pending. The server transaction rolls back, but the process step is unavailable. The failing UI test remains active; no app fix or test skip was made.
- **Evidence:** [HR04 cancellation attempt report](runs/2026-11-04-HR04-CANCEL-PENDING-DEPT-attempt-01.md), [API exception excerpt](runs/assets/hr04-cancel-pending-dept-attempt-01/api-exception.log), [trace](runs/assets/hr04-cancel-pending-dept-attempt-01/trace.zip); `api/app/Modules/HR/Observers/SalaryAdjustmentApprovalRecordRealtimeObserver.php:31`, `api/app/Providers/AppServiceProvider.php:655`, and `api/app/Modules/Leave/Services/LeaveRequestService.php:539-549`.
- **Reproduction:** From a fresh E2E baseline, sign in as Employee, submit a leave request, then use its detail page's Cancel action before Department Head approval. The request fails with HTTP 500 while superseding approval rows.
- **Status:** Open. Phase 7 fix requires explicit approval and a clean-baseline rerun through the cancellation step.

### FND-HR-003 — Paired DTR CSV rejects a valid day-shift row

- **Classification:** Defect (attendance import process failure)
- **Process / journey:** HR-03, `HR03-CSV-VALIDATION`
- **Step / role:** Import biometric DTR / `hr_officer`
- **Expected:** A paired row for the baseline Employee, 2026-11-06, 06:00–14:00 Day Shift imports; the unknown employee row alone is skipped.
- **Observed:** The UI upload returned HTTP 200 with total 2, imported 0, skipped 2. The valid row was reported as `Time out (2026-11-05 14:00:00) must be after time in (2026-11-06 06:00:00).`; the unknown employee row was correctly reported. A read-only query found no attendance record for the Employee/date after the failed upload. The UI displayed two row errors.
- **Impact:** A normal paired day-shift DTR CSV row cannot complete this import journey. No attendance, audit, notification, or email side effect was created or verified. Other attendance entry paths were not part of this attempt.
- **Evidence:** [attempt report](runs/2026-11-04-HR03-CSV-VALIDATION-attempt-01.md), [journey context and failure evidence](runs/assets/hr03-csv-validation-attempt-01/); parser source `api/app/Modules/Attendance/Services/DTRImportService.php:90-109`; time-order validation `api/app/Modules/Attendance/Services/DTRComputationService.php:184-191`.
- **Reproduction:** From a fresh E2E baseline, sign in through the UI as HR Officer, open Human Resources → Attendance & DTR → Import DTR, upload a CSV row with the seeded Employee's number, `2026-11-06`, `06:00`, `14:00`, and import. The row is returned as skipped with the previous-day time-out error.
- **Status:** Open. The E2E journey remains failing at the import assertion; no retry, test weakening, or application fix was made. Severity is High because this paired CSV subprocess cannot complete for an ordinary day-shift row.

### FND-HR-004 — Employee training assignment returns HTTP 500

- **Classification:** Defect (training process failure)
- **Process / journey:** HR-07, `HR07-TRAINING-COMPLETE`
- **Step / role:** Assign an active training course to an Employee / `hr_officer`
- **Expected:** The UI assignment returns HTTP 201 and creates one `scheduled` employee-training record for the selected Employee and course.
- **Observed:** HR completed the assignment form through the Employee detail → Trainings tab and clicked the dialog's exact `Assign` button. The UI POST returned HTTP 500 with `MassAssignmentException: Add fillable property [status] to allow mass assignment on [App\\Modules\\HR\\Models\\EmployeeTraining]`. `EmployeeTrainingService::assign()` passes `status` to `EmployeeTraining::create()`, while `EmployeeTraining::$fillable` excludes it. A read-only query found zero matching assignment rows after the failed request, consistent with transaction rollback.
- **Impact:** HR cannot assign training through the UI. The Department Head's upcoming-training hand-off and HR's completion action cannot be reached, so no training status transition, notification, badge, or live observer update was verified.
- **Evidence:** [attempt 11 report](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-11.md), [trace and screenshots](runs/assets/hr07-training-complete-attempt-11/), `api/app/Modules/HR/Services/EmployeeTrainingService.php:48-56`, and `api/app/Modules/HR/Models/EmployeeTraining.php:24-37`.
- **Reproduction:** From a fresh isolated E2E baseline, sign in through the UI as HR Officer, create an active course under Human Resources → Training Programs, open Human Resources → Employees → the target employee → Trainings, select the course and date, and click Assign. The POST returns HTTP 500 and leaves no assignment row.
- **Status:** Open. Do not use an API/DB mutation to bypass the failure. After explicit Phase 7 approval and an application fix, rerun the clean-baseline journey through the Department Head hand-off and HR completion step.

### FND-HR-007 — Cash-advance rejection returns HTTP 500 while skipping later approvers

- **Classification:** Defect (approval process failure; High)
- **Process / journey:** HR-05, `HR05-REJECT`
- **Step / role:** Reject the pending cash advance / `department_head`
- **Expected:** Department Head's UI rejection returns HTTP 200, records the reason and rejecting actor, changes the loan to `rejected`, skips later Finance and VP approval rows, notifies the Employee, and creates no disbursement journal.
- **Observed:** Employee submission and the Department Head's real Approval Queue hand-off passed. The rejection modal required a reason; submitting it through the Department Head's UI returned HTTP 500. A read-only snapshot found the loan still `pending`, all three approval rows still `pending` with no approver/reason, no disbursement journal, no rejection outcome notifications, and no Department Head audit row.
- **Cause:** `ApprovalService::reject()` marks subsequent rows `skipped`. Updating one of those `ApprovalRecord` rows invokes `SalaryAdjustmentApprovalRecordRealtimeObserver`, globally registered for all `ApprovalRecord` models. The observer reads `$record->approvable` without eager loading; strict lazy-loading throws `LazyLoadingViolationException`. The transaction rolls back the preceding rejection update. This shares the observer root cause recorded in FND-HR-001.
- **Impact:** A Department Head cannot reject a cash advance. The rejection status transition, skipped-row changes, Employee outcome notices, live rejected state, and later-step suppression are not reached.
- **Evidence:** [attempt 03 report](runs/2026-11-04-HR05-REJECT-attempt-03-rejection-http-500.md), [sanitized trace and screenshots](runs/assets/hr05-reject-attempt-03/), [curated API exception](runs/assets/hr05-reject-attempt-03/api-exception.log); `api/app/Modules/HR/Observers/SalaryAdjustmentApprovalRecordRealtimeObserver.php:19-36`, `api/app/Providers/AppServiceProvider.php:659`, `api/app/Common/Services/ApprovalService.php:199-214`, and `api/app/Modules/Loans/Services/LoanService.php:326-353`.
- **Reproduction:** From a fresh E2E baseline, submit a cash advance through Employee self-service, find it through the Department Head's Approval Queue, enter a rejection reason, and confirm. The reject PATCH returns HTTP 500 and the database remains pending.
- **Status:** Open. The journey remains enabled and failing at the UI rejection. No app fix, API mutation, or assertion weakening was made. Phase 7 approval and an end-to-end rerun are required before marking fixed.

### FND-HR-008 — Manual employee-loan repayment has no Finance UI action

- **Classification:** Process gap (missing transactional UI step; High)
- **Process / journey:** HR-05, `HR05-PARTIAL-PAYMENT`
- **Step / role:** Record a partial manual repayment / `finance_officer`
- **Expected:** Finance opens an active loan from Employee Loans, records a positive manual payment within the outstanding balance, and sees the payment ledger, reduced balance, unchanged `active` status for a partial payment, and a balanced cash/receivable journal. A later settlement should reach `paid`.
- **Observed:** The fresh-baseline journey created the company loan as Employee, then approved it through Department Head, HR Officer and Finance Officer UI sessions. Finance navigated through the sidebar to the active loan detail. It showed total paid ₱0.00 and balance ₱29,999.99, but exposed no “Record payment” or “Make payment” action; its payment panel said “No payments yet.” The journey stopped with `test.fixme` / GAP-HR-05-03. Read-only checks confirmed one posted balanced disbursement journal, no payment rows and no payment total. No repayment request was sent.
- **Source:** `api/app/Modules/Loans/routes.php` exposes `POST /loans/{loan}/payments` with `loans.write_off`; `LoanController::recordPayment()` validates and calls `LoanService::recordPayment()`, which records the immutable payment, reconciles loan totals/status and posts the configured manual-repayment journal. `spa/src/pages/loans/detail.tsx` only renders payment history and exposes no payment mutation. Payroll deductions are a separate HR-06 process.
- **Impact:** The manual partial-repayment variant cannot proceed through the required user interface. The payment ledger, manual repayment journal, partial balance reduction, `active → paid`, and final settlement behavior remain unverified. This finding does not assert that the separate payroll deduction path is missing.
- **Evidence:** [attempt 02 report and six annotated screenshots](runs/2026-11-05-HR05-PARTIAL-PAYMENT-attempt-02-blocked-manual-repayment-ui.md); [journey context](runs/assets/hr05-partial-payment-attempt-02/journey-context.json); `api/app/Modules/Loans/routes.php:25`, `api/app/Modules/Loans/Controllers/LoanController.php:160-182`, `api/app/Modules/Loans/Services/LoanService.php:517-590,729-749`, and `spa/src/pages/loans/detail.tsx:164-193`.
- **Reproduction:** From a fresh E2E baseline, submit a below-threshold company loan as Employee, approve it through its UI chain, then sign in as Finance, navigate Sidebar → Human Resources → Employee Loans, and open the active loan. The detail shows payment history but no manual repayment control.
- **Status:** Open. Do not call the API to create a repayment for E2E. After explicit Phase 7 approval and a UI implementation, rerun the journey through partial and final settlement and verify ledger, balance, journal, audit, notifications, and live observers.


### FND-HR-009 — Payroll Compute is advertised by API but missing from the UI

- **Classification:** Defect (core payroll process cannot reliably advance; High)
- **Process / journey:** HR-06, `HR06-HAPPY`
- **Step / role:** Compute a UI-created Draft payroll period / `hr_officer`
- **Expected:** The authorized HR maker sees an enabled Compute action on the Draft period, submits through the UI, and the period advances to `processing`; the queued computation then reaches `computed` with payroll rows and a Finance approval hand-off.
- **Observed:** In the retained fresh-baseline run, HR created a Production-scoped period through Payroll Processing. The UI's detail GET returned HTTP 200 with `available_actions.compute=true` and `permissions.compute=true`; read-only state showed `draft`, HR as creator, four eligible employees, and no payroll rows. The rendered page showed Draft and “No payroll rows yet,” but no Compute button. The journey stopped at step 2; Finance/finalization/disbursement/payslip steps did not run. A prior partial execution had shown Compute and queued through `processing → computed`, so this is inconsistent behavior and is not retried away.
- **Impact:** HR06-HAPPY cannot reliably continue from Draft. No approval, GL, bank file, disbursement, employee notice or payslip was created or verified in the retained attempt.
- **Evidence:** [attempt report and sanitized screenshot/trace](runs/2026-11-05-HR06-HAPPY-attempt-01-blocked-compute-action.md); test context `docs/e2e/runs/assets/hr06-happy-attempt-01/journey-context.json`; read-only detail response for run-scoped period `j16KyzV0Xl`. SPA journey is `spa/e2e-process/hr-payroll.process.spec.ts`.
- **Reproduction:** From a fresh guarded E2E baseline, sign in as HR Officer, navigate Human Resources → Payroll Processing → New Period, create a Production-scoped period, then open the period detail. Compare the detail response's `available_actions.compute` with the visible enabled buttons. The retained attempt had the action in the response and no button in the page.
- **Status:** Open. HR06 marks the blocked step with `test.fixme` / GAP-HR-06-01. No app fix or test retry was made.

### FND-PROD-001 — Stock-adjustment confirmation click sends no request

- **Classification:** Defect (UI transaction cannot be submitted; High)
- **Process / journey:** PROD-01, `PROD01-HAPPY`
- **Step / role:** Submit the inbound RM-001 opening-stock adjustment / `warehouse_staff`
- **Expected:** After Warehouse reviews and confirms a valid inbound adjustment through the UI, the page sends the create request, records the adjustment, posts its stock movement when below threshold, and navigates to the movement view.
- **Observed:** Attempt 24 completed the accessible Location selection with a real touchscreen tap and the selected-value assertion passed. The review button opened the confirmation dialog. Playwright's exact “Submit adjustment” click completed, but the dialog remained open and the expected navigation did not occur. The trace contains no stock-adjustment create request. A read-only query against the dedicated E2E database found no adjustment matching the run-scoped reason and RM-001 on-hand remained 0.000. No transaction was created.
- **Impact:** The production journey cannot provide its first raw-material stock through the Warehouse UI, so the remaining 19 steps are blocked. The frontend cause, backend action availability, and any notification/audit behavior remain unverified because the request was never sent.
- **Evidence:** [Attempt 24 report, trace, context and screenshots](runs/2026-11-05-PROD01-HAPPY-attempt-24-submit-no-request.md); `spa/src/pages/inventory/stock-adjustments/create.tsx` (confirmation callback and mutation); `spa/src/components/ui/ConfirmDialog.tsx`.
- **Reproduction:** From a fresh guarded E2E baseline, sign in as Warehouse Staff, navigate through the sidebar to Stock Adjustments, select active RM-001 and Main Warehouse, enter a valid 200.095 kg inbound adjustment with a run-scoped audit reason, review it, and click “Submit adjustment.” In attempt 24 the dialog remained open, no create request was issued, and no row or stock movement was persisted.
- **Status:** Open. No application change or retry was made. Root-cause and fix require the approved Phase 7 loop; rerun the full journey from a fresh baseline after the fix.

## Medium

### FND-HR-002 — Employee DTR did not refresh after HR attendance correction

- **Classification:** Defect (real-time update / stale record)
- **Process / journey:** HR-03, HR03-MISSING-PUNCH
- **Step / role:** Observe the corrected record in the already-open Employee DTR / Employee
- **Expected:** After HR saves a valid time-out correction, the open Employee DTR refreshes and displays corrected time and hours without manual reload.
- **Observed before fix:** HR's successful correction persisted and cleared the Missing time-out list, while the already-open Employee DTR retained blank time-out/hours. The original trace showed the stale row for eight seconds.
- **Shared-workspace change observed:** The self-service DTR query prefix was added to the central `attendance_changed` invalidation map in the shared worktree, and a red-first map regression is reported there. This session did not make or approve that application change. Phase 7 approval has not been given.
- **Verification:** Attempt 08 passed in separate Employee and HR browser contexts. The open Employee DTR displayed 14:00 and 7.5 hours without reload, then retained the correction after reload. The E2E outbox had one published AttendanceChanged create and one published update; the dedicated worker completed both. Step-bound interval was 1.654 seconds in one sample, not p95 or exact event-to-render latency; no raw browser WebSocket frame was archived. See [attempt 08](runs/2026-11-04-HR03-MISSING-PUNCH-attempt-08.md) and [screenshots/timeline](runs/assets/hr03-missing-punch-attempt-08/).
- **Earlier attempts:** Attempt 07 reached and passed the live DTR assertion but failed a global Mailpit ID invariant on 12 unrelated messages to other recipients; the reload step was blocked. See [attempt 07](runs/2026-11-04-HR03-MISSING-PUNCH-attempt-07.md).
- **Status:** Open pending explicit user approval of the Phase 7 fix loop. The changed worktree passed the listed Employee DTR/HR-list path; other Attendance surfaces and module-wide delivery remain unverified.

### FND-HR-005 — Interview email omits the Manila timezone label

- **Classification:** Defect (notification content; Medium)
- **Process / journey:** HR-02, `HR02-HAPPY`
- **Step / role:** Schedule interview and advance application to Interview / HR Officer
- **Expected:** The candidate interview email identifies the scheduled time as Asia/Manila, as assembled by `InterviewScheduledMail::content()` and required by the journey invariant.
- **Observed:** The HR UI action advanced the application to `interview`, the System Admin observer updated without refresh, and one interview row was created by HR Officer with local stored value `2026-11-06 20:23:00`. The candidate's exact-recipient email showed `November 6, 2026 8:23 PM` and all other expected interview details, but no `(Asia/Manila)` label. API config reports `app.timezone = Asia/Manila`.
- **Cause:** `InterviewScheduledMail` exposes a public `$scheduledAt` property formatted without a timezone label, then passes a labeled value with the same key in `Content::with`. Laravel's `Mailable::buildViewData()` merges initialized public properties after view data, so the public property overrides the labeled value used by the email template.
- **Impact:** Candidates receive interview details without an explicit timezone. The displayed local date and time matched the stored Manila-local schedule; this finding does not show that the instant or local time was wrong. The journey stops at the email-content invariant, blocking interview outcome, Offer, Hired, and public tracking.
- **Evidence:** [attempt 13 report, trace, failure context, and screenshots](runs/2026-11-04-HR02-HAPPY-attempt-13-interview-timezone-label.md); [archived artifacts](runs/assets/hr02-happy-attempt-13-interview-timezone-label/); `api/app/Modules/HR/Mail/InterviewScheduledMail.php:23,43-45,66-78`; `api/vendor/laravel/framework/src/Illuminate/Mail/Mailable.php:353-367`; `api/resources/views/emails/recruitment/interview-scheduled.blade.php:9`.
- **Reproduction:** From a fresh E2E baseline, create and publish a posting as HR Officer, apply as a public candidate, follow the HR notification and System Admin observer hand-off, advance to Screening, then schedule the interview through the HR UI. Inspect the candidate's new interview email in Mailpit; its Date and time omits the timezone label.
- **Status:** Open. Do not change the email or weaken the assertion before explicit Phase 7 approval; the journey must pass through interview scheduling after an approved fix.

### FND-HR-006 — Final loan approver loses record read access after decision

- **Classification:** RBAC issue (over-restrictive post-decision read scope; Medium)
- **Process / journey:** HR-05, `HR05-CASH-ADVANCE`; now also reproduced in `HR05-LOAN-AT-THRESHOLD`
- **Step / role:** Refresh a completed loan detail after final approval / `vice_president`
- **Expected:** After the Vice President's own approval changes the loan to `active`, the still-open detail should show the resulting state without a permission error. The route and GET endpoint both accept `loans.view`; the business rule for historical read access needs owner confirmation.
- **Observed:** The VP's cash-advance UI approval returned HTTP 200; all three approval rows became approved, the loan became active, and its ₱1,000 journal posted. The same behavior reproduced after a successful exact-threshold ₱50,000 company-loan VP approval: all four approvals completed, journal `JE-202611-0001` posted by VP, then the VP's open page received repeated `GET /api/v1/loans/{publicId}` HTTP 403 responses and showed “Loan not found” with permission toasts. Employee views showed Active without refresh; their detail requests were not denied.
- **Cause:** `LoanAccessPolicy::isGlobal()` lists `system_admin`, `finance_officer`, and `hr_officer` but excludes `vice_president`. The VP can read the record as a current chain participant only while its status is `pending`; `isChainParticipant()` returns false as soon as the final approval changes the status to `active`. The SPA `/hr/loans/:id` guard and API GET middleware still admit the VP's `loans.view` permission, so the open page keeps polling a resource the row-scope policy now denies.
- **Impact:** The business transaction completes, but its final approver loses the page state immediately after acting and sees repeated errors instead of the completed result. This is over-restriction; no unauthorized read or write was observed. Proposed default: keep read-only access for users who acted on the record, without granting new approval or cancellation actions.
- **Evidence:** [cash-advance attempt 14 report and artifacts](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-14-final-approver-read-scope.md), [exact-threshold report and sanitized artifacts](runs/2026-11-05-HR05-LOAN-AT-THRESHOLD-attempt-01-final-approver-read-scope.md), and [VP failure screenshot](runs/assets/hr05-loan-at-threshold-attempt-01/test-failed-2.png). Source: `api/app/Modules/Loans/Policies/LoanAccessPolicy.php:105-110,160-172,211-216`, `api/app/Modules/Loans/routes.php:19`, `spa/src/routes/hrRoutes.tsx:171-186`, and the seeded VP permission in `api/database/seeders/RolePermissionSeeder.php`.
- **Reproduction:** From a fresh E2E baseline, submit a cash advance as Employee, approve it through Department Head and Finance UI sessions, then approve as Vice President from the in-app notification. After the API reports success, observe the open VP detail's repeated loan GET responses.
- **Status:** Open pending confirmation of post-decision read scope and explicit Phase 7 approval. No app change or assertion weakening was made.

### FND-MRP-001 — MRP completion notification is suppressed by subject access registry

- **Classification:** Defect (notification side effect / hand-off)
- **Process / journey:** MRP-01, `MRP01-SHORTAGE`
- **Step / role:** Follow automatic MRP completion notice / `ppc_head`
- **Expected:** After an automatic MRP run completes, the notification service persists a `mrp_run_completed` notice for each eligible active PPC/Sales recipient who has both `mrp.plans.view` and `mrp.runs.view`; PPC follows its link to the run's plans.
- **Observed:** Sales created and confirmed `SO-202611-0001` through the UI. The queue completed run 1, created active plan `MRP-202611-0001` with 3 shortages, one draft auto-generated `PR-202611-0001` assigned to PPC, and one planned work order. PPC's UI inbox did not show “MRP planning completed”; a read-only query found zero matching `mrp_run_completed` rows. The queue log states `Notification delivery skipped: subject has no registered access rule` for subject `App\\Modules\\MRP\\Models\\MrpRun`, id 1. The PPC role has both required view permissions. `userCanViewRecord()` contains a `MrpRun` rule, but `supports()` does not include `MrpRun::class`, so `sendForSubject()` returns before evaluating that rule. The generic sidebar showed one active MRP plan, but the journey did not use it as a substitute for the planned notification hand-off.
- **Impact:** The run, shortage plan, auto-PR and planned WO are created, but the targeted MRP result notice and linked hand-off are missing. The journey stops at PPC's notification step; Purchasing did not inspect or submit the PR. No application fix was made.
- **Evidence:** [MRP-01 first-run report](runs/2026-11-04-MRP01-SHORTAGE-first-run.md), archived [suppression log](runs/assets/mrp01-shortage-first-run/notification-suppression.log), [journey trace and screenshots](runs/assets/mrp01-shortage-first-run/); `api/app/Modules/MRP/Services/MrpAutomationService.php` calls `sendForSubject()`, and `api/app/Common/Support/NotificationSubjectAccessRegistry.php` omits `MrpRun` from `supports()` despite having a `MrpRun` `userCanViewRecord()` match.
- **Reproduction:** In the isolated E2E environment, create and confirm a shortage-producing SO as `sales_officer`, wait for its automatic MRP run, then open Notifications as `ppc_head`. The run/plan/PR/WO persist, but the expected linked notice is absent and the queue log records suppression.
- **Status:** Open. Phase 7 fix requires explicit approval and a clean-baseline end-to-end rerun.

### FND-PR-001 — PR submission creates no approval notification

- **Classification:** Defect (notification side effect / hand-off)
- **Process / journey:** PR-01, `PR01-DIRECT-PO-UNDER-THRESHOLD`
- **Step / role:** Submit purchase request / `department_head__hr`
- **Expected:** The active Finance approval step creates an `approval.action_required` in-app notice for eligible Finance approvers; no unrelated recipient receives it.
- **Observed:** The UI submission succeeded and the PR reached `pending`. The approval row was pending for `finance_officer`; the two Finance E2E accounts held `purchasing.pr.approve`. The read-only DB assertion found zero matching notification rows. The journey correctly stopped at this invariant, so the Finance hand-off was not attempted.
- **Evidence:** [PR-01 first-attempt report](runs/2026-11-03-PR01-first-attempt.md); `api/app/Modules/Purchasing/Services/PurchaseRequestService.php:444` calls `ApprovalService::submit(..., 'purchase_request', ...)`, while `api/app/Common/Services/ApprovalService.php:24,123-127` only sends on-submit notices when the workflow key is `pr`. E2E run `cab24dab-f92e-467c-9434-8531b75e5cb0`, record `PR-202611-0001`.
- **Impact:** Approval work may appear in the approval queue, but the expected targeted notification hand-off is absent. Queue discoverability was not tested because the notification invariant failed first.
- **Reproduction:** In the isolated E2E environment, create a PR through the UI as `department_head__hr`, submit it, then query read-only for `notifications.type = 'approval.action_required'` whose message contains the generated PR number. Observed zero rows while `approval_records` contains a pending Finance step.
- **Status:** Open. No application code changed. Fix requires explicit Phase 7 approval and an end-to-end rerun.

## High process gaps

### GAP-HR-08-02 — Separation cancellation is unavailable in the SPA

- **Classification:** Process gap (missing transactional UI action; High)
- **Process / journey:** HR-08, `HR08-CANCEL-REOPEN`
- **Step / role:** Cancel a pending or in-progress clearance and re-initiate through the Employee UI / `hr_officer`
- **Observed in source:** `SeparationService::cancel()` accepts pending/in-progress clearances and restores the employee to the pre-initiation status. The authenticated `PATCH /hr/clearances/{clearance}/cancel` route requires `hr.separation.cancel`, and `ClearanceResource` includes `cancel` in `available_actions` for an eligible HR actor. The SPA's `separationsApi` has no cancel method and the separation detail page renders no Cancel control. The UI journey is therefore blocked despite the backend capability.
- **Impact:** An HR user cannot reverse a rescinded or mistyped separation through the application and may leave an employee in `on_leave`. No positive API call may substitute for the missing UI action.
- **Evidence:** `api/app/Modules/HR/Services/SeparationService.php:673-760`; `api/app/Modules/HR/Controllers/SeparationController.php:88-95`; `api/app/Modules/HR/Resources/ClearanceResource.php:48-53`; `api/app/Modules/HR/routes.php:282`; `spa/src/api/separations.ts`; `spa/src/pages/hr/separations/detail.tsx`.
- **Reproduction:** From a fresh isolated baseline, start a separation through HR → Employees → Separate, then open it through the Separation Clearance sidebar. Compare the HR-visible buttons with the read-only clearance resource's `available_actions`; the source advertises `cancel`, but the SPA renders no cancellation button.
- **Status:** Open; source-confirmed gap and test authored, runtime confirmation pending. `HR08-CANCEL-REOPEN` records a missing-UI-action step with `test.fixme` and does not invoke the cancel endpoint. It has not run while the isolated-stack guard waits for the existing Compose project to clear. After approved Phase 7 UI work, rerun cancellation, employee restoration, audit/history, reservation release, notification, and re-initiation assertions from a clean baseline.

### GAP-HR-03-02 — Paired CSV DTR import rejects a valid day-shift row

- **Classification:** Broken process step (High; FND-HR-003)
- **Process / journey:** HR-03, `HR03-CSV-VALIDATION`
- **Observed:** The UI import reports a correctly identified 06:00–14:00 day-shift row as an inverted range and imports zero rows. The dependent Employee live-update and reload steps are blocked.
- **Evidence:** [attempt report and trace](runs/2026-11-04-HR03-CSV-VALIDATION-attempt-01.md); parser and validation references are listed under FND-HR-003.
- **Status:** Open. No test shortcut or application fix was made.

### GAP-HR-07-01 — Training assignment fails before scheduled state

- **Classification:** Broken process step (High; FND-HR-004)
- **Process / journey:** HR-07, `HR07-TRAINING-COMPLETE`
- **Observed:** HR creates the course through the UI, but assignment through Employee detail → Trainings returns HTTP 500. `EmployeeTrainingService::assign()` mass-assigns `status`, while `EmployeeTraining::$fillable` excludes it. Read-only verification found no assignment row after the failed transaction; the Department Head hand-off and HR completion are blocked.
- **Evidence:** [attempt 11 report](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-11.md), [trace and screenshots](runs/assets/hr07-training-complete-attempt-11/), and the service/model references listed under FND-HR-004.
- **Status:** Open. After explicit Phase 7 approval and an app fix, rerun from a fresh baseline through assignment, the intended hand-off, HR completion, audit/badge/notification assertions, and the observer update. No API/database shortcut is allowed.

### GAP-HR-05-02 — Cash-advance rejection fails while skipping later approval rows

- **Classification:** Broken process step (High; FND-HR-007)
- **Process / journey:** HR-05, `HR05-REJECT`
- **Observed:** Department Head's UI reject action returns HTTP 500. A salary-adjustment approval observer lazy-loads `ApprovalRecord.approvable` while `ApprovalService::reject()` marks the later Finance/VP rows skipped. The transaction rolls back; the loan and all approval rows remain pending. The outcome-notice and post-rejection observer steps are blocked.
- **Evidence:** [attempt 03 report and artifacts](runs/2026-11-04-HR05-REJECT-attempt-03-rejection-http-500.md); API excerpt and source references are listed under [FND-HR-007](FINDINGS.md#fnd-hr-007--cash-advance-rejection-returns-http-500-while-skipping-later-approvers). Related generic observer failure in leave cancellation is [FND-HR-001](FINDINGS.md#fnd-hr-001--leave-cancellation-500s-when-retiring-pending-approval-records).
- **Status:** Open. Preserve the failing UI test; after Phase 7 approval and a fix, rerun from fresh baseline and verify rejected status, reason/actor, skipped later rows, exact Employee notices, no journal, audit, and live Employee/HR views.

## Medium process exemptions and owner decisions

### GAP-HR-07-02 — Performance-review variant points to an intentionally removed feature

- **Classification:** Source-confirmed feature retirement / owner decision (not a runtime defect)
- **Process / journey:** HR-07, catalog variant `HR07-REVIEW`
- **Observed:** Migration `api/database/migrations/0457_drop_performance_review_tables.php` explicitly describes a 2026-08-07 scope cut and drops `review_cycles`, `performance_reviews`, and `review_templates`. It also states that models, service, controller, routes, SPA pages, and permissions were deleted in the same change. Current `spa/src/routes/hrRoutes.tsx` has an empty Performance Reviews route group. `ReviewStatus` remains as an orphaned enum; it does not establish a current process or reachable transitions.
- **Impact:** No current review journey can be performed through the UI or verified against an active review persistence model. The feature is not treated as a product defect because source explicitly records the scope cut.
- **Evidence:** `api/database/migrations/0457_drop_performance_review_tables.php`, `api/app/Modules/HR/Enums/ReviewStatus.php`, and `spa/src/routes/hrRoutes.tsx` (empty Performance Reviews route group).
- **Status:** Pending owner confirmation that performance reviews remain out of scope. Until then, retain `HR07-REVIEW` as an explicit source-confirmed exemption and do not create a synthetic journey or transactional record.

### GAP-UI-02 — HR cannot initiate a salary adjustment from the SPA

- **Classification:** Process gap (High)
- **Process / journey:** HR-07, `HR07-SALARY-APPROVAL`
- **Step / role:** Create a salary adjustment / `hr_officer`
- **Expected:** HR can create the compensation-change request through the UI, after which the active `salary_adjustment` workflow routes first to `production_manager` and then `vice_president`.
- **Observed:** The backend route, service, maker permission and active PM → VP workflow exist. The SPA has no caller for `salaryAdjustmentsApi.request`; Employee edit explicitly removes pay values from its update payload and only links to the salary-adjustment queue, which lists and acts on existing records but provides no request control. A fresh-baseline UI journey confirmed the absent compensation inputs and request action in both entry points, then stopped before any API write. Read-only DB verification after the run found zero salary-adjustment rows and one seeded target Employee. Playwright recorded the request step as skipped by GAP-UI-02 and PM/VP as blocked.
- **Impact:** A salary adjustment cannot be initiated through the UI, so the approval chain, pay-history writes, effective-date behavior and payroll impact cannot be exercised. No transactional record was created or fast-forwarded.
- **Evidence:** `api/app/Modules/HR/Services/SalaryAdjustmentService.php`, `api/app/Modules/HR/routes.php`, `api/database/seeders/WorkflowSeeder.php`, `spa/src/api/hr/salary-adjustments.ts`, `spa/src/pages/hr/employees/edit.tsx`, `spa/src/pages/hr/salary-adjustments/index.tsx`, [blocked journey report and UI screenshot](runs/2026-11-04-HR07-SALARY-APPROVAL-blocked.md), and archived [journey context](runs/assets/hr07-salary-approval-blocked/journey-context.json) and [Playwright results](runs/assets/hr07-salary-approval-blocked/process-results.json).
- **Reproduction:** Sign in through the login UI as `hr_officer`, open Human Resources → Employees, open an employee and select Edit, then inspect the Salary Adjustments queue. Pay inputs are absent and neither page exposes an HR request action.
- **Status:** Open. No application code changed. Owner decision and, if required, an approved separate UI fix are needed before salary approval variants can run.

## Test and environment issues

These entries explain failed runs that do not establish an application defect. They remain part of the run history and are not counted as product findings.

### TST-INV-001 — Initial zero-stock text assertion used a different numeric scale

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-HIGH-VALUE-APPROVAL`
- **Step / role:** Pending adjustment invariants / `warehouse_staff`
- **Expected:** On-hand stock is exactly zero before Finance approval.
- **Observed:** The read-only query returned the absent stock-level default as `"0"`; the test compared it to `"0.000"`. Other fields in the pending adjustment matched. The comparison stopped later step assertions from running.
- **Correction:** Normalize the read-only numeric expression to `numeric(14,3)` before converting it to text. This preserves the exact zero-stock assertion and changes no application code.
- **Evidence:** [INV-01 first-attempt report](runs/2026-11-03-INV01-first-attempt.md).
- **Status:** Resolved. The normalized assertion passed in INV-01's second run.

### TST-INV-002 — Sidebar link locator rejected a live unread-count badge

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-HIGH-VALUE-APPROVAL`
- **Step / role:** Find the adjustment notification / `finance_officer`
- **Expected:** The Finance actor locates Notifications from the visible Work shortcut group and follows the matching notice.
- **Observed:** The Notifications sidebar link has an unread-count badge, and the Warehouse child link sits in a collapsible section. After attempts to match the badge suffix and compact-sidebar accessible name, the custom helper timed out while opening Stock Adjustments even though the role has `inventory.adjust`. INV-01 attempt 2 had already passed the full Warehouse step with the original navigation approach.
- **Correction:** E2E helpers now locate internal menu anchors by their configured `href` within the Modules navigation, expand the owning section through its actual button, and click the sidebar anchor. The supplier portal retains its accessible link locator. This follows the in-app menu path and supports badges and collapsed groups.
- **Evidence:** [INV-01 second-attempt report](runs/2026-11-03-INV01-second-attempt.md), [third-attempt report](runs/2026-11-03-INV01-third-attempt.md), [fourth-attempt report](runs/2026-11-04-INV01-fourth-attempt.md), [fifth-attempt report](runs/2026-11-04-INV01-fifth-attempt.md); `spa/src/components/layout/Sidebar.tsx` defines the route anchors and expandable sections.
- **Status:** Resolved in E2E test code. The complete clean-baseline run reached Stock Adjustments through the sidebar and followed Finance's notification to the linked record.
- **Evidence:** [INV-01 sixth-attempt report](runs/2026-11-04-INV01-sixth-attempt.md), [first complete run](runs/2026-11-04-INV01-full-run.md).

### TST-INV-003 — Observer filter used native-select interaction on a searchable combobox

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-HIGH-VALUE-APPROVAL`
- **Step / role:** Prepare the Warehouse observer view / `warehouse_staff`
- **Expected:** Clear the status filter through the visible filter control and retain the pending adjustment in the open list.
- **Observed:** The test called `selectOption('')` on a `role=combobox` implemented by an input and a visible listbox. Playwright rejected the action after the Warehouse creation assertions and Finance notification hand-off had passed.
- **Correction:** The E2E test opens the Status combobox, chooses the visible `Status: All` option, and asserts the URL filter was removed. This does not weaken the live observer assertion.
- **Evidence:** [INV-01 sixth-attempt report](runs/2026-11-04-INV01-sixth-attempt.md); `spa/src/components/ui/FilterBar.tsx`, `spa/src/components/ui/Select.tsx`, and `spa/src/components/ui/SearchableSelect.tsx` define the rendered control and option label.
- **Status:** Resolved in E2E test code; selecting the visible `Status: All` option passed in the complete clean-baseline run.
- **Evidence:** [INV-01 complete run](runs/2026-11-04-INV01-full-run.md).

### TST-INV-004 — Count-session Scope control did not resolve by exact label

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-COUNT-CANCEL`
- **Step / role:** Create draft count / `warehouse_staff`
- **Observed:** After reaching the New count session modal through the Warehouse Map UI, `getByLabel('Scope', { exact: true })` timed out. No count session was created, and the observer/cancellation steps were blocked.
- **Evidence:** [INV01-COUNT-CANCEL first-attempt report](runs/2026-11-04-INV01-COUNT-CANCEL-first-attempt.md); `spa/src/components/ui/Select.tsx` renders a native select with a label.
- **Correction:** The E2E test locates the visible combobox using the accessible role and a Scope name prefix.
- **Status:** Resolved in E2E test code; the corrected control interaction passed in the complete count-cancellation journey.
- **Evidence:** [INV01-COUNT-CANCEL first attempt](runs/2026-11-04-INV01-COUNT-CANCEL-first-attempt.md), [complete run](runs/2026-11-04-INV01-COUNT-CANCEL-full-run.md).

### TST-INV-005 — Finance observer opened the wrong sidebar section

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-LOW-ADJUST`
- **Step / role:** Open Stock Adjustments as observer / `finance_officer`
- **Observed:** The link existed but remained hidden under the collapsed Finance section because the test helper was told to expand Warehouse. No adjustment form was opened and no transaction was created; the Warehouse step was blocked.
- **Correction:** Expand the actual Finance section and click its visible Stock Adjustments sidebar link.
- **Evidence:** [INV01-LOW-ADJUST first-attempt report](runs/2026-11-04-INV01-LOW-ADJUST-first-attempt.md); `spa/src/components/layout/Sidebar.tsx` places this Finance role's link in the Finance section.
- **Status:** Resolved in E2E test code; Finance opened the page through its own sidebar section in the clean-baseline [low-value adjustment run](runs/2026-11-04-INV01-LOW-ADJUST-full-run.md).

### TST-INV-006 — Low-adjustment notification assertion matched unrelated numeric IDs

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-LOW-ADJUST`
- **Step / role:** Posted adjustment invariants / `warehouse_staff`
- **Expected:** No notification is associated with the automatically posted low-value adjustment.
- **Observed:** A read-only query filtering only `notifications.data.subject_id` found 12 unrelated notices for other subjects with the same numeric primary key. The adjustment itself was approved, its single stock movement posted, and a query scoped to the stock-adjustment subject type found no notifications. Audit data names the Warehouse actor.
- **Correction:** Scope both notification queries to the stock-adjustment subject type as well as its ID. This preserves the no-notification check and protects the high-value recipient assertion from cross-model ID collisions.
- **Evidence:** [INV01-LOW-ADJUST invariant-attempt report](runs/2026-11-04-INV01-LOW-ADJUST-invariant-attempt.md); `api/app/Common/Services/NotificationService.php` stores `subject_type` and `subject_id`; `api/app/Modules/Inventory/Services/StockAdjustmentService.php` sends approval notifications only in the above-threshold branch.
- **Status:** Resolved in E2E test code; the corrected subject-specific check passed in the clean-baseline [low-value adjustment run](runs/2026-11-04-INV01-LOW-ADJUST-full-run.md).

### TST-INV-007 — Count-session creation check did not wait for the lookup guard

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-COUNT-VARIANCE`
- **Step / role:** Create warehouse-scoped count / `warehouse_staff`
- **Observed:** The initial attempt left the New count session dialog open, created no session, and emitted no POST to the stock-count endpoint. `StockCountManager` disables Create session while its warehouse/options lookups are fetching.
- **Correction:** The journey now asserts the Create session control is enabled after selecting the warehouse, verifies native required fields, observes the UI-initiated POST, and requires HTTP 201 before asserting the created record. The final clean-baseline run passed.
- **Evidence:** [count-variance report](runs/2026-11-04-INV01-COUNT-VARIANCE-full-run.md); `spa/src/pages/warehouse/stock-count.tsx` disables the submit control while lookups are pending.
- **Status:** Resolved in E2E test readiness assertions; no application change made.

### TST-INV-008 — Observer asserted count lines before opening the selected session

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-COUNT-VARIANCE`
- **Step / role:** Observe count line and variance / `system_admin`
- **Observed:** The observer had the live Stock Count list open but had not selected the new session; the detail table therefore was not rendered. The captured UI showed the session card and “Select a session to view details.”
- **Correction:** After finding the session in the updated list, the observer now selects its card through the UI and asserts the detail heading and pending line before checking realtime line updates.
- **Evidence:** [count-variance report](runs/2026-11-04-INV01-COUNT-VARIANCE-full-run.md); `spa/src/pages/warehouse/stock-count.tsx` renders the detail view only for the selected session.
- **Status:** Resolved in E2E test navigation; the independent observer received the full lifecycle without refresh.

### TST-INV-009 — Transfer assertion selected a nonexistent location column

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-TRANSFER`
- **Step / role:** Transfer source/destination state assertion / read-only verifier
- **Observed:** The first transfer attempt queried `warehouse_locations.full_code`, a column absent from the current schema. The business step had not completed, so no pass or product defect was inferred.
- **Correction:** Read the source-defined `warehouse_locations.code` field.
- **Evidence:** [INV01-TRANSFER full-run report](runs/2026-11-04-INV01-TRANSFER-full-run.md), attempt history section.
- **Status:** Resolved in the read-only assertion; the final clean-baseline transfer run passed.

### TST-INV-010 — Transfer item option was rendered in a portal outside the modal

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-TRANSFER`
- **Step / role:** Select RM-001 / `warehouse_staff`
- **Observed:** The SearchableSelect listbox is portalled at document scope. A modal-scoped locator could not see the option, so the transfer was not submitted in that attempt.
- **Correction:** Locate the visible accessible option from the page, then select it.
- **Evidence:** [INV01-TRANSFER full-run report](runs/2026-11-04-INV01-TRANSFER-full-run.md), attempt history section; `spa/src/components/ui/SearchableSelect.tsx`.
- **Status:** Resolved in E2E selectors; final transfer execution and cancellation runs passed.

### TST-INV-011 — Generic status role matched live indicator and toast messages

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-TRANSFER`
- **Step / role:** Assert maker/checker instruction / `warehouse_staff`
- **Observed:** A generic `role=status` locator matched the live connection indicator plus status toasts, producing a strict-mode ambiguity before transfer execution.
- **Correction:** Assert the exact maker instruction and separately assert the named Live connection indicator.
- **Evidence:** [INV01-TRANSFER full-run report](runs/2026-11-04-INV01-TRANSFER-full-run.md), attempt history section.
- **Status:** Resolved in E2E assertions; transfer execution and cancellation passed.

### TST-INV-012 — Repeated Playwright titles reused output paths and lost earlier failure artifacts

- **Classification:** Test/reporting issue
- **Process / journey:** INV-01, transfer execution attempts
- **Expected:** Each failed run retains its own trace, screenshot, console/network record, and step context for triage.
- **Observed:** Re-running the identical Playwright test title reused its output directory. The first two transfer-attempt traces/screenshots were overwritten before archival; attempt 3/4 and both passing journey artifacts were retained. HR03-MISSING-PUNCH attempt 02 also lacks its trace and error-context files; only its annotated screenshot and report remain. During the overtime work, an auth-timeout invocation's error context and screenshots were replaced by the following shared run before archival; auth setup tracing is intentionally off to avoid recording the E2E password. After overtime attempt 04 was archived, the shared `spa/test-results` trace path was rewritten to a small empty archive while its process-results JSON remained at the prior run timestamp; the replacement's source is unknown. The attempt 04 documentation copy remains intact.
- **Impact:** The first two transfer browser traces, the HR03 missing-punch attempt-02 trace/error context, and the overtime auth-timeout screenshot/error context cannot be reviewed. Attempt 04 is retained, but the overwritten shared output path shows why evidence must be archived immediately after each run. The corresponding missing artifact claims were corrected in the reports and finding.
- **Evidence:** [INV01-TRANSFER full-run report](runs/2026-11-04-INV01-TRANSFER-full-run.md), attempt history section; retained attempt 3/4 assets under `runs/assets/inv01-transfer-attempt-03/` and `runs/assets/inv01-transfer-attempt-04/`; [HR03 overtime auth-timeout report](runs/2026-11-04-HR03-OVERTIME-APPROVE-preflight-03-auth-interruption.md); [overtime attempt 04 report and archived trace](runs/2026-11-04-HR03-OVERTIME-APPROVE-attempt-04.md).
- **Status:** Open. The HR03 reports now state each artifact loss. Archive artifacts before any rerun; consider assigning unique per-invocation Playwright output directories in the E2E runner.

### TST-INV-013 — Transfer-cancellation test called a sibling-scoped helper

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-TRANSFER-CANCEL`
- **Step / role:** TypeScript/Playwright preflight
- **Observed:** The first cancellation invocation stopped during test compilation because `openTransfers` was declared inside the sibling transfer test and was not in scope for cancellation. No browser journey ran and no transactional record was created by that failed invocation.
- **Correction:** Move the UI navigation helper to shared spec scope.
- **Evidence:** [INV01-TRANSFER-CANCEL full-run report](runs/2026-11-04-INV01-TRANSFER-CANCEL-full-run.md); `spa/e2e-process/inventory.process.spec.ts`.
- **Status:** Resolved; `npm run typecheck:e2e` and the final clean-baseline cancellation run passed.

### TST-INV-014 — Direct Compose invocation omitted the targeted actor selector

- **Classification:** Test invocation issue
- **Process / journey:** INV-01, `INV01-NEGATIVE-STOCK`
- **Step / role:** Auth setup before browser journey
- **Observed:** A direct `docker compose run` omitted `E2E_AUTH_ACTORS` from Compose interpolation. The same missing selector recurred when `runner.mjs test` was invoked without the role selector in its process environment. Auth setup rejected the empty selector before any actor signed in or any business test ran.
- **Correction:** The guarded runner now honors a shell-provided `E2E_AUTH_ACTORS` selector while keeping all other environment identity and credentials sourced from `.env.e2e`. A focused unit test covers this narrow override; the selected Employee and Department Head then signed in through the real UI in HR03 overtime attempt 05. The Playwright global setup still applies the E2E environment guard.
- **Evidence:** [Negative-stock invocation failure](runs/2026-11-04-INV01-NEGATIVE-STOCK-auth-invocation-failure.md), [duplicate-submit invocation failure](runs/2026-11-04-INV01-DUPLICATE-SUBMIT-auth-invocation-failure.md), [HR07-PROFILE-ORDINARY invocation failure](runs/2026-11-04-HR07-PROFILE-ORDINARY-auth-invocation-failure.md), [HR03 overtime preflight 05](runs/2026-11-04-HR03-OVERTIME-APPROVE-preflight-05-auth-invocation-failure.md), [HR03 overtime attempt 05](runs/2026-11-04-HR03-OVERTIME-APPROVE-attempt-05.md), and archived screenshots/context in their adjacent asset folders.
- **Status:** Resolved in E2E runner tooling. No application behavior changed.

### TST-INV-015 — Outbound location assertion inspected the hidden native-select subtree

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-NEGATIVE-STOCK`
- **Step / role:** Select the stocked location for an outbound adjustment / `warehouse_staff`
- **Observed:** The first business attempt completed the Finance observer step and UI-created 1 kg adjustment step, then failed before entering the outbound quantity. The test searched for a native `<option>` under the visible combobox, but the shared `Select` renders an accessible `SearchableSelect` with the native select hidden. The screenshot showed the stocked `MW-A-A-01 · Main Warehouse` location auto-filled and the available quantity at 1 kg. No outbound request was sent.
- **Correction:** Assert the visible combobox's selected value and its “Filled from the item's stocked location” helper text. Keep the greater-than-available UI block assertion unchanged.
- **Evidence:** [INV01-NEGATIVE-STOCK first business attempt](runs/2026-11-04-INV01-NEGATIVE-STOCK-attempt-01.md), archived [trace](runs/assets/inv01-negative-stock-attempt-02-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/trace.zip), and `spa/src/components/ui/Select.tsx`.
- **Status:** Resolved. The next clean-baseline attempt passed the visible location-value assertion; its later failure is tracked separately as TST-INV-016.

### TST-INV-016 — Helper-text assertion used straight apostrophe while UI renders typographic apostrophe

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-NEGATIVE-STOCK`
- **Step / role:** Assert the auto-selected stocked location / `warehouse_staff`
- **Observed:** The second browser attempt completed the first two steps and passed the visible location-value assertion, then timed out on exact helper text `Filled from the item's stocked location.` The rendered text uses a typographic apostrophe, so the exact straight-apostrophe locator found no element. No outbound quantity or request occurred.
- **Correction:** Match the helper sentence with a regex that accepts the rendered apostrophe while retaining the helper-text assertion.
- **Evidence:** [Second browser attempt](runs/2026-11-04-INV01-NEGATIVE-STOCK-attempt-02.md), archived [trace](runs/assets/inv01-negative-stock-attempt-03-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/trace.zip), and screenshot showing the selected location.
- **Status:** Resolved. The third attempt passed the helper-text assertion and reached the quantity-limit assertion; the latter's formatting mismatch is tracked separately as TST-INV-017.

### TST-INV-017 — Quantity-limit assertion expected fixed decimals instead of UI formatting

- **Classification:** Test issue
- **Process / journey:** INV-01, `INV01-NEGATIVE-STOCK`
- **Step / role:** Assert the over-available outbound validation / `warehouse_staff`
- **Observed:** The third attempt entered 1.001 kg against 1 kg available. The UI displayed “Only 1 kg can be removed from this location” and showed Review adjustment disabled. The test expected `1.000`; `formatQuantity` renders an integer amount without trailing decimals. The test stopped before the remaining disabled-action and read-only DB assertions.
- **Correction:** Match the exact displayed validation text for the seeded RM-001 quantity while keeping the quantity-limit condition and disabled-submit assertions.
- **Evidence:** [Third browser attempt](runs/2026-11-04-INV01-NEGATIVE-STOCK-attempt-03.md), archived [trace](runs/assets/inv01-negative-stock-attempt-04-failed/inventory.process-INV01-NE-b4ef9-ment-beyond-available-stock-chromium-process/trace.zip), and `spa/src/lib/formatNumber.ts`.
- **Status:** Resolved. The clean-baseline run passed the quantity-limit text and disabled-action assertions. Read-only checks confirmed 1.000 kg remained on hand, no attempted adjustment or extra movement existed, no adjustment notification was emitted, and the Warehouse audit actor was correct. See the [full run report](runs/2026-11-04-INV01-NEGATIVE-STOCK-full-run.md).

### TST-MRP-001 — Sales order customer selector was treated as a native select

- **Classification:** Test issue
- **Process / journey:** MRP-01, `MRP01-SHORTAGE`
- **Step / role:** Choose the customer and product / `sales_officer`
- **Observed:** The form's accessible combobox is backed by `SearchableSelect`; its choices are visible listbox options and the native `<select>` is hidden from the accessibility tree. The test looked for nested native `<option>` elements under the input and found none, before submitting a transaction.
- **Correction:** Open each accessible combobox and click the matching page-level `role=option`, preserving exact one-option assertions.
- **Evidence:** Initial MRP attempt artifacts were reused by Playwright and overwritten; the failure details are summarized in the [MRP-01 report](runs/2026-11-04-MRP01-SHORTAGE-first-run.md). `spa/src/components/ui/Select.tsx` and `spa/src/components/ui/SearchableSelect.tsx` define the rendered control.
- **Status:** Resolved in E2E test code; the corrected UI selection passed in the first-run journey.

### TST-MRP-002 — Seeded price agreement did not cover the 60-day delivery date

- **Classification:** Test issue
- **Process / journey:** MRP-01, `MRP01-SHORTAGE`
- **Step / role:** Create the SO draft / `sales_officer`
- **Observed:** The test chose a customer/product with an agreement active on the E2E date but set delivery 60 days later, beyond the agreement's effective end. The UI returned HTTP 422 with “No base selling price or applicable customer agreement is configured for this delivery date.” No SO was created in that attempt.
- **Correction:** Read the agreement's `effective_to` from the disposable baseline and enter that valid date through the UI. The test still requires the actual price-resolution path to succeed.
- **Evidence:** [MRP-01 report](runs/2026-11-04-MRP01-SHORTAGE-first-run.md); `api/app/Modules/CRM/Services/PriceAgreementService.php` validates the agreement against delivery date.
- **Status:** Resolved in E2E test data selection; a subsequent clean-baseline attempt created the SO through the UI.

### TST-MRP-003 — Draft-save assertion expected a fallback banner instead of normal navigation

- **Classification:** Test issue
- **Process / journey:** MRP-01, `MRP01-SHORTAGE`
- **Step / role:** Verify created SO draft / `sales_officer`
- **Observed:** Normal draft creation returned HTTP 201 and navigated to the sales-order detail, but the test waited for “Draft saved,” a banner used only when confirmation fails after saving. The created draft was visible as `SO-202611-0001 Draft` with Confirm order available.
- **Correction:** Assert the detail heading, visible Draft status, generated SO number and Confirm order action at the destination page.
- **Evidence:** [MRP-01 report](runs/2026-11-04-MRP01-SHORTAGE-first-run.md); `spa/src/pages/crm/sales-orders/create.tsx` navigates on successful draft creation and renders the fallback banner only when confirmation fails.
- **Status:** Resolved in E2E test code; the corrected draft creation step passed on a fresh baseline.

### ENV-E2E-001 — HTTPS E2E proxy did not maintain the Reverb WebSocket route

- **Classification:** Environment issue
- **Process / journey:** E2E platform, discovered during INV-01 startup/navigation
- **Expected:** Browser Reverb connections through HTTPS reach the isolated Reverb service and complete a WebSocket handshake.
- **Observed:** The HTTPS proxy first served the SPA for `/app/`; after route forwarding was added, a recreated Reverb container caused `502` due to a stale upstream IP. The app's HTTP proxy already used Docker DNS refresh.
- **Correction:** The E2E-only TLS proxy forwards `/app/` and `/ws` to Reverb using Docker's embedded resolver with a refresh interval. Nginx configuration validation passed, and a read-only HTTPS handshake probe returned `101 Switching Protocols`.
- **Evidence:** [INV-01 first-attempt report](runs/2026-11-03-INV01-first-attempt.md), `docker/e2e/nginx-https.conf`.
- **Status:** Corrected in E2E infrastructure; the complete INV-01 run exercised the Reverb observer update successfully. No application behavior changed.
- **Evidence:** [INV-01 complete run](runs/2026-11-04-INV01-full-run.md).

### ENV-E2E-002 — E2E runner and full actor setup are intermittently terminated

- **Classification:** Environment issue
- **Process / journey:** E2E platform, discovered before `INV01-LOW-ADJUST`
- **Expected:** The guarded Playwright setup completes real UI login for the requested actors while the isolated E2E Compose services remain available.
- **Observed:** Earlier `runner.mjs test` invocations returned exit 130 before Playwright output, and a direct run had a transient `ERR_NAME_NOT_RESOLVED` for `https://nginx`; a later full 35-actor login and journey passed. On 2026-11-04, a full-auth invocation ended with exit 143 after 14 storage states had been refreshed. Other interrupted attempts stopped during baseline reset, browser startup, or an observer step; several showed no OOM event. Most recently, HR-07's sixteenth attempt passed real UI sign-in for Employee, HR, and Finance, then its Playwright journey was terminated before returning results. The guarded Compose services received shutdown signals at the same time. The prior process-results JSON remained from the fifteenth run, so this attempt has no step-level artifact. A subsequent fresh seed for `HR07-PROFILE-ORDINARY` also exited 143 during baseline rebuilding; Docker showed the API seed exec terminated with the service shutdown, API and PostgreSQL exited cleanly, and neither was OOM-killed. The actor-seeder exec had started, but no successful completion or 37-account verification was obtained, so that baseline was discarded. Before the later `HR07-SALARY-APPROVAL` run, the stack was stopped; the guarded seed refused to reset the database until the stack was restored. Guarded `up`/`wait` then brought all E2E services healthy and a fresh seed completed with all 37 isolated actors. During `HR03-PUNCH`, an attached runner invocation again exited 143 after the UI-created time-in/out actions; read-only database and clock checks confirmed both punches, but no Playwright result was emitted. A detached invocation of the same guarded runner completed the journey from a fresh baseline; the termination source remains unknown.
- **Additional HR-07 preflight evidence:** The first training preflight found no running E2E services and was recorded before any reset or browser startup. After guarded `up`/`wait`, the next locked `test` invocation lost PostgreSQL during `migrate:fresh`; PostgreSQL logged a fast shutdown while applying a foreign-key migration, and no browser started. Shutdown source remains unknown. [Readiness reports](runs/2026-11-04-HR07-TRAINING-COMPLETE-preflight-01-stack-stopped.md) and [database interruption report](runs/2026-11-04-HR07-TRAINING-COMPLETE-preflight-02-database-stopped.md).
- **Additional HR-07 preflight evidence:** A later guarded training run again stopped during `migrate:fresh` with exit 137. PostgreSQL logged a client connection reset, unexpected EOF, and administrative fast shutdown at 17:59:22 UTC; no browser started and no UI action occurred. Shutdown source remains unknown. [Readiness report](runs/2026-11-04-HR07-TRAINING-COMPLETE-preflight-03-database-stopped.md).
- **Additional HR-07 preflight evidence:** The first run after training attempt 07 was refused by the readiness guard because the dedicated E2E services had stopped. Exact-project Compose inspection found PostgreSQL/API exit 0, SPA exit 143, and Reverb/scheduler exit 131; no reset or browser started. Shutdown source remains unknown. [Readiness report](runs/2026-11-04-HR07-TRAINING-COMPLETE-preflight-04-stack-stopped.md).
- **Additional HR-07 interruption evidence:** After guarded recovery and successful fresh migration/seed, Playwright enumerated the selected auth setup and journey tests, then its one-off container received SIGTERM at 18:09:33 UTC. The dedicated services were stopped immediately afterward. PostgreSQL/API exited 0; SPA exited 143; Reverb/scheduler exited 131; inspected containers were not OOM-killed. The `.auth` states remained unchanged, and no journey context or process action was produced. Shutdown source remains unknown. [Interrupted attempt report](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-08-interrupted.md).
- **Additional HR-07 startup interruption:** A subsequent guarded `up` exited 1 when the API container exited 0 during startup; SPA exited 143, Reverb/scheduler exited 131, and PHP-FPM logged a normal shutdown. No baseline reset or browser followed that invocation. Shutdown source remains unknown. [Readiness report](runs/2026-11-04-HR07-TRAINING-COMPLETE-preflight-05-stack-startup.md).
- **Additional HR-02 baseline interruption:** Attempt 06 began `migrate:fresh --seed` and logged through `VehicleSeeder`; before E2E account seeding completed, every long-running E2E service received stop signals. Docker showed queue-dss exit 137 after SIGKILL and no OOM indication in the inspected status. No browser or process action ran; the baseline is discarded. Stop-signal source remains unknown. [Attempt 06 report and archived baseline log](runs/2026-11-04-HR02-HAPPY-attempt-06-stack-shutdown.md).
- **Additional HR-02 browser interruption:** Attempt 07 freshly seeded the baseline and passed auth setup for HR Officer and System Admin. Before the journey emitted a result, Docker events show stop signals to the one-off Playwright container and the E2E services. The stored `process-results.json` belongs to attempt 05 and is not current evidence; no HR02 context or process action was produced. Queue-main exited 137 after SIGKILL; no OOM indication was present in inspected status. Stop source remains unknown. [Attempt 07 report and log](runs/2026-11-04-HR02-HAPPY-attempt-07-playwright-interrupted.md).
- **Additional HR-02 seed interruption:** With a healthy guarded stack, a separate `runner seed` invocation began the fresh migration/seed but services stopped before E2E actor seeding completed. API and PostgreSQL exited 0; SPA exited 143; Reverb/scheduler exited 131; both queue-main and queue-dss exited 137. Inspected containers report `OOMKilled=false`. No browser or process action ran; stop source remains unknown. [Attempt 08 report and baseline log](runs/2026-11-04-HR02-HAPPY-attempt-08-seed-interruption.md).
- **Additional HR-02 journey interruption:** Attempt 12 passed selected HR Officer/System Admin UI sign-ins and the draft-posting, publish, and public-application steps. During the System Admin observer hand-off, the application list API returned 200 but the candidate-row assertion was interrupted while the page showed a loading skeleton. The trace does not retain the response body, so observer visibility is unverified. E2E services stopped while the step was active; inspected `OOMKilled` flags were false. A read-only query after service recovery reconfirmed application `JA-202611-0001` at stage `new` on its open posting. During that recovery, the stack received shutdown signals at 20:06:58 UTC, about 2m54s after guarded `up`; the only intervening database operation was the read-only query. The bounded Docker event output does not identify the initiating process. Six later steps did not run, and the run emitted no current `process-results.json`. No product defect is inferred. [Attempt 12 report and archived context/trace](runs/2026-11-04-HR02-HAPPY-attempt-12-interrupted-during-notification-handoff.md).
- **Additional HR-07 preflight evidence:** Before `HR07-TRAINING-COMPLETE` preflight 01, the guarded `test` command refused to start because no E2E services were running. It started no seed, migration, or browser. Guarded `up`/`wait` restored the stack; shutdown source remains unknown. [Readiness report](runs/2026-11-04-HR07-TRAINING-COMPLETE-preflight-01-stack-stopped.md).
- **Correction/workaround:** The auth setup accepts optional `E2E_AUTH_ACTORS` for a journey-specific subset; selected actors still sign in through the real UI. The guarded `up` command builds and detaches; `wait` polls the 12-service health snapshot; `seed` and `test` require a healthy stack and do not hold a Compose startup process open. The targeted HR-07 run completed through this path. `test-split` now resets the baseline, runs selected actor UI logins, restarts E2E services without removing volumes, then runs one exact journey ID; runner tests cover project isolation and reject broad selectors. This is a mitigation for the observed short service window, not a verified root-cause fix.
- **Evidence:** [low-adjustment first-attempt report](runs/2026-11-04-INV01-LOW-ADJUST-first-attempt.md), [auth interruption record](runs/2026-11-04-INV01-HIGH-REJECT-RESUBMIT-auth-interrupted.md), [subsequent passing journey](runs/2026-11-04-INV01-HIGH-REJECT-RESUBMIT-full-run.md), [negative-stock environment interruption before Playwright](runs/2026-11-04-INV01-NEGATIVE-STOCK-environment-interruption.md), [interrupted observer-step attempt and archived trace](runs/2026-11-04-INV01-NEGATIVE-STOCK-attempt-01-interrupted.md), [interrupted reset report](runs/2026-11-04-INV01-NEGATIVE-STOCK-reset-interrupted.md), [browser startup interruption](runs/2026-11-04-INV01-NEGATIVE-STOCK-browser-interruption-02.md), [HR-07 sixteenth interruption](runs/2026-11-04-HR07-BANK-CHANGE-sixteenth-interrupted-attempt.md), [profile-ordinary seed interruption](runs/2026-11-04-HR07-PROFILE-ORDINARY-seed-interruption.md), [salary-approval stack-readiness interruption](runs/2026-11-04-HR07-SALARY-APPROVAL-stack-interruption.md), [HR03-PUNCH interruption](runs/2026-11-04-HR03-PUNCH-interrupted-attempt.md), [passing HR03-PUNCH journey](runs/2026-11-04-HR03-PUNCH-full-run.md), [passing HR-07 journey](runs/2026-11-04-HR07-BANK-CHANGE-full-run.md), and the guarded runner / stack-readiness tests.
- **Status:** Open. DNS recovered and auth has passed in prior runs, but the intermittent container/runner termination has not been root-caused. Attempt 08 received SIGTERM during Playwright startup; preflight 05 later encountered a stack shutdown during API startup. Attempts 10–12 reached browser journey execution; attempt 12 was interrupted during the observer hand-off. Docker reported no OOM for these interrupted attempts. A separate confirmed Playwright-container OOM during multi-context screenshot capture is recorded as ENV-E2E-003. No application defect is inferred.
- **Additional HR-05 rejection evidence:** The initial rejection baseline completed migration/reference seeding but the E2E actor-seed exec exited 137 as the dedicated services received stop signals; no browser began in that invocation. A later fresh baseline and three real-UI logins passed, but the selected Playwright process exited 130 immediately after “Running 1 test.” A read-only query on the isolated database found no matching rejection loan after that interruption. The successful actor seed was subsequently confirmed as 37 accounts, and the next attempt produced a complete HTTP 500 test report. See [baseline interruption](runs/2026-11-04-HR05-REJECT-preflight-baseline-interruption.md) and [attempt 02](runs/2026-11-04-HR05-REJECT-attempt-02-runner-interruption.md). These are environment interruptions, not process outcomes.
- **Additional HR-03 evidence:** Before `HR03-CSV-VALIDATION`, guarded `seed` refused to run because the dedicated E2E stack was not fully running. That invocation started no browser and performed no reset or business action. Guarded `up`, `wait`, and a fresh `seed` restored a healthy isolated baseline before the recorded journey. No shutdown cause was established.
- **Additional HR-03 evidence:** A selected-actor run timed out waiting 20 seconds for Employee's real UI sign-in response and left the form busy before the business test began. A subsequent shared Playwright result signed in both actors successfully, and guarded stack readiness passed; the transient timeout's cause is unknown. [Auth timeout report](runs/2026-11-04-HR03-OVERTIME-APPROVE-preflight-03-auth-interruption.md).
- **Additional HR-03 evidence:** After an overtime attempt 04 artifact archive, a later guarded stack readiness check found the long-running E2E services stopped. The preceding `runner.mjs seed` call yielded without a captured exit status; read-only Compose status showed the dedicated containers exited, and no Playwright or PHPUnit process was active. Seed completion and shutdown source are unknown; no browser journey followed. [Stack interruption report](runs/2026-11-04-HR03-OVERTIME-APPROVE-preflight-04-stack-interruption.md).
- **Additional HR-03 evidence:** After the scheduler Compose update and a successful guarded seed, a corrected targeted overtime invocation was refused before Playwright startup because read-only Compose status found no running services. No browser or PHP test process was active, and no business transaction was created. The stack shutdown source remains unknown. [Preflight 06 report](runs/2026-11-04-HR03-OVERTIME-APPROVE-preflight-06-stack-stopped.md).
- **Additional HR-02 evidence:** The first recruitment invocation omitted `E2E_AUTH_ACTORS`; auth setup therefore began sequential real-UI sign-in for all 37 seeded actors and the run exited 130 before setup finished. The shared screenshots were overwritten by the following Playwright run before archival. No business step was confirmed. [Interrupted attempt report](runs/2026-11-04-HR02-HAPPY-attempt-01-interrupted-auth-setup.md).
- **Additional HR-02 evidence:** A filtered two-actor invocation completed fresh migration/seeding, then `auth.setup` failed resolving `https://nginx/sign-in`; the runner returned 143 as the dedicated services received shutdown signals. No business journey or transaction was confirmed. The shutdown/DNS cause remains unknown. [Attempt 03 report and auth artifacts](runs/2026-11-04-HR02-HAPPY-attempt-03-dns-startup-interruption.md).
- **Additional HR-05 preflight evidence:** The first `HR05-CASH-ADVANCE` split run was refused because the dedicated E2E stack had stopped. Guarded `up`/`wait` restored readiness, but the next split run lost the E2E services during `migrate:fresh` while PostgreSQL created `material_review_records`; Docker events show shutdown signals and PostgreSQL's fast shutdown at 21:26:28 UTC. No browser or business action ran. Shutdown source remains unknown. [Attempt 05 report](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-05-environment-interruption.md).
- **Additional HR-05 runner interruption:** On 2026-11-05, a fresh baseline migration and seed completed and the E2E account seeder reported 38 isolated actors. The guarded `test` command exited 143 while rebuilding the Playwright image, before TypeScript output, auth setup, or Chromium. The exact E2E Compose services stopped; the two queue workers exited 137 and Reverb/scheduler exited 131. Inspected E2E containers reported `OOMKilled=false`, and host memory showed 9 GiB available. Shutdown source remains unknown; no loan was created. [Attempt 08 report](runs/2026-11-05-HR05-LOAN-UNDER-THRESHOLD-attempt-08-playwright-build-interruption.md).
- **Additional HR-05 baseline interruption:** A second guarded invocation began `migrate:fresh --seed` at 00:05:16 UTC and its captured output reached `WarehouseSeeder`, then the API and E2E services received stop signals before E2E account seeding completed. API/PostgreSQL exited 0; the queue workers exited 137, SPA 143, Reverb/scheduler 131. Inspected E2E containers reported `OOMKilled=false`; a Docker event recorded signal 3 to the API but did not identify its source. No browser or business action ran. [Attempt 09 report](runs/2026-11-05-HR05-LOAN-UNDER-THRESHOLD-attempt-09-baseline-interruption.md).
- **Additional HR-05 baseline interruption:** On 2026-11-05, a selected `test-split` command exited 143 while the fresh seed was running `ProductRoutingSeeder`, after migration and several reference seeders completed but before `E2EAccountSeeder`. Docker events show SIGQUIT sent to E2E services; the initiating process is unknown. Host memory showed approximately 10 GiB available and swap was not configured, so OOM is not established. No browser, actor login, or business transaction ran. [Attempt 10 report](runs/2026-11-05-HR05-LOAN-UNDER-THRESHOLD-attempt-10-baseline-interruption.md).
- **Additional HR-05 baseline interruption:** The `HR05-PARTIAL-PAYMENT` split run exited 143 during fresh baseline seeding after `VehicleSeeder` and before E2E actor seeding. Docker events show SIGQUIT sent to E2E services at 00:43:29 UTC; the initiator is unknown. Host memory showed approximately 10 GiB available and swap was not configured; no OOM is established. No selected actor logged in and no browser or loan transaction ran. [Attempt report](runs/2026-11-05-HR05-PARTIAL-PAYMENT-attempt-01-baseline-interruption.md).
- **Additional MNT-01 stack interruption:** After a five-minute quiet window with no PHP test detected, the supervised `up`/`wait`/single-journey sequence exited 143 during stack startup/readiness. Docker events show stop/kill signals and destruction of the dedicated E2E containers; no PHP-test match was reported by the supervisor. No new Playwright result, actor storage state, journey context, or business action was produced. The initiating process is unknown. [Attempt 18 report and sanitized evidence](runs/2026-11-05-MNT01-BREAKDOWN-attempt-18-stack-interruption.md).
- **Additional MNT-01 build interruption:** Attempt 24 exited 143 during the Vite frontend image build, before baseline reset or Playwright. The supervisor did not report a PHP-test match. Follow-up inspection found no E2E containers or runner processes, no kernel OOM record, and approximately 8.7 GiB host memory available. Shutdown source remains unknown. [Attempt 24 report and runner evidence](runs/2026-11-05-MNT01-BREAKDOWN-attempt-24-build-interruption.md).
- **Additional PROD-01 build interruption:** Attempt 18 passed E2E stack readiness, `migrate:fresh --seed`, reference-data seeding and creation of 38 disposable actors, then BuildKit canceled the Playwright image export while preparing the TypeScript preflight. The supervised runner exited with SIGTERM (`-15`); its PHP-test guard reported no match. The dedicated services remained healthy and inspected containers reported `OOMKilled=false`. No auth setup, browser, or business step ran, and no transactional record was created. The SIGTERM source is unknown. [Attempt 18 report](runs/2026-11-05-PROD01-HAPPY-attempt-18-playwright-build-interruption.md).
- **Additional PROD-01 split-run interruption:** Attempt 19 passed fresh baseline seeding, TypeScript preflight, and real-UI auth setup for all six selected actors. During the required Compose restart before the journey, Docker events recorded SIGQUIT/SIGTERM/SIGINT to the dedicated services; the supervised runner exited with SIGTERM before readiness or Playwright. No host PHP test or Docker OOM event was detected; the signal source is unknown. No process transaction ran. [Attempt 19 report](runs/2026-11-05-PROD01-HAPPY-attempt-19-compose-restart-interruption.md).
- **Additional PROD-01 first-step interruption:** Attempt 20 passed baseline/actor seeding, TypeScript preflight, six selected UI logins, and service readiness. The partial browser trace shows the HR Officer loading Attendance & DTR and shift data; Docker then sent SIGTERM to Playwright and mixed stop signals to the E2E services. No PHP-test match or Docker OOM event was found. The sanitized trace contains no business mutation request, and a read-only DB query found zero new E2E shift assignments. The signal source is unknown. [Attempt 20 report and partial trace](runs/2026-11-05-PROD01-HAPPY-attempt-20-stack-interruption-during-first-step.md).
- **Mitigation:** The guarded runner now uses fixed runtime Compose metadata `ogami-erp-e2e-session` while retaining the logical environment guard `E2E_COMPOSE_PROJECT=ogami-erp-e2e`, `E2E_ENV=true`, `ogami_e2e`, and the `https://nginx` allowlist. Network and volume names are explicit and unchanged. This matches the previously successful isolated workaround; root cause remains unknown pending a run with the updated runner.

- **Additional HR-05 interruption evidence:** Attempts 11–13 passed selected UI auth or reached Playwright startup but were terminated during the split Compose restart or before a journey result. Attempt 13's possible process activity was discarded by the fresh migration/seed used for attempt 14. The final business attempt ran against the same dedicated E2E network and named volumes under temporary Compose metadata `ogami-erp-e2e-session`; the E2E environment guard still passed, but this workaround bypasses the normal project's runtime label and is not a root-cause fix. See [attempt 11](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-11-runner-interruption.md), [attempt 12](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-12-compose-restart-interruption.md), [attempt 13](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-13-playwright-interruption.md), and [attempt 14](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-14-final-approver-read-scope.md). Shutdown source remains unknown.
- **Additional MNT-01 interruption evidence:** On 2026-11-05, guarded startup, fresh baseline seeding, the four selected real UI sign-ins, and the HR shift-assignment prerequisite completed. Its read-only schedule and audit checks passed, but the Playwright JSON result remained stale and the E2E containers were absent after the runner exited before MWO creation. Shutdown source remains unknown. [Attempt 13 report and sanitized trace](runs/2026-11-05-MNT01-BREAKDOWN-attempt-13-runner-interruption.md).

### ENV-E2E-003 — Playwright container OOM-killed during annotated screenshot capture

- **Classification:** Environment issue
- **Process / journey:** E2E browser resources, `INV01-COUNT-VARIANCE`
- **Expected:** Chromium keeps the acting and observer sessions available through the required annotated step screenshots.
- **Observed:** Docker emitted an `oom` event for the one-off Playwright container at its 768 MiB memory cap; Chromium's target page crashed during screenshot annotation while the Warehouse and observer pages were open.
- **Correction:** Increased only the isolated Playwright container cap to 1280 MiB. The confirmed E2E service caps total 2480 MiB including this runner. The final clean-baseline count-variance run completed; a live memory sample during execution was 838 MiB.
- **Evidence:** [count-variance report](runs/2026-11-04-INV01-COUNT-VARIANCE-full-run.md), Docker OOM event for service `playwright`, and `docker-compose.e2e.yml`.
- **Status:** Resolved in E2E configuration; continue monitoring during multi-context journeys.

### ENV-E2E-004 — Repeated E2E seeding exhausted persistent queue Redis

- **Classification:** Environment issue
- **Process / journey:** E2E baseline setup, discovered during HR-07 reruns
- **Expected:** Each seeded baseline starts with empty transient queues and cache so background delivery is isolated to that run.
- **Observed:** `runner.mjs seed` ran `migrate:fresh` and the E2E account seeder but left Redis volumes intact. Across repeated baselines, queue Redis accumulated 37,027 keys and reached its 96 MiB `noeviction` limit. The latest seed log showed `OOM command not allowed when used memory > 'maxmemory'` and outbox-dispatch errors. The Redis service remained healthy, but background side effects could not be trusted while saturated.
- **Correction:** The guarded seed path now synchronously flushes only the dedicated `redis` and `redis-cache` Compose services before rebuilding PostgreSQL. An E2E environment guard inside the helper rejects any other project/database/flag. Unit tests cover exact service targets, failure short-circuiting, and wrong-environment refusal.
- **Evidence:** `docker-compose.e2e.yml` sets queue Redis to 96 MiB / `noeviction`; before correction, read-only `INFO memory` reported 100,756,768 bytes against a 100,663,296 max with `db0:keys=37027`, and seed output recorded outbox OOM errors. After the guarded reset, a fresh seed reported 2,720 queue DB0 keys, current usage 9,357,584 bytes (8.92 MiB), cache Redis usage 1.33 MiB, and no OOM/outbox failure in the complete seed log. `node scripts/e2e/runner.mjs config` passed.
- **Status:** Resolved in E2E baseline tooling; continue monitoring. No application behavior changed.

### ENV-E2E-005 — Manual Compose diagnostics omitted the dedicated E2E project name

- **Classification:** Environment isolation issue
- **Process / journey:** E2E diagnostics after `HR04-APPROVE` attempt 09
- **Expected:** Every operational Compose command targets only project `ogami-erp-e2e` and reads only the disposable `ogami_e2e` database.
- **Observed:** Three ad hoc `docker compose` commands omitted `-p ogami-erp-e2e`, so Compose resolved the default `ogami-erp` project. The service listing and a short database-container log tail were read. Three subsequent connection attempts failed before any SQL returned data (missing roles/database); no successful connection, query result, or write occurred.
- **Correction:** Use `scripts/e2e/runner.mjs` for E2E operations. Any necessary direct Compose inspection must include `-p ogami-erp-e2e --env-file .env.e2e -f docker-compose.e2e.yml`; verify the resolved container names include `ogami-erp-e2e` before `exec`.
- **Evidence:** [HR04 attempt 09 report](runs/2026-11-04-HR04-APPROVE-attempt-09.md); subsequent guarded project listing showed the isolated `ogami-erp-e2e-*` services. The failed connection messages are in the command session; no dev business data was returned or changed.
- **Status:** Guarded runner was used for all test execution. This manual-command discipline issue remains open for review; no database or application data was modified.

### ENV-E2E-006 — E2E schema disappeared while the training journey was active

- **Classification:** Environment issue (transient database schema loss)
- **Process / journey:** HR-07, `HR07-TRAINING-COMPLETE` attempt 02
- **Expected:** The seeded `ogami_e2e` schema remains available for the complete single journey.
- **Observed:** While HR was viewing the Add training form, API requests failed with HTTP 500 because `notifications`, `sessions`, and `products` relations were absent. A later authenticated-user request returned 401, and the SPA returned to Sign in. The journey sent no training create request. A read-only inspection after the failure found 274 tables and 831 migration records restored in the E2E database. The schema-loss source was not identified; this test invocation performed no seed/reset after its initial baseline setup.
- **Evidence:** [attempt 02 report and trace](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-02-environment-interruption.md); API/PostgreSQL logs at 2026-11-04 17:24:50–17:24:53 UTC; read-only `php artisan db:show` and `migrate:status` inspection.
- **Status:** Open. No app code changed. Confirm the E2E schema remains stable before continuing process journeys.

### ENV-E2E-007 — Archived Playwright traces retained encrypted E2E session cookies

- **Classification:** Environment / evidence-handling issue (limited to disposable E2E accounts)
- **Process / journey:** E2E report artifact handling; discovered during MNT01-BREAKDOWN attempt 13
- **Expected:** Trace archives preserve useful action/network evidence without retaining cookie, CSRF, session, or authorization values.
- **Observed:** A credential-marker scan found encrypted Laravel session-cookie values in 123 historical trace artifacts, including archived ZIPs and loose `.network`/`.trace` files. These were E2E-only sessions; no production or user credentials were involved. An earlier detached API diagnostic also printed a disposable E2E cookie in its error output.
- **Correction:** The initial cleanup scanned all 123 known cookie-bearing artifacts; 101 were rewritten, and the local MNT attempt trace/auth files were scrubbed. A later audit through the reusable scanner covered 523 archived trace candidates and found one remaining credential-like field in `hr03-overtime-approve-attempt-02/trace.zip`; that field was redacted. The final aggregate check reported 523 clean and 0 flagged. A guarded fresh baseline reset invalidated the earlier diagnostic sessions.
- **Prevention:** Both guarded Playwright runner paths now sanitize and then check `spa/test-results/process` before returning. The scanner is restricted to E2E artifact roots and never prints values. Manual archive instructions are documented in [02-platform.md](02-platform.md).
- **Status:** Resolved in E2E artifact handling. Four sanitizer tests and two runner-hook tests pass; no application code changed.

### ENV-E2E-008 — A PHP test started during a guarded E2E run

- **Classification:** Environment / run-coordination issue; resource-policy violation
- **Process / journey:** MNT01-BREAKDOWN attempts 14, 15, and 17
- **Expected:** The isolated Playwright journey runs only when no PHP test suite is active.
- **Observed:** The E2E preflight found no PHP test process. A separate development-stack filtered PHP test started during E2E baseline/type-check work and was detected after the Playwright journey had begun. A brief overlap occurred. The E2E Playwright container and then the isolated E2E stack were stopped; the PHP test was left untouched. The test report was not flushed, so the last completed journey step is unknown. Attempt 17 began after 60 seconds without a detected PHP test. A temporary `/proc` watcher then reported a substring match and stopped all 12 E2E containers; the runner exited 137. Review found that the watcher's own launching shell command contained its PHP-test search strings, so a self-match is plausible. The guarded runner's independent process matcher found no active PHP test after shutdown. No new Playwright result, journey context, or actor storage state was produced; no attempt-17 process step is evidenced, and a brief overlap cannot be ruled out. Attempt 19 passed a five-minute quiet preflight, fresh baseline, TypeScript preflight, and all four selected real-UI auth logins. During the remaining `test-split` sequence, an ancestry-aware monitor using the guarded runner's exact process patterns detected a host PHP test and stopped 13 dedicated E2E containers. The auth setup report was flushed as passed, but no MNT journey result or context was produced. A brief overlap cannot be excluded. Attempt 25 also began after a five-minute quiet window and completed all seven MNT steps. Playwright's recorded end was 07:07:41.735Z; Docker records the E2E container stop at 07:07:42Z after a PHP-test process was detected. Those timestamps show no overlap in attempt 25, although the PHP process start time was not captured.
- **Evidence:** [Attempt 14 report and sanitized trace](runs/2026-11-05-MNT01-BREAKDOWN-attempt-14-concurrency-stop.md), [attempt 15 report](runs/2026-11-05-MNT01-BREAKDOWN-attempt-15-baseline-stop.md), [attempt 17 report and sanitized runner output](runs/2026-11-05-MNT01-BREAKDOWN-attempt-17-monitor-stop.md), [attempt 19 report and sanitized auth evidence](runs/2026-11-05-MNT01-BREAKDOWN-attempt-19-php-test-stop.md), and [attempt 25 completed journey](runs/2026-11-05-MNT01-BREAKDOWN-attempt-25-full-run.md); process snapshots at 04:31–04:32 UTC; stale Playwright results were not used as journey evidence.
- **Prevention:** The guarded runner fails closed when `ps` shows `phpunit` or `php artisan test`. It checks before E2E stack startup, each baseline mutation, TypeScript compilation, and Playwright. This does not provide an atomic lock with an externally launched PHP suite, so the operator must still monitor during the run. Attempt 25’s temporary supervisor stopped only containers with the exact E2E project label after the Playwright result. The supervisor is now revised to send SIGTERM to the runner process group, escalate to SIGKILL after three seconds if needed, and then stop only E2E-labeled containers when it detects a host PHP test. A five-minute quiet window remains required before another journey.
- **Additional attempt 15 evidence:** A second filtered development PHP test, `test_repeated_submit_returns_a_state_conflict`, started during the E2E `migrate:fresh` command after its process check. The E2E API container was stopped before account seeding or Playwright. This demonstrates the boundary checks cannot protect a long synchronous command from a process that starts after its check. [Attempt 15 report](runs/2026-11-05-MNT01-BREAKDOWN-attempt-15-baseline-stop.md).
- **Additional attempt 17 evidence:** The temporary watcher stopped the isolated E2E containers during `test-split`, but its substring match may have come from its own launch shell. A post-stop check with the runner's process matcher found no active PHP test, so an external test during attempt 17 is unconfirmed. The sanitized runner output and the monitoring limitation are documented in the [attempt 17 report](runs/2026-11-05-MNT01-BREAKDOWN-attempt-17-monitor-stop.md).
- **Additional attempt 19 evidence:** After a five-minute quiet window and successful selected-role UI logins, the ancestry-aware monitor found a host PHP test using the runner's exact patterns and stopped the dedicated E2E containers. The auth setup result passed; no process journey result was emitted. [Attempt 19 report and sanitized evidence](runs/2026-11-05-MNT01-BREAKDOWN-attempt-19-php-test-stop.md).
- **Additional attempt 03 evidence:** PROD01-HAPPY attempt 03 passed a five-minute quiet window, then an external `ProfileUpdateSodTest` Laravel test started during the guarded fresh baseline at `LeaveTypeSeeder`. The monitor terminated the E2E runner and stopped only its 12 dedicated containers; the PHP test was left untouched. The baseline was incomplete, no Playwright container started, and no process journey step ran. The E2E and development databases are separate, but the runtime overlap violated the no-concurrent-test resource rule. [Attempt report](runs/2026-11-05-PROD01-HAPPY-attempt-03-php-test-interruption.md).
- **Additional attempt 06 evidence:** PROD01-HAPPY attempt 06 also passed a five-minute quiet window, then the exact-pattern `/proc` monitor detected a host PHP test during fresh `migrate:fresh --seed`. It terminated the guarded runner and stopped the 12 `ogami-erp-e2e-session` containers. The log ended during migrations; actor seeding and browser setup did not occur, and there is no Playwright result or process step. The monitor did not retain the external test command. The development and E2E databases remain separate, but this is a second runtime-overlap violation. [Attempt report](runs/2026-11-05-PROD01-HAPPY-attempt-06-php-test-interruption.md).
- **Additional attempt 07 evidence:** Another host PHP test was detected during the initial quiet interval, which reset the timer; it ended, followed by a new full 300-second quiet interval. A fresh baseline and real-UI authentication for all six selected actors then passed. The Playwright journey recorded four completed UI steps, then failed at PPC's Work Orders heading expectation before creating a work order; the next 16 steps were blocked. A host PHP test was detected during the journey wrapper, after which the monitor terminated the runner and stopped the 12 E2E-labeled containers. The trace was manually sanitized and checked after termination. The development and E2E databases remain separate, but a brief runtime overlap occurred. [Attempt report and sanitized artifacts](runs/2026-11-05-PROD01-HAPPY-attempt-07-production-list-heading.md).
- **Additional attempt 08 evidence:** After a fresh five-minute quiet interval, `migrate:fresh --seed`, the E2E reference/account seeders, and all six selected real-UI logins passed. A host PHP test was then detected during the service restart/readiness stage after auth; the monitor stopped the 11 remaining E2E-labeled containers before Playwright launched the production journey. No business action or transaction occurred in this attempt. The external test command was not captured; a post-stop check found no active PHP test. [Attempt report](runs/2026-11-05-PROD01-HAPPY-attempt-08-php-test-service-restart.md).
- **Additional attempt 09 evidence:** A host PHP test was active at the initial preflight, and the ancestry-aware monitor detected a filtered `MrbDoubleReleaseRaceTest` during the next quiet interval. The timer reset; after both processes ended, a full new 300-second interval passed. No PHP test was detected during the attempt-09 baseline, auth setup, or journey. The E2E journey completed with a test-query failure, not a concurrency interruption. [Attempt report](runs/2026-11-05-PROD01-HAPPY-attempt-09-shortage-pr-query.md).
- **Additional attempt 10 evidence:** After two host PHP tests reset the quiet timer, a new full 300-second interval passed. A filtered `NcrCapaEffectivenessTest` then started during the guarded Docker image build. The monitor terminated the E2E runner process group; the SPA Vite build was canceled during transformation, and the monitor found zero running E2E-labeled containers. No baseline, E2E seeding, Playwright, actor login, or business action occurred. The external PHP test was left untouched. [Attempt report](runs/2026-11-05-PROD01-HAPPY-attempt-10-php-test-startup-interruption.md).
- **Additional attempt 11 evidence:** After a new full 300-second quiet window, image startup succeeded and the fresh E2E database seed began. The host-test monitor detected a PHP test during the journey wrapper while default seeders were running; it terminated the guarded runner and stopped only the 12 dedicated E2E containers. The log ends at `VehicleSeeder`, before E2E reference/account seeding or Playwright auth; no journey action ran. The isolated baseline is incomplete and requires a fresh reset. [Attempt report](runs/2026-11-05-PROD01-HAPPY-attempt-11-php-test-baseline-interruption.md).
- **Additional attempt 12 evidence:** After the next 300-second quiet interval, the complete baseline and E2E seeders passed, and all six selected roles signed in through the real UI. A host PHP test was detected during the post-auth service restart; the supervisor stopped the four remaining E2E-labeled containers before launching the production journey. No business step ran; the external test command was not captured. [Attempt report](runs/2026-11-05-PROD01-HAPPY-attempt-12-php-test-service-restart.md).
- **Additional attempt 13 evidence (ENV-E2E-002):** After another clean 300-second interval, the baseline, E2E account seeding, TypeScript preflight and Playwright image setup completed. Playwright authentication setup began; two screenshots show the authenticated HR Officer and Employee dashboard shells displaying `Reconnecting`. Before the auth test result, the E2E services stopped and the runner received SIGTERM. The host PHP-test monitor reported no active PHP test, all inspected containers had `OOMKilled=false`, and the external stop source is unknown. No business journey step ran. [Attempt report and screenshots](runs/2026-11-05-PROD01-HAPPY-attempt-13-e2e-stack-shutdown.md).
- **Additional attempt 14 evidence (ENV-E2E-002):** After a clean quiet interval, the fresh migration reached `2026_08_11_160000_create_scheduler_execution_ledger` and printed `FAIL`. PostgreSQL recorded a fast administrator-requested shutdown at 11:49:14 UTC, terminating active connections. The PHP-test monitor reported no host test; containers reported no OOM. E2E-specific seeding and browser setup were not reached. The shutdown source is unknown, so this run does not establish a migration defect. [Attempt report](runs/2026-11-05-PROD01-HAPPY-attempt-14-e2e-database-shutdown.md).
- **Additional attempt 21 evidence:** The ancestry-aware supervisor waited the full 300-second quiet interval, then its exact PHP-test matcher reported a match at the final preflight and refused before invoking guarded E2E startup. No E2E containers, baseline reset, browser, or journey action ran. The supervisor did not retain the PID or command; an immediate process inspection found no active test. Attempt 22's separate instrumented quiet interval later captured a filtered `RfqBiddingTest.php` process in the supplier RFQ worktree; this is a plausible but unconfirmed source of the earlier match. [Attempt report](runs/2026-11-05-PROD01-HAPPY-attempt-21-preflight-guard-refusal.md).
- **Additional attempt 22 coordination evidence:** The instrumented supervisor captured the exact filtered supplier RFQ `RfqBiddingTest.php` process and restarted the full quiet timer after it ended. A new 300-second interval then completed; the guarded baseline and single Playwright journey ran without a PHP-test interruption. The E2E stack was stopped after the run, and its volumes were preserved. This confirms the quiet-window guard can identify and wait for this external worktree test when it is active; no shared database was used.
- **Additional attempt 23 DNS evidence:** After another full quiet interval, the fresh baseline, TypeScript preflight, six selected real-UI logins, service restart, and health checks passed. The browser journey's first gated clock request then failed with `getaddrinfo EAI_AGAIN nginx`; no process step or business transaction ran. The host PHP-test monitor reported no interruption. Docker recorded isolated-service SIGQUIT/SIGTERM cleanup after the failure; its source is unknown, and no OOM event was found. Attempt 24 will use the guarded non-split `test` path to avoid the extra network teardown/recreation between auth and journey; this is a diagnostic path change, not a root-cause fix. [Attempt report and sanitized artifacts](runs/2026-11-05-PROD01-HAPPY-attempt-23-clock-endpoint-dns-failure.md).
- **Current environment observation:** The same filtered PHP test process was active again at 04:47 UTC while all E2E containers were down. No E2E command was started during this observation.
- **Additional preflight observation:** While preparing the next HR-08 termination run, an E2E Compose stack and one-off Playwright container from an unknown session were already present before this session's stack-aware quiet monitor reached its launch boundary. The monitor was stopped before it invoked `runner up` or Playwright; the observed containers were left untouched. No baseline reset, browser login, or process action from this session occurred. A stack-free and PHP-test-free 300-second window is now required before launch.
- **Additional HR08-TERMINATION attempt 01 evidence:** After a clean 300-second quiet interval, a fresh baseline and selected-role UI authentication passed. During the 16-step termination journey the host-test monitor detected a PHP test and terminated the guarded runner with exit 91; the E2E stack was stopped. The journey context records all 16 step checks as passed, but the Playwright summary was not emitted and the external-test start time is not captured, so a brief overlap cannot be excluded. This variant remains pending a clean rerun. [Interruption report, context, and screenshots](runs/2026-11-05-HR08-TERMINATION-attempt-01-host-test-interruption.md).
- **Additional HR08-TERMINATION attempt 02 evidence:** After another clean 300-second stack-free/PHP-test-free interval, the isolated services became healthy and `runner test --grep HR08-TERMINATION` began the fresh baseline. The child received SIGTERM during the default seed, before Playwright, and the supervisor stopped only the isolated project. Its PHP-test detector did not report a match; no PHP test or E2E container was visible after teardown. The source of SIGTERM is unknown; kernel logs were inaccessible and Docker events showed only teardown. No browser or business step ran. [Attempt 02 report](runs/2026-11-05-HR08-TERMINATION-attempt-02-seed-interruption.md).
- **Additional HR08-TERMINATION attempt 03 evidence:** After another clean 300-second interval, the baseline, TypeScript preflight, eight-role real-UI authentication, and first five business steps passed. The monitor then captured a concurrent development-stack PHP test (`RealtimeQueueHealthTest::test_a_stale_processing_outbox_message_marks_delivery_degraded`) and stopped E2E with exit 91. Warehouse step 6 had timed out waiting for the clearance-list heading; because the PHP run overlapped and clock cleanup then failed DNS during teardown, that locator observation is inconclusive and is not logged as a product finding. The sanitized trace and five screenshots are archived; the raw error dump was excluded because it contained the E2E control header. [Attempt 03 report and artifacts](runs/2026-11-05-HR08-TERMINATION-attempt-03-host-test-interruption.md).
- **Additional HR08-TERMINATION attempt 04 evidence:** After a clean 300-second window, fresh E2E migrations/default seed and the E2E reference/38-account seeding completed. The monitor then detected a separate filtered Laravel feature test (`QueueLaneAssignmentTest::test_horizon_monitors_the_actual_outbox_queue_connection`) and stopped the isolated runner with exit 91. The TypeScript preflight, Playwright, and all business steps were not reached; no E2E containers or PHP test process remained after teardown. [Attempt 04 report](runs/2026-11-05-HR08-TERMINATION-attempt-04-host-test-interruption.md).
- **Additional preflight observation after attempt 04:** The attempt-05 quiet-window supervisor received SIGTERM before completing a fresh 300-second interval. It had not invoked `runner up` or Playwright; no E2E containers were started by that monitor. The source of the signal is unknown. This is not counted as a business journey attempt or report.
- **Additional HR08-TERMINATION attempt 05 evidence:** After a fresh 300-second quiet interval, the isolated stack, fresh baseline, and 38 E2E actors were ready. The host guard captured a concurrent PHP feature test (`BadgeRealtimeTest::test_creating_a_badge_relevant_model_touches_version_and_stages_refresh_outbox`) at the Playwright image/preflight boundary and stopped E2E with exit 91. No browser authentication or business step ran; TypeScript preflight outcome is indeterminate. The E2E stack was removed and no E2E container remained. [Attempt 05 report](runs/2026-11-05-HR08-TERMINATION-attempt-05-host-test-interruption.md).
- **Status:** Open. The boundary guard catches active PHP tests at phase boundaries, but cannot provide an atomic lock with an external PHP test runner. The temporary substring watcher used in attempt 17 is discarded because it may self-match. Coordinate the two runners before a new journey attempt. A fresh guarded baseline is required before every new process journey. Attempt 25's timestamps show its MNT journey ended before E2E shutdown; the PHP process start time was not captured. PROD01-HAPPY attempt 03 shows an external PHP test can start during the long baseline command after a quiet window; the watcher now stops only the isolated E2E stack, but an atomic cross-runner lock remains unavailable. This incident does not indicate shared database access; the E2E and development databases are separate.

### TST-PROD-001 — Tablet-floor project launched WebKit

- **Classification:** Test issue
- **Process / journey:** PROD-01, `PROD01-HAPPY` attempt 01
- **Step / role:** Browser startup, before the journey body
- **Expected:** The tablet-floor project launches headless Chromium with tablet/touch emulation.
- **Observed:** The project inherited Playwright's `iPad (gen 7)` descriptor, which selects WebKit. WebKit rejected the shared Chromium flag `--disable-dev-shm-usage`; the test failed after 4 ms before any actor action. The E2E baseline had been seeded, but no business record or process side effect was created.
- **Correction:** Use the Desktop Chrome descriptor with explicit `browserName: 'chromium'`, 810×1080 viewport, `isMobile`, `hasTouch`, and device scale factor 2. A fresh-baseline attempt is pending; the original failure remains archived.
- **Evidence:** [Attempt 01 report and sanitized trace](runs/2026-11-05-PROD01-HAPPY-attempt-01-browser-project.md); `spa/playwright.process.config.ts`.
- **Status:** Resolved in E2E test configuration; the production journey has not yet run.

### TST-PROD-002 — Read-only profile lookup treated an Eloquent accessor as a column

- **Classification:** Test issue
- **Process / journey:** PROD-01, `PROD01-HAPPY` attempt 02
- **Step / role:** Seeded employee fixture lookup, before the journey opens its actor contexts or begins a process step
- **Expected:** Read the seeded employee's stored fields and derive the name used by the crew-selection UI.
- **Observed:** The query selected `employees.full_name`, which is computed by `Employee::getFullNameAttribute()` and is not a PostgreSQL column. The read-only query failed before any process UI action or transactional record.
- **Correction:** Select stored name fields and compose the display name with the accessor's rules in the E2E fixture. A fresh-baseline attempt is pending; the original failure remains archived.
- **Evidence:** [Attempt 02 report and sanitized artifacts](runs/2026-11-05-PROD01-HAPPY-attempt-02-worker-profile-query.md); `api/app/Modules/HR/Models/Employee.php`; `api/database/migrations/0016_create_employees_table.php`.
- **Status:** Corrected in E2E test code; journey execution remains pending.

### TST-PROD-003 — Read-only routing fixture query used a nonexistent foreign key

- **Classification:** Test issue
- **Process / journey:** PROD-01, `PROD01-HAPPY` attempt 04
- **Step / role:** WB-001 fixture lookup, before the first process UI action
- **Expected:** Read the active routing setup time from the E2E reference-data baseline.
- **Observed:** The query joined `routing_operations.product_routing_id`, but the table stores the foreign key in `routing_id`. PostgreSQL stopped the query; no process action or transactional record occurred.
- **Correction:** Join on `routing_operations.routing_id` as defined by the migration and model. A fresh-baseline run is pending; the original failure remains archived.
- **Evidence:** [Attempt 04 report and sanitized artifacts](runs/2026-11-05-PROD01-HAPPY-attempt-04-routing-fixture-join.md); `api/database/migrations/0256_create_routing_operations_table.php`; `api/app/Modules/Production/Models/RoutingOperation.php`.
- **Status:** Corrected in E2E test code; journey execution remains pending.

### TST-PROD-004 — Listener verification used nonexistent chain-run columns

- **Classification:** Test issue found during source preflight
- **Process / journey:** PROD-01, `PROD01-HAPPY`
- **Step / role:** Verify completion dispatched outgoing QC
- **Expected:** Link the `TriggerOutgoingQC` listener run to the completed work order and assert it completed once.
- **Observed:** The query referenced `chain_listener_runs.source_type`, `source_id`, and `listener`; the schema instead stores listener metadata in `listener_class` and links the listener run to aggregate identity through `chain_step_runs` (`entity_type`, `entity_id`). This query had not yet executed in a journey attempt.
- **Correction:** Join `chain_listener_runs` to `chain_step_runs` by `outbox_id`, scope to the `work_order` entity and ID, and retain the exact completed-once assertion. No application behavior or assertion was weakened.
- **Evidence:** `api/database/migrations/2026_08_10_110000_create_event_outbox_and_chain_step_runs.php`, `api/database/migrations/2026_08_10_120000_create_chain_listener_runs.php`, `api/app/Common/Services/OutboxService.php`, `spa/e2e-process/production-stock.floor.spec.ts`.
- **Status:** Corrected before a process run; awaits fresh-baseline execution.

### TST-PROD-005 — E2E baseline omitted the finished-good item identity

- **Classification:** Test issue (E2E reference-data fixture)
- **Process / journey:** PROD-01, `PROD01-HAPPY` attempt 05
- **Step / role:** Read-only WB-001 fixture precondition, before the first process UI action
- **Expected:** The active WB-001 product/BOM baseline also contains its matching finished-good inventory item identity.
- **Observed:** The BOM and component rows were present, but the fixture lookup returned `finished_item_id = null`; the exact precondition failed. No business UI action or transactional record was created.
- **Cause:** `ManufacturedProductInventorySeeder` creates the matching item master row for active BOM-backed products, but `E2EReferenceDataSeeder` did not call it.
- **Correction:** The E2E-environment-gated `E2EReferenceDataSeeder` now calls the idempotent manufactured-product identity seeder. It creates reference/master identities only; opening stock and all later process records remain UI-created by the role that owns each step. No business assertion was weakened.
- **Evidence:** [Attempt 05 report and sanitized artifacts](runs/2026-11-05-PROD01-HAPPY-attempt-05-finished-good-fixture.md); `api/database/seeders/E2EReferenceDataSeeder.php`; `api/database/seeders/ManufacturedProductInventorySeeder.php`; `api/database/seeders/E2EAccountSeeder.php`; `spa/e2e-process/production-stock.floor.spec.ts`.
- **Status:** Corrected in E2E-only baseline setup; pending fresh-baseline confirmation.

### TST-PROD-006 — PPC Work Orders page heading expectation did not match the UI

- **Classification:** Test issue (exact accessible heading)
- **Process / journey:** PROD-01, `PROD01-HAPPY` attempt 07
- **Step / role:** Create WB-001 stock work order / PPC Head
- **Expected:** After navigating through Production → Production Work Orders, the test should assert the page's visible heading and then use the available Produce for stock action.
- **Observed:** Navigation succeeded, and the page rendered heading `Work orders` plus the `Produce for stock` button. The test expected heading `Production Work Orders` and timed out before clicking the button. No work order or downstream production record was created; later dependent steps were blocked.
- **Cause:** The E2E locator used the sidebar label as the page heading. The page title is explicitly `Work orders` in `spa/src/pages/production/work-orders/index.tsx`.
- **Correction:** Update only the exact heading locator to `Work orders`; preserve the navigation, create-action, and business-state assertions. The original failure remains archived and requires a fresh-baseline run to confirm the correction.
- **Evidence:** [Attempt 07 report, screenshots, journey context, and sanitized trace](runs/2026-11-05-PROD01-HAPPY-attempt-07-production-list-heading.md); `spa/src/pages/production/work-orders/index.tsx`; `spa/e2e-process/production-stock.floor.spec.ts`.
- **Status:** Corrected in E2E test code; awaiting fresh-baseline confirmation.

### TST-PROD-007 — Shortage verification used a nonexistent purchase-request work-order key

- **Classification:** Test issue (read-only verification query)
- **Process / journey:** PROD-01, `PROD01-HAPPY` attempt 09
- **Step / role:** Verify the PPC-created stock work order / `ppc_head`
- **Expected:** After UI creation, assert the work order's material lines and that no shortage purchase requests were generated.
- **Observed:** PPC created `WO-202611-0001` through the Work Orders UI. The UI action and planned-status assertions passed, but the subsequent read-only assertion queried `purchase_requests.work_order_id`; PostgreSQL rejected it because that column does not exist. The Playwright step failed and its 16 dependent hand-offs were blocked. A separate read-only diagnostic query using the schema's actual key found the work order still `planned`, with three material lines and zero shortage requests. That diagnostic does not count as the journey invariant passing.
- **Cause:** The `purchase_requests` relation stores the work-order FK in `stock_production_work_order_id`, as defined by `api/database/migrations/2026_10_09_220000_add_stock_production_and_order_allocations.php` and `PurchaseRequest::$fillable`.
- **Correction:** Use `request.stock_production_work_order_id` in the verification query and retain the exact zero-shortage assertion. No UI action, expected business result, or application code changed.
- **Evidence:** [Attempt 09 report and sanitized artifacts](runs/2026-11-05-PROD01-HAPPY-attempt-09-shortage-pr-query.md); `spa/e2e-process/production-stock.floor.spec.ts`; `api/database/migrations/2026_10_09_220000_add_stock_production_and_order_allocations.php`; `api/app/Modules/Purchasing/Models/PurchaseRequest.php`.
- **Status:** Corrected in E2E test code; a fresh-baseline run is required to confirm it.

### TST-PROD-008 — Worker option locator was scoped inside a dialog that does not contain its portal

- **Classification:** Test issue (portal-aware locator scope)
- **Process / journey:** PROD-01, `PROD01-HAPPY` attempt 15
- **Step / role:** Find the planned work order, assign the Production operator, and confirm / `production_manager`
- **Observed:** The HR coverage, three Warehouse stock entries, and PPC work-order creation steps passed. Production Manager followed the signal, opened the review dialog, and searched by the seeded employee number. The test timed out because it queried the `Add worker` listbox from inside the dialog. The sanitized trace's DOM snapshot shows the employee option was present in the page-level listbox. The UI component renders this searchable list through a document-level portal outside the dialog's subtree. No crew was selected and no confirmation was submitted; 15 downstream steps were blocked.
- **Cause:** `SearchableSelect` in `spa/src/components/ui/SearchableSelect.tsx` calls `createPortal` for its popup. The option must be located from the page, not through the dialog locator.
- **Correction:** Locate `getByRole('listbox', { name: 'Add worker' })` from `manager.page`; preserve the exact one-option, enabled, and click checks. No expected result or application code changed.
- **Evidence:** [Attempt 15 report, sanitized trace, error context, and screenshots](runs/2026-11-05-PROD01-HAPPY-attempt-15-worker-option-portal-locator.md); `spa/e2e-process/production-stock.floor.spec.ts`; `spa/src/components/ui/SearchableSelect.tsx`.
- **Status:** Corrected in E2E test code; a fresh-baseline run is required to confirm it.

### TST-PROD-009 — Warehouse location option becomes unusable during a stock adjustment

- **Classification:** Test interaction issue; RM-010 and PKG-001 behavior remains unverified
- **Process / journey:** PROD-01, `PROD01-HAPPY` attempt 16
- **Step / role:** Select a receiving location for a baseline stock adjustment / `warehouse_staff`
- **Observed:** Attempt 16 passed HR shift assignment and RM-001/RM-010 stock adjustments, then Playwright's ordinary click failed on the visible PKG-001 location option. Attempt 17 passed HR shift assignment and RM-001 adjustment; `locator.tap()` saw the RM-010 Main Warehouse option visible/stable, then auto-scrolling made it disappear and detach. Attempt 22 again passed HR shift assignment and RM-001 stock. For RM-010, the accessible Main Warehouse option was visible, its label was read, and `toBeInViewport()` passed, but the next `locator.boundingBox()` returned `null`. Attempt 24's CSS rectangle and actual touchscreen tap selected RM-001's Main Warehouse location and the combobox value assertion passed; the later confirmation submit failed separately under FND-PROD-001. RM-010 and PKG-001 remain untested with the corrected tap.
- **Evidence so far:** Attempt 17 logs `element is visible, enabled and stable`, then `scrolling into view`, `element is not visible`, and `element was detached`. Attempt 22's trace shows the exact portal option rendered and the viewport assertion passed, but the following Playwright bounds read returned `null`. Attempt 24 proves the CSS rectangle plus real tap works for RM-001. The original RM-010 selection issue is still not established as an application defect.
- **Correction / next check:** The test reads the exact accessible option's CSS rectangle in one locator evaluation, asserts a positive target size and an in-viewport center, sends a direct `page.touchscreen.tap()` at that center, and verifies the Location combobox value. Keep those assertions. Exercise RM-010 and PKG-001 only in a future approved fresh-baseline run; do not use API, forced DOM clicks, or keyboard selection to continue.
- **Evidence:** [Attempt 16 report and artifacts](runs/2026-11-05-PROD01-HAPPY-attempt-16-warehouse-location-tap.md); [attempt 17 report and artifacts](runs/2026-11-05-PROD01-HAPPY-attempt-17-touch-locator-autoscroll.md); [attempt 22 report and artifacts](runs/2026-11-05-PROD01-HAPPY-attempt-22-location-bounds-null.md); [attempt 23 environment report](runs/2026-11-05-PROD01-HAPPY-attempt-23-clock-endpoint-dns-failure.md); [attempt 24 report and artifacts](runs/2026-11-05-PROD01-HAPPY-attempt-24-submit-no-request.md); `spa/e2e-process/production-stock.floor.spec.ts`; `spa/src/components/ui/SearchableSelect.tsx`.
- **Status:** The locator/touch correction passed for RM-001 in attempt 24. RM-010 and PKG-001 remain unverified; attempt 24 stopped at the separate submission defect FND-PROD-001. No application code changed.

## Test and reporting issues

### TST-E2E-001 — Trace sanitizer missed and repeatedly reprocessed the E2E control header

- **Classification:** Test/reporting issue (E2E credential redaction; Medium)
- **Process / journey:** HR-08, `HR08-TERMINATION` attempt 03
- **Observed:** The Playwright trace and error report contained the `X-E2E-Control` header. The sanitizer did not initially classify that header as sensitive. After adding it, the first sanitize pass redacted values, but `--check` still counted six already-redacted header values because the regular expression backtracked over whitespace.
- **Impact:** Raw E2E traces and error dumps could retain the test-only control token or fail artifact checks. No production credential or production environment was involved.
- **Correction:** `scripts/e2e/sanitize_traces.py` now treats `X-E2E-Control` as sensitive and avoids matching `[REDACTED]` after whitespace. A regression test covers structured headers, raw header text, and idempotence. The attempt trace/context now pass the sanitizer check; the raw error dump was excluded from the archive.
- **Evidence:** [HR08-TERMINATION attempt 03 trace](runs/assets/hr08-termination-attempt-03-host-test-interruption/trace.zip); `scripts/e2e/sanitize_traces.py`; `scripts/e2e/test_sanitize_traces.py`.
- **Status:** Resolved in E2E artifact tooling. The five Python sanitizer unit tests passed on 2026-11-05 after a clean 300-second PHP-test-free window; the attempt trace/context also pass `--check`. No application code changed.

### TST-HR-001 — Employee process navigation began at the public home page

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Open employee profile / `employee`
- **Observed:** First, the test opened `/`, which is the public marketing site. After changing to the authenticated `/dashboard` landing route, the test still expected a “My profile” dashboard button that does not exist. The authenticated employee page exposes Profile through the topbar profile dropdown. No profile-change request was submitted in either attempt.
- **Correction:** Open `Profile menu for E2E employee` and select the visible `Profile` menu item. HR and Finance start at `/dashboard` and open their queues through the sidebar.
- **Evidence:** [first attempt](runs/2026-11-04-HR07-BANK-CHANGE-first-attempt.md), [second attempt](runs/2026-11-04-HR07-BANK-CHANGE-second-attempt.md), archived browser assets under `runs/assets/hr07-bank-change-first-attempt/` and `runs/assets/hr07-bank-change-second-attempt/`; `spa/src/components/layout/ProfileDropdown.tsx`.
- **Status:** Resolved in E2E navigation; this path passed in the seventeenth full-baseline HR-07 run.

### TST-HR-002 — Empty-state text locator matched responsive duplicates

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Confirm the initial HR review queue is empty / `hr_officer`
- **Observed:** The Employee profile and HR queue opened through their real UI routes. A text locator for “No requests” matched duplicate responsive empty-state headings and stopped the step before any request was submitted.
- **Correction:** Assert the visible accessible heading with `getByRole('heading', { name: 'No requests', exact: true })`.
- **Evidence:** [third-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-third-attempt.md).
- **Status:** Resolved in E2E locator; this assertion passed in the seventeenth full-baseline HR-07 run.

### TST-HR-003 — Read-only verifier treated computed HashID as a database column

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Verify the clean baseline / read-only E2E verifier
- **Observed:** Employee Profile and both reviewer queues opened through the UI. The baseline SQL referenced `profile_update_requests.hash_id`, but the model computes that public identifier from the integer primary key; the column does not exist. The failure preceded transaction submission.
- **Correction:** Find the unique transaction from this run by requester email, request note, and run start time; use the opaque ID returned by the UI only for subsequent UI review actions.
- **Evidence:** [fourth-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-fourth-attempt.md); `api/app/Common/Traits/HasHashId.php`.
- **Status:** Resolved in the read-only verifier; final database assertions passed in the seventeenth full-baseline HR-07 run.

### TST-HR-004 — Badge handoff assertion confused accessible description with visible label

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Follow HR's actionable badge / `hr_officer`
- **Observed:** Employee submitted the request, and HR's already-open queue received its `Pending HR` row and a count-1 badge. The assertion expected “Awaiting my action” in the badge trigger's accessible description; the trigger instead describes the signal as “Profile changes you can review,” while its popover link shows “Awaiting my action.” HR did not approve the request in this attempt.
- **Correction:** Match the badge trigger by destination label and count, then use the visible “Awaiting my action” link in its popover.
- **Evidence:** [fifth-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-fifth-attempt.md); `spa/src/components/layout/Sidebar.tsx`.
- **Status:** Resolved in E2E locators; the actionable HR hand-off passed in the seventeenth full-baseline HR-07 run.

### TST-HR-005 — E2E expected the intended badge query before confirming that the live link used it

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Follow HR's “Awaiting my action” sidebar signal / `hr_officer`
- **Observed:** The request was submitted through the employee UI, and HR saw the live pending row and count-1 badge. After selecting the visible badge-menu link, the test expected a `signal=awaiting_my_action` query that the live SPA did not add.
- **Correction:** Verify the actual profile-change queue route and require the employee's row to be present there. Track the missing filter separately as `UX-HR-001`; no application change is made.
- **Evidence:** [sixth-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-sixth-attempt.md); `Sidebar.tsx`; `Sidebar.behavior.test.tsx`.
- **Status:** Resolved in E2E expectation; the badge hand-off passed in the seventeenth full-baseline HR-07 run. The UI filter observation remains tracked separately as UX-HR-001.

### TST-HR-006 — Finance dashboard landing had no accessible heading

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Open Finance's bank-change queue / `finance_officer`
- **Observed:** Finance reached the authenticated dashboard shell with its sidebar and profile menu, but that role's dashboard exposed no heading. A generic first-heading assertion stopped before opening the queue; no transaction was created.
- **Correction:** Confirm the authenticated Modules navigation and profile menu, then use Finance's sidebar queue link.
- **Evidence:** [seventh-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-seventh-attempt.md).
- **Status:** Resolved in E2E navigation; Finance queue access passed in the seventeenth full-baseline HR-07 run.

### TST-HR-007 — Requested bank name matched desktop and mobile table markup

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Verify the expanded request / `hr_officer`
- **Observed:** Employee created the request through the UI, and HR found and expanded it through the queue. A page-wide bank-name locator matched both desktop and mobile responsive table content and stopped before approval.
- **Correction:** Scope the bank-name assertion to the visible “Requested changes” region.
- **Evidence:** [eighth-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-eighth-attempt.md); `spa/src/pages/hr/profile-update-requests/index.tsx`.
- **Status:** Resolved in E2E locator; request detail assertions passed in the seventeenth full-baseline HR-07 run.

### TST-HR-008 — HR badge invariant expected the popover link text in the trigger name

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Assert the live HR actionable badge / `hr_officer`
- **Observed:** The employee submitted the change through the UI, HR's open queue received the `Pending HR` row, and the badge count updated to 1. The invariant searched the badge trigger for “Awaiting my action,” which is the popover link text. The trigger's accessible name instead described the signal as “Profile changes you can review.” Read-only checks passed for the pending request, unchanged employee bank account, absent reviewers, and zero notifications. HR did not approve in this attempt.
- **Correction:** Match the trigger's rendered accessible description in the invariant. The hand-off helper continues to follow the separate “Awaiting my action” link.
- **Evidence:** [ninth-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-ninth-attempt.md); archived accessibility snapshot in `runs/assets/hr07-bank-change-ninth-attempt/playwright/error-context.md`; `spa/src/components/layout/Sidebar.tsx`.
- **Status:** Resolved in E2E test code; live HR badge assertions passed in the seventeenth full-baseline HR-07 run.

### TST-HR-009 — HR status assertion matched the filter option and responsive status chips

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Observe the first approval state / `hr_officer`
- **Observed:** Employee submitted the bank change through the UI and the HR queue/badge hand-off passed. HR approved through the UI; the review endpoint returned HTTP 200 and the page updated. The page-wide exact status locator matched the status filter option and two responsive “Awaiting Finance” chips, so read-only verification and the second Finance step did not run.
- **Correction:** Scope the HR assertion to the employee's request row and the Employee assertion to that request's list item. The Finance badge invariant also matches the actual trigger description.
- **Evidence:** [tenth-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-tenth-attempt.md); `spa/src/pages/hr/profile-update-requests/index.tsx`; `spa/src/pages/self-service/profile.tsx`; `BadgeService.php`.
- **Status:** Resolved in E2E test code; both approval-stage status assertions passed in the seventeenth full-baseline HR-07 run.

### TST-HR-010 — Masked bank account display did not expose the last four as standalone text

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Verify the final bank account / `finance_officer` and `employee`
- **Observed:** Employee submission, HR approval, Finance hand-off, and Finance approval all ran through the UI. The approval returned HTTP 200; Employee saw Approved, the new bank name, and a masked account value. The assertion searched for bare `7426`, while the rendered definition is `••••7426`; final read-only, audit, email, and notification verification did not run.
- **Correction:** Assert the masked definition contains the run-generated last four and continue asserting the full account number is not exposed.
- **Evidence:** [twelfth-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-twelfth-attempt.md); `spa/src/pages/self-service/profile.tsx` renders the masked account value.
- **Status:** Resolved in E2E test code; the masked account assertion passed in the seventeenth full-baseline HR-07 run.

### TST-HR-011 — Completed request was expected to disappear from an all-statuses list

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Verify completed request state / `finance_officer` and `hr_officer`
- **Observed:** Both approvals ran through their owning UI sessions. Employee saw Approved, the updated bank name, and the masked account value. The test expected the request row to disappear from both reviewer lists, which default to “All statuses” and retain completed records without their action buttons.
- **Correction:** Assert the request row shows Approved and neither role has its approval action. Continue checking each actionable badge falls to zero; run final database, audit, notification, and email assertions from a clean baseline.
- **Evidence:** [thirteenth-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-thirteenth-attempt.md); `spa/src/pages/hr/profile-update-requests/index.tsx`; `api/app/Modules/HR/Services/ProfileUpdateRequestService.php`.
- **Status:** Resolved in E2E test code; approved rows and removed actions passed in the seventeenth full-baseline HR-07 run.

### TST-HR-012 — Employee audit assertion expected plaintext sensitive fields

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Final employee record audit / `finance_officer`
- **Observed:** The fourteenth attempt found a Finance-authored Employee `updated` audit row with `bank_name` and `bank_account_no` keys, redacted as `***`. The fifteenth attempt's aggregate query returned zero before its row-level query ran.
- **Correction:** The verifier now queries Employee audit rows by seeded employee ID and run start time, accepts field keys from either old or new audit JSON, and requires a Finance actor with both keys. The source employee row separately verifies the new bank name and changed ciphertext.
- **Evidence:** [fourteenth-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-fourteenth-attempt.md), [fifteenth-attempt report and archived artifacts](runs/2026-11-04-HR07-BANK-CHANGE-fifteenth-attempt.md), and [passing seventeenth-attempt report with walkthrough assets](runs/2026-11-04-HR07-BANK-CHANGE-full-run.md); `api/app/Common/Traits/HasAuditLog.php`.
- **Status:** Resolved in E2E verifier; the corrected direct-row check passed in the seventeenth clean-baseline run.

### TST-HR-013 — Replacement request expansion assumed the rejected row was collapsed

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-REJECT-RESUBMIT`
- **Step / role:** Employee resubmits; HR locates the replacement / Employee and HR Officer
- **Observed:** HR rejected the first bank-change request through its own UI session. The Employee saw the rejection live and created a distinct replacement through Profile. Expanding the replacement row left the first request's details open too; the page-wide `Requested changes` locator matched both sections and failed in Playwright strict mode. The replacement was `pending` with no reviewer, and the Employee bank fields remained unchanged.
- **Correction:** Match the `Requested changes` section containing the unique run-scoped bank name and assert exactly one matching section. Preserve the current process/status assertions and continue only after a fresh baseline reset.
- **Evidence:** [first-attempt report, screenshots, and trace](runs/2026-11-04-HR07-REJECT-RESUBMIT-first-attempt.md); `spa/e2e-process/hr-profile.process.spec.ts`.
- **Status:** Resolved in E2E test code. A fresh-baseline run passed all six steps, including HR and Finance approvals of the replacement; the corrected locator selects exactly one requested-change region for the unique bank value. [Full run report and screenshots](runs/2026-11-04-HR07-REJECT-RESUBMIT-full-run.md). No application finding or app code change.

### TST-HR-014 — Salary-adjustment discovery expected the Modules sidebar on a blank page

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-SALARY-APPROVAL`
- **Step / role:** Begin HR UI discovery / `hr_officer`
- **Observed:** HR signed in through the login UI, but `actorPage` opened a new blank page. The journey expected the authenticated Modules navigation before sending the actor to the dashboard; Playwright timed out before UI discovery, and no business action or transaction ran.
- **Correction:** Begin at the role's post-login Dashboard, then navigate to Human Resources → Employees through the sidebar. Keep the salary-request UI absence assertions and GAP-UI-02 `test.fixme` unchanged.
- **Evidence:** [First-attempt report and archived trace/screenshots](runs/2026-11-04-HR07-SALARY-APPROVAL-first-attempt.md); `spa/e2e-process/hr-profile.process.spec.ts`.
- **Status:** Resolved in E2E test code. A fresh-baseline run reached the Dashboard and completed UI discovery through the employee edit screen and Salary Adjustments queue before stopping at GAP-UI-02. [Blocked run report and screenshot](runs/2026-11-04-HR07-SALARY-APPROVAL-blocked.md). No application code changed.

### TST-HR-015 — Compensation guidance locator did not account for the inline link

- **Classification:** Test issue
- **Process / journey:** HR-07, `HR07-SALARY-APPROVAL`
- **Step / role:** Inspect employee compensation entry point / `hr_officer`
- **Observed:** HR reached the Employee edit page through the Employees list and clicked Edit. The screenshot shows the compensation workflow guidance, but Playwright used an exact-text locator for a sentence split around an inline “Open salary adjustments” link. The salary queue was not inspected; no salary transaction was created.
- **Correction:** Match the visible compensation sentence with a web-first regular-expression assertion; keep the assertions that no pay input or request action is available.
- **Evidence:** [Second-attempt report and archived trace/screenshots](runs/2026-11-04-HR07-SALARY-APPROVAL-second-attempt.md), `spa/src/components/hr/EmployeeForm.tsx`, and `spa/e2e-process/hr-profile.process.spec.ts`.
- **Status:** Resolved in E2E test code. The regex locator matched the sentence split around the inline link; the fresh-baseline run completed edit-page and queue discovery before stopping at GAP-UI-02. [Blocked run report and screenshot](runs/2026-11-04-HR07-SALARY-APPROVAL-blocked.md). No application code changed.

### TST-HR-016 — E2E Employee baseline lacked opening leave entitlements

- **Classification:** Test/baseline issue (E2E-only fixture)
- **Process / journey:** HR-04, `HR04-APPROVE`
- **Step / role:** Baseline precondition before employee submission / `employee`
- **Observed:** The selected Employee, Department Head, and HR Officer signed in through the UI, but the read-only baseline query found no current-year Vacation Leave balance row for the seeded Employee. The journey stopped before opening the leave-request form; no process transaction or side effect was created.
- **Correction:** The gated `E2EAccountSeeder` now adds idempotent opening leave balances for the seeded `employee` actor, using active leave types' `default_balance` in the current Asia/Manila year. This supplies reference baseline data only; request creation and approvals remain UI actions.
- **Evidence:** [Baseline failure report, screenshot, and trace](runs/2026-11-04-HR04-APPROVE-baseline-attempt-01.md); `api/database/seeders/E2EAccountSeeder.php`; `docs/e2e/02-platform.md`.
- **Status:** Resolved in the E2E fixture; the subsequent fresh-baseline HR04 attempt passed the opening-entitlement and zero-request preconditions. No application behavior changed.

### TST-HR-017 — Exact required-field labels did not resolve in the leave request dialog

- **Classification:** Test issue
- **Process / journey:** HR-04, `HR04-APPROVE`
- **Step / role:** Select leave type and dates in new request / `employee`
- **Observed:** With the corrected fresh baseline, the Employee opened the Leave Request dialog and the screenshot showed the required Leave type and date controls. Exact-label `getByLabel()` locators did not resolve them. An anchored combobox name match selected the exact Vacation Leave option, but the exact Start date match still failed before form submission.
- **Correction:** Scope the controls to the visible request dialog and use anchored accessible-name matches for the required fields; keep the exact Vacation Leave option and run-specific dates.
- **Evidence:** [Second-attempt report and trace](runs/2026-11-04-HR04-APPROVE-attempt-02.md), `spa/src/pages/self-service/leave.tsx`, and `spa/src/components/ui/Select.tsx`.
- **Status:** Resolved in E2E test code. A clean-baseline rerun selected Vacation Leave, entered the requested dates/reason, and submitted through the UI. The next failure occurred after the request was created, at the separate status-label assertion. No application code changed.

### TST-HR-018 — Leave status assertions used enum labels instead of rendered queue labels

- **Classification:** Test expectation/source-label inconsistency (related UI label issue is UX-HR-003)
- **Process / journey:** HR-04, `HR04-APPROVE`
- **Step / role:** Verify the pending queue labels before Department Head and HR approval / `department_head`, `hr_officer`
- **Observed:** UI submission returned 201 and read-only assertions passed for `pending_dept`, 1.0 day, null approvers, unchanged balance/attendance, and both pending approval rows. The Employee detail rendered “Pending dept.” After the real sidebar hand-off, the Department Head queue also rendered “Pending dept”; the test still expected the enum label “Pending department head.” The HR queue's pending-HR row follows the same options-derived short label.
- **Correction:** Assert the observed per-view queue labels (“Pending dept”, “Pending hr”) and retain exact stored-status assertions (`pending_dept`, `pending_hr`).
- **Evidence:** [Fourth-attempt report](runs/2026-11-04-HR04-APPROVE-attempt-04.md), [eighth-attempt report](runs/2026-11-04-HR04-APPROVE-attempt-08.md), [ninth-attempt report](runs/2026-11-04-HR04-APPROVE-attempt-09.md); `api/app/Modules/Leave/Controllers/LeaveRequestController.php`; `api/app/Modules/Leave/Enums/LeaveRequestStatus.php`; `spa/src/pages/leaves/index.tsx`.
- **Status:** Resolved in E2E test code. The complete fresh-baseline run rendered “Pending dept” in Employee/Department Head views and “Pending hr” in HR's queue; the source-level label difference remains logged under UX-HR-003.

### TST-HR-019 — Leave badge locator did not match the real sidebar controls

- **Classification:** Test issue
- **Process / journey:** HR-04, `HR04-APPROVE`
- **Step / role:** Department Head finds the pending leave / `department_head`
- **Observed:** Employee submission and recipient assertions passed. The already-open Department Head dashboard showed one Pending Approvals row for the leave request and the collapsed Human Resources section showed a badge count of 1. The initial test searched for a visible “Leaves signals” button without expanding the section. After expanding and retaining the section with hover, the visible Leave Management item displayed count 1, but the exact button locator still assumed its signal label (“Leaves”) was in the accessible name. The source composes that name from the signal description (“1 Leave requests awaiting your approval”).
- **Correction:** Expand Human Resources through its actual button and hover the Leave Management link to keep the temporary section open. Match `View all Leave Management signals: 1 Leave requests awaiting your approval`, then follow the visible `Leave requests awaiting your approval: 1` link. Repeat for each actor's hand-off.
- **Evidence:** [Fifth-attempt report](runs/2026-11-04-HR04-APPROVE-attempt-05.md), [sixth-attempt report](runs/2026-11-04-HR04-APPROVE-attempt-06.md), [seventh-attempt report](runs/2026-11-04-HR04-APPROVE-attempt-07.md), and [full run](runs/2026-11-04-HR04-APPROVE-full-run.md); `spa/src/components/layout/Sidebar.tsx`; `api/app/Modules/Dashboard/Services/BadgeService.php`.
- **Status:** Resolved in E2E navigation. The full fresh-baseline run followed each actor's sidebar signal dialog and actionable queue link to the unique request row, verified actions and approvals, and saw both hand-offs update in the already-open sessions.

### TST-HR-021 — Department Head queue assertion expected a completed item to remain actionable

- **Classification:** Test issue
- **Process / journey:** HR-04, `HR04-APPROVE`
- **Step / role:** Verify the Department Head hand-off after approval / `department_head`
- **Observed:** The Department Head approved through the UI confirmation dialog. The stored record advanced to `pending_hr` with the correct Department Head actor, and the Employee detail showed `Pending hr` with the second approval step pending. The test incorrectly expected the same `awaiting_my_approval` row to remain visible with its new status. The failure screenshot showed that queue at zero requests.
- **Correction:** Assert that the request leaves the Department Head's actionable queue. Continue asserting `pending_hr` and actor attribution through the database reader, Employee live detail, HR badge/queue, and HR approval rows.
- **Evidence:** [Ninth-attempt report and archived trace/screenshots](runs/2026-11-04-HR04-APPROVE-attempt-09.md); read-only E2E database query returned `pending_hr`, `department_head` approved, and `hr_officer` pending; `LeaveApprovalPolicy::awaitingMyApprovalQuery()`; `LeaveRequestService::approveDept()`; `spa/src/pages/leaves/index.tsx`.
- **Status:** Resolved in E2E test code. The fresh-baseline full run confirmed the request leaves the Department Head queue, then reached HR through its badge and completed the final approval.

### TST-HR-020 — Human Resources sidebar peek closed before badge lookup

- **Classification:** Test issue
- **Process / journey:** HR-04, `HR04-APPROVE`
- **Step / role:** Keep Department Head sidebar hand-off visible / `department_head`
- **Observed:** The test expanded Human Resources and observed `aria-expanded=true`, but its sidebar subsection auto-collapsed before the badge lookup. Source sets a five-second temporary expansion and keeps it while a contained link is hovered or focused. The screenshot shows Human Resources collapsed with its count of 1 and the dashboard's live Pending Approvals row.
- **Correction:** Hover the visible Leave Management link after expanding the section and repeat this interaction immediately before each actor's badge lookup. Continue to require the exact badge, count, signal link, and queue URL.
- **Evidence:** [Sixth-attempt report, screenshot, and trace](runs/2026-11-04-HR04-APPROVE-attempt-06.md); `spa/src/components/layout/Sidebar.tsx` (`toggleModule` temporary expansion timer and `NavLink` signal menu).
- **Status:** Resolved in E2E navigation. The full fresh-baseline run kept Human Resources expanded while following both role-specific badges; no timing sleep was used.

### TST-HR-022 — Rejection journey checked the badge before reopening Human Resources

- **Classification:** Test navigation issue
- **Process / journey:** HR-04, `HR04-REJECT-DEPT`
- **Step / role:** Department Head finds the pending leave to reject / `department_head`
- **Observed:** Employee creation and exact submission-recipient checks passed. Department Head's already-open dashboard displayed the pending request live and the collapsed Human Resources section showed badge count 1. The test polled for the expanded section's signal button without reopening the temporary sidebar section; it timed out before the Department Head opened the queue or acted.
- **Correction:** Reopen Human Resources and hover Leave Management before locating the exact signal button and following its queue link.
- **Evidence:** [Department Head rejection attempt 01 and archived screenshots/trace](runs/2026-11-04-HR04-REJECT-DEPT-attempt-01.md); `spa/src/components/layout/Sidebar.tsx`; `spa/e2e-process/hr-leave.process.spec.ts`.
- **Status:** Resolved in E2E navigation. The fresh-baseline Department Head rejection run reopened Human Resources before looking for the signal; it followed the badge into the queue and completed the UI rejection. [Full report and screenshots](runs/2026-11-04-HR04-REJECT-DEPT-full-run.md).

### TST-HR-023 — HR rejection journey searched for the signal while Human Resources was collapsed

- **Classification:** Test navigation issue
- **Process / journey:** HR-04, `HR04-REJECT-HR`
- **Step / role:** Department Head finds the pending request and hands it to HR / `department_head`
- **Observed:** Employee submission and the exact `leave.submitted` recipient check passed. The Department Head dashboard showed the request in Pending Approvals and the sidebar badge count was 1, but the test searched for the nested signal button while Human Resources was collapsed. No approval action occurred in attempt 01.
- **Correction:** Reuse `openLeaveApprovalFromBadge()` immediately before the hand-off. It expands Human Resources, hovers Leave Management, follows the exact signal button and opens the actionable queue.
- **Evidence:** [Attempt 01 with screenshots and trace](runs/2026-11-04-HR04-REJECT-HR-attempt-01.md); `spa/e2e-process/hr-leave.process.spec.ts`; `spa/src/components/layout/Sidebar.tsx`.
- **Status:** Resolved in E2E test code. The clean-baseline run passed all three UI steps, including Department Head approval and HR rejection via separate badge queues. [Full report and walkthrough](runs/2026-11-04-HR04-REJECT-HR-full-run.md).

### TST-HR-024 — Cancellation journey helper widened a stage value to string

- **Classification:** Test issue
- **Process / journey:** HR-04, `HR04-CANCEL-PENDING-DEPT` preflight
- **Step / role:** TypeScript compile before actor setup
- **Observed:** The guarded runner rejected the new test file because `currentStatus()` returned `string` where the notification invariant required `LeaveCancellationStage`. Playwright did not start, no role logged in, and no transaction was created.
- **Correction:** Narrowed the helper return type to `LeaveCancellationStage`.
- **Evidence:** [Preflight report](runs/2026-11-04-HR04-CANCEL-PENDING-DEPT-preflight-01.md); successful `npm run typecheck:e2e` after the correction.
- **Status:** Resolved in E2E test code; `HR04-CANCEL-PENDING-DEPT` subsequently ran and exposed FND-HR-001.

### TST-HR-025 — Approved cancellation invariant counted a soft-deleted attendance row

- **Classification:** Test issue
- **Process / journey:** HR-04, `HR04-CANCEL-APPROVED`
- **Step / role:** Read-only attendance invariant after Employee cancellation
- **Expected:** No active `on_leave` attendance row remains after cancellation.
- **Observed:** The request cancelled through the UI, the leave balance was restored, and `LeaveRequestService::unmarkAttendance()` soft-deleted the exact leave-created row. The read-only query counted it because it omitted `attendance.deleted_at IS NULL`, so the assertion reported one row instead of zero. A direct read-only DB check found `deleted_at` set to the cancellation timestamp and the request's leave marker intact on the soft-deleted record.
- **Correction:** The E2E helper now counts active rows only by filtering `deleted_at IS NULL`; the no-active-attendance expectation is unchanged. The row remains available as soft-delete history.
- **Evidence:** [first attempt report](runs/2026-11-04-HR04-CANCEL-APPROVED-attempt-01.md); `spa/e2e-process/hr-leave.process.spec.ts`; `api/app/Modules/Leave/Services/LeaveRequestService.php:705-775`.
- **Status:** Resolved in E2E test code after the clean-baseline approved-cancellation run passed the active-attendance invariant. [Full run](runs/2026-11-04-HR04-CANCEL-APPROVED-full-run.md).

### TST-HR-026 — Leave notification assertion raced the queued listener

- **Classification:** Test issue (async checkpoint)
- **Process / journey:** HR-04, `HR04-CANCEL-APPROVED` clean-baseline attempt 02
- **Step / role:** Employee submission; exact `leave.submitted` audience assertion
- **Expected:** Wait until the queued notification listener persists the expected notification types, then assert exact types, recipients and links.
- **Observed:** The immediate read-only query ran before `NotifyOnLeaveSubmitted` completed, so the test saw an empty list and stopped after Employee submission. A later read-only query found the expected `leave.submitted` notice for the Department Head; the queue log shows `NotifyOnLeaveSubmitted` running and completing after the invariant had already failed. No wrong recipient or missing persisted notification was found.
- **Correction:** `noticeTypes()` now uses bounded `expect.poll` on read-only notification rows until the expected types are persisted, then performs the unchanged exact type/recipient/link assertions. This waits on the async condition and adds no fixed sleep.
- **Evidence:** [attempt 02 report](runs/2026-11-04-HR04-CANCEL-APPROVED-attempt-02.md), [queue timing excerpt](runs/assets/hr04-cancel-approved-attempt-02/queue-notification.log), [persisted notification snapshot](runs/assets/hr04-cancel-approved-attempt-02/notification-state.json), and `spa/e2e-process/hr-leave.process.spec.ts`.
- **Status:** Resolved in E2E test code after the clean-baseline run passed the queue-backed notice and exact-recipient assertions. [Full run](runs/2026-11-04-HR04-CANCEL-APPROVED-full-run.md).

### TST-HR-027 — Leave route heading timeout was shorter than the cold lazy-bundle load

- **Classification:** Test issue (bounded route-ready timeout)
- **Process / journey:** HR-04, `HR04-CANCEL-APPROVED` clean-baseline attempt 03
- **Step / role:** Employee opens Profile → Leaves, before the first process step
- **Expected:** The Leaves page lazy bundle and data load, then the exact “My Leave Requests” heading becomes visible.
- **Observed:** The browser reached `/self-service/leaves` but the main region remained empty at the 8-second locator timeout. The trace shows `leaves-Cj53DmgJ.js` loaded successfully in 8.38 seconds; the route's API requests were still in flight and were logged as HTTP 499 after Playwright closed the context. No record was created and no application 5xx occurred.
- **Correction:** `openSelfServiceLeaves()` now uses the same exact heading assertion with a 20-second bounded web-first timeout, based on the measured cold route load. No fixed sleep or product assertion change was added.
- **Evidence:** [attempt 03 report, screenshot and trace](runs/2026-11-04-HR04-CANCEL-APPROVED-attempt-03.md); `spa/e2e-process/hr-leave.process.spec.ts`.
- **Status:** Resolved in E2E test code; the Leaves page rendered and the approved-cancellation journey passed with the bounded heading assertion. [Full run](runs/2026-11-04-HR04-CANCEL-APPROVED-full-run.md).

### TST-HR-028 — Insufficient-balance estimate locator omitted its unit

- **Classification:** Test issue
- **Process / journey:** HR-04, `HR04-INSUFFICIENT-BALANCE` clean-baseline attempt 01
- **Step / role:** Employee checks the estimated leave days before submission
- **Expected:** The UI working-day estimate equals the read-only server calendar calculation and exceeds the Employee's remaining leave balance.
- **Observed:** The UI rendered `16 days` against a `15.0 / 15.0` balance, matching the server-derived 16-day interval. The locator searched for an exact text node `16`, so the journey stopped before submission. No transaction was created.
- **Correction:** Match the complete rendered label, including the singular/plural unit, and preserve the estimate comparison. Rerun from a fresh baseline.
- **Evidence:** [attempt 01 report and trace](runs/2026-11-04-HR04-INSUFFICIENT-BALANCE-attempt-01.md); [form screenshot](runs/assets/hr04-insufficient-balance-attempt-01/leave-form-estimate.png); `spa/e2e-process/hr-leave.process.spec.ts`.
- **Status:** Resolved in test code in attempt 02: the estimate locator matched “16 days” and the journey reached the UI submission and post-action checks.

### TST-HR-029 — Expected 422 resource error failed page diagnostics

- **Classification:** Test issue (expected negative response)
- **Process / journey:** HR-04, `HR04-INSUFFICIENT-BALANCE` clean-baseline attempt 02
- **Step / role:** Employee submits an over-balance request / `employee`
- **Expected:** The server returns a specific HTTP 422 business-rule response, and unrelated console/network errors still fail the step.
- **Observed:** After the UI POST returned 422 with the expected insufficient-balance message, Chromium emitted a console resource-error message for that response. The general console monitor treated it as unexpected even though the status and body assertions and all read-only no-side-effect assertions passed.
- **Correction:** Declare one exact console message fragment immediately before this single expected UI submission. A different status, message, route, or additional console error remains reportable.
- **Evidence:** [attempt 02 report and trace](runs/2026-11-04-HR04-INSUFFICIENT-BALANCE-attempt-02.md); `spa/e2e-process/helpers/page-diagnostics.ts`; `spa/e2e-process/hr-leave.process.spec.ts`.
- **Status:** Resolved in E2E test code; the final clean-baseline run passed with the exact expected 422 allowance and all remaining diagnostics active. [Full run](runs/2026-11-04-HR04-INSUFFICIENT-BALANCE-full-run.md).

### TST-HR-030 — Time-in result locator matched the live announcement, result and toast

- **Classification:** Test issue
- **Process / journey:** HR-03, `HR03-PUNCH` attempt 01
- **Step / role:** Employee time-in / `employee`
- **Expected:** Assert that the visible success result reports the punched time and geofence distance.
- **Observed:** The UI POST returned HTTP 201 and the page displayed the expected 6:00 AM result. The prefix locator also matched the screen-reader announcement and toast, causing a Playwright strict-mode violation before read-only invariants ran.
- **Correction:** Match the complete visible sentence, including the distance and plant-centre suffix, for both time-in and time-out. This keeps the expected UI content and uniqueness check intact.
- **Evidence:** [attempt 01 report](runs/2026-11-04-HR03-PUNCH-attempt-01.md); [passing run and walkthrough](runs/2026-11-04-HR03-PUNCH-full-run.md); `spa/e2e-process/hr-attendance.process.spec.ts`.
- **Status:** Resolved in E2E assertions; the complete clean-baseline journey passed.

### TST-HR-031 — Read-only DTR snapshot shifted local timestamps by eight hours

- **Classification:** Test issue
- **Process / journey:** HR-03, `HR03-PUNCH` attempt 02
- **Step / role:** Employee time-in / `employee`
- **Expected:** Compare database DTR wall-clock values to the UI's Asia/Manila values.
- **Observed:** The verifier applied `AT TIME ZONE 'Asia/Manila'` to schema columns declared as `timestamp without time zone`. The row stored 06:00 local, while the conversion returned 22:00 on the previous date. The UI itself displayed the correct 06:00 value.
- **Correction:** Format the stored local timestamps directly in the read-only snapshot query. No application behavior or process data was altered.
- **Evidence:** [attempt 02 report](runs/2026-11-04-HR03-PUNCH-attempt-02.md); attendance schema migrations `0024_create_attendances_table.php` and `0570_create_attendance_punches_table.php`; [passing run](runs/2026-11-04-HR03-PUNCH-full-run.md); `spa/e2e-process/hr-attendance.process.spec.ts`.
- **Status:** Resolved in the verifier; the passing run confirmed local 06:00 and 14:00 timestamps, payroll hours and the Employee DTR.

### TST-HR-032 — Missing-punch reload action returned a Playwright response

- **Classification:** Test issue (preflight)
- **Process / journey:** HR-03, `HR03-MISSING-PUNCH` preflight attempt 01
- **Step / role:** TypeScript compilation before actor setup
- **Observed:** The reload step passed `page.reload()` directly as an action, returning `Promise<Response | null>` where the process runner requires `Promise<void>`. Playwright did not start; no actors logged in and no record was created.
- **Correction:** Awaited the reload in a block-bodied async action.
- **Evidence:** [preflight report](runs/2026-11-04-HR03-MISSING-PUNCH-preflight-01.md); guarded runner log in [archived assets](runs/assets/hr03-missing-punch-preflight-01/runner.log).
- **Status:** Resolved in E2E test code; the next invocation passed TypeScript preflight.

### TST-HR-033 — Missing-time-out filter locator matched hidden and sidebar controls

- **Classification:** Test issue (locator)
- **Process / journey:** HR-03, `HR03-MISSING-PUNCH` attempt 02
- **Step / role:** HR selects the Missing time-out filter / `hr_officer`
- **Observed:** `getByLabel('Time out')` matched five elements, including two attendance sidebar signal buttons, a hidden native select, the visible searchable combobox and its Show button. The Employee time-in step had passed; HR did not submit a correction.
- **Correction:** Target the visible `combobox` by its exact accessible name and choose the exact `Missing time-out` option.
- **Evidence:** [attempt report](runs/2026-11-04-HR03-MISSING-PUNCH-attempt-02.md), [retained annotated screenshot](runs/assets/hr03-missing-punch-attempt-02/), and `spa/src/components/ui/FilterBar.tsx` / `spa/src/components/ui/SearchableSelect.tsx`. The trace and error-context files were not retained; see TST-INV-012.
- **Status:** Resolved in the E2E locator; attempt 03/05 verified the filter and correction path. The attempt-02 diagnostic artifact loss remains tracked by TST-INV-012.

### TST-HR-034 — Server and browser clocks differed during missing-time-out rendering

- **Classification:** Test issue (time setup)
- **Process / journey:** HR-03, `HR03-MISSING-PUNCH` attempt 03
- **Step / role:** HR inspects the prior-day missing-punch row / `hr_officer`
- **Observed:** The guarded API clock was on 2026-11-06 and returned the 2026-11-05 row through the Missing time-out filter. The browser still used its host date, 2026-11-04 in Manila, so the client-side `row.date < today` check displayed an em dash instead of “Missing.” No HR correction was submitted.
- **Correction:** Set the Employee and HR pages' fixed browser dates to the same Asia/Manila instants as the guarded API clock. Fixed browser time does not pause timers.
- **Evidence:** [attempt report and artifacts](runs/2026-11-04-HR03-MISSING-PUNCH-attempt-03.md); `spa/src/pages/attendance/index.tsx` computes the chip against client `localToday()`, while `AttendanceService::listQuery()` compares with server `now()`.
- **Status:** Resolved in test clock setup; the next clean-baseline run must verify the UI result.

### TST-HR-035 — Correction assertion kept a row inside the missing-time-out filter

- **Classification:** Test issue (expected list semantics)
- **Process / journey:** HR-03, `HR03-MISSING-PUNCH` attempt 04
- **Step / role:** HR saves the correction / `hr_officer`
- **Observed:** The Employee attendance was corrected through the UI and read-only DB checks confirmed time-out 14:00, 7.50 regular hours, and the HR Officer audit actor. The test then expected “2:00 PM” inside a list whose active filter requires a missing time-out.
- **Correction:** Assert that the row leaves the Missing time-out list, while retaining read-only status, time, hours and audit invariants. The next fresh-baseline run will establish whether the UI list applies its successful filtered GET.
- **Evidence:** [attempt report and artifacts](runs/2026-11-04-HR03-MISSING-PUNCH-attempt-04.md); the archived trace shows the `PUT` succeeded and the following filtered `GET` returned zero rows.
- **Status:** Resolved. The clean-baseline attempt 05 confirmed the corrected record leaves the active Missing time-out filter; the independent Employee DTR live-refresh defect is recorded as FND-HR-002.

### TST-HR-041 — Global Mailpit comparison included unrelated recipients (resolved)

- **Classification:** E2E assertion-scope issue
- **Process / journey:** HR-03, HR03-MISSING-PUNCH attempts 06–08
- **Step / role:** Final notification/email invariants / HR Officer and Employee
- **Observed:** Attempt 06 used existing E2E volumes and found one pre-existing attendance notification. After a clean E2E reset, attempt 07 completed the HR correction and the Employee's live DTR update, then failed because the journey compared all Mailpit message IDs. Twelve new critical-stock alert emails to unrelated seeded recipients were included; none was addressed to the Employee or HR actors. The reload step was consequently blocked.
- **Correction:** The journey snapshots only Mailpit message IDs addressed to its two actors. This keeps the no-email invariant for those users while ignoring messages to unrelated recipients.
- **Evidence:** [attempt 06](runs/2026-11-04-HR03-MISSING-PUNCH-attempt-06.md), [attempt 07](runs/2026-11-04-HR03-MISSING-PUNCH-attempt-07.md), [passing attempt 08](runs/2026-11-04-HR03-MISSING-PUNCH-attempt-08.md), and the actor-scoped assertion in spa/e2e-process/hr-attendance.process.spec.ts.
- **Status:** Resolved in test code; attempt 08 passed and verified no new email to either journey actor.

### TST-HR-036 — Overtime journey audit assertion rejected nullable actor email

- **Classification:** Test issue (TypeScript preflight)
- **Process / journey:** HR-03, `HR03-OVERTIME-APPROVE` preflight attempt 01
- **Step / role:** Runner compilation before actor setup
- **Observed:** The guarded Playwright runner's TypeScript compile rejected `OvertimeAuditRow.actor_email` (`string | null`) as input to the shared `assertAuditActor` helper (`string | undefined`). No browser context opened, no actor signed in, and no business record was created.
- **Correction:** Compare the read-only audit actor value directly to the expected email. A null actor still fails the equality assertion.
- **Evidence:** [preflight report](runs/2026-11-04-HR03-OVERTIME-APPROVE-preflight-01.md); `spa/e2e-process/hr-attendance.process.spec.ts`.
- **Status:** Resolved in E2E test code. The nullable actor comparison now fails on null; the business journey still requires a fresh-baseline run.

### TST-HR-037 — Nullable-audit correction removed an existing punch assertion import

- **Classification:** Test issue (Playwright spec compilation)
- **Process / journey:** HR-03, `HR03-OVERTIME-APPROVE` preflight attempt 02; regression surfaced in the existing `HR03-PUNCH` assertions
- **Step / role:** Runner spec compilation before auth setup
- **Observed:** The TST-HR-036 correction removed `assertAuditActor` from imports even though `HR03-PUNCH` still calls it twice. Playwright stopped on two unresolved references; no browser context opened and no business record was created.
- **Correction:** Restore the shared import and keep direct nullable actor-email comparisons only for the new overtime audit rows.
- **Evidence:** [preflight attempt 02 report](runs/2026-11-04-HR03-OVERTIME-APPROVE-preflight-02.md); `spa/e2e-process/hr-attendance.process.spec.ts:611,661`.
- **Status:** Resolved in E2E test code; a fresh-baseline run is still required for the overtime journey.

### TST-HR-038 — Overtime empty state exposed two identically named apply buttons

- **Classification:** Test issue (locator)
- **Process / journey:** HR-03, `HR03-OVERTIME-APPROVE` attempt 01
- **Step / role:** Employee opens the request form / `employee`
- **Observed:** The self-service overtime page displayed both its header “Apply for OT” button and the empty-state “Apply for OT” button. The exact-name locator matched two elements and strict mode stopped before either control was clicked; the request form never opened and no record was created.
- **Correction:** Scope the click to the header action and assert it resolves to exactly one enabled control. Keep the duplicate accessible name visible as a UI fact; do not make the assertion ambiguous or weaken it.
- **Evidence:** [attempt 01 report](runs/2026-11-04-HR03-OVERTIME-APPROVE-attempt-01.md), [sanitized trace and screenshots](runs/assets/hr03-overtime-approve-attempt-01/), and `spa/src/pages/self-service/overtime.tsx` header and empty-state actions. Attempt 02 passed the scoped locator and submitted the request through the UI.
- **Status:** Resolved in E2E test code; no application change was made.

### TST-HR-039 — Read-only badge check lacked Sanctum's same-origin request headers

- **Classification:** Test issue (API verification helper)
- **Process / journey:** HR-03, `HR03-OVERTIME-APPROVE` attempt 02
- **Step / role:** Final badge-count invariant / `department_head`
- **Observed:** Employee submission and Department Head approval completed through the UI; the open Employee view showed Approved without refresh. The `BrowserContext.request` GET to `/api/v1/dashboards/badges` returned 401 because the helper omitted the active browser page's `Origin` and `Referer` headers used by Sanctum's stateful session check. Read-only database evidence confirmed `approved`, Department Head approver role, two audit rows, and exactly the intended Employee/Department Head notifications. The badge-count API assertion and final diagnostic assertions did not run.
- **Correction:** Derive `Origin` and `Referer` from the current same-origin page and include the SPA's `Accept: application/json` and `X-Requested-With: XMLHttpRequest` headers in read-only and negative API requests. This is test-only code; it does not change authorization or application behavior.
- **Evidence:** [attempt 02 report](runs/2026-11-04-HR03-OVERTIME-APPROVE-attempt-02.md), [sanitized trace and screenshots](runs/assets/hr03-overtime-approve-attempt-02/), `spa/e2e-process/helpers/api-reader.ts`, and the read-only approval snapshot described in the report.
- **Status:** Resolved in E2E helper. Attempt 05 passed the badge-count assertion against the same-origin endpoint and completed the final diagnostic assertions.

### TST-HR-040 — Overtime nested sidebar signal locator was unavailable in repeated attempts

- **Classification:** Test issue (navigation helper)
- **Process / journey:** HR-03, `HR03-OVERTIME-APPROVE` attempts 03 and 04
- **Step / role:** Department Head finds the actionable work / `department_head`
- **Observed:** Employee submission and notification-recipient checks passed. The Department Head dashboard showed the expected Human Resources badge summary and a Pending Approvals row linking to the overtime record. Attempt 03 stopped while the section was collapsed. In attempt 04 the trace records the section opening and the Overtime link being hovered, but the nested `View all Overtime signals` locator still did not resolve before timeout; the final screenshot showed the section collapsed again. Neither attempt sent an approval action.
- **Correction:** The journey helper asserts the accessible Human Resources badge summary and follows the dashboard's linked Pending Approvals row in the Department Head's own session. Attempt 05 passed through approval and all final read-only invariants via that UI hand-off. The sidebar sub-menu itself remains unverified; the dashboard row is the tested process hand-off.
- **Evidence:** [attempt 03 report and artifacts](runs/2026-11-04-HR03-OVERTIME-APPROVE-attempt-03.md), [attempt 04 report and artifacts](runs/2026-11-04-HR03-OVERTIME-APPROVE-attempt-04.md), [attempt 05 report and walkthrough](runs/2026-11-04-HR03-OVERTIME-APPROVE-attempt-05.md), and `spa/e2e-process/hr-attendance.process.spec.ts`.
- **Status:** Resolved in E2E navigation; no product finding is assigned because the dashboard provides a visible linked approval hand-off.

### TST-HR-042 — Overtime live-update screenshot was captured before the assertion

- **Classification:** Test reporting issue
- **Process / journey:** HR-03, `HR03-OVERTIME-APPROVE` attempt 05
- **Step / role:** Capture the Employee's live-updated list / `employee`
- **Observed:** The auto-retrying assertion passed after the open Employee list changed to Approved, but the annotated screenshot was captured immediately before that assertion and therefore shows the intermediate Pending state.
- **Correction:** Move the screenshot capture after the Approved status and action-absence assertions. This does not alter the transition or loosen the live-update check.
- **Evidence:** [attempt 05 report and screenshot](runs/2026-11-04-HR03-OVERTIME-APPROVE-attempt-05.md), [fresh-baseline full run and corrected screenshot](runs/2026-11-04-HR03-OVERTIME-APPROVE-full-run.md), `spa/e2e-process/hr-attendance.process.spec.ts`.
- **Status:** Resolved in E2E test code. Full-run screenshot 003 is captured after the successful live Approved assertion and displays the approved row.

### TST-HR-043 — Training journey opened the sidebar before loading the authenticated shell

- **Classification:** Test issue (session initialization)
- **Process / journey:** HR-07, `HR07-TRAINING-COMPLETE` attempt 01
- **Step / role:** Open Training Programs / `hr_officer`
- **Observed:** `actorPage` created the authenticated HR context with a blank page. The journey tried to locate `/hr/trainings` under Modules before loading the ERP shell, so the sidebar link count was zero. The failure occurred before opening the form or sending any request; no course or training assignment was created, and the remaining three steps were blocked.
- **Correction:** Load the authenticated `/dashboard` landing destination and assert the Modules navigation before opening Training Programs through the sidebar. A clean-baseline confirmation run is pending.
- **Evidence:** [attempt 01 report](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-01.md), [trace, journey context, screenshots, and error context](runs/assets/hr07-training-complete-attempt-01/), and `spa/e2e-process/hr-training.process.spec.ts`.
- **Status:** Resolved in E2E test code. Attempt 02 reached the Add training heading through the authenticated dashboard and sidebar before an unrelated schema interruption stopped the step; see [environment report](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-02-environment-interruption.md). No application code changed.

### TST-HR-044 — Required training Name label did not match the exact locator

- **Classification:** Test issue (accessible-label locator)
- **Process / journey:** HR-07, `HR07-TRAINING-COMPLETE` attempt 03
- **Step / role:** Enter course master data / `hr_officer`
- **Observed:** HR opened the Add training page through the sidebar, and the required Name field was visible. `getByLabel('Name', { exact: true })` timed out before filling the field; no form submission or business record creation occurred.
- **Correction:** Match only the Name label with its optional required marker (`/^Name\\s*\\*?$/`) while preserving a field-specific accessible-label locator.
- **Evidence:** [attempt 03 report](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-03.md), [trace and screenshots](runs/assets/hr07-training-complete-attempt-03/), and `spa/src/components/ui/Input.tsx` (the required marker is rendered in the associated label).
- **Status:** Resolved in E2E test code; attempt 04 passed the Name-field locator from a fresh baseline. Attempt 04's separate audit assertion issue is TST-HR-045. No application code changed.

### TST-HR-045 — Course audit assertion expected a database-defaulted field

- **Classification:** Test issue (audit invariant expectation)
- **Process / journey:** HR-07, `HR07-TRAINING-COMPLETE` attempt 04
- **Step / role:** Verify course creation side effects / `hr_officer`
- **Observed:** The UI created the course successfully, and read-only verification found `is_active = true`, 12-month validity, audit action `created`, and the correct HR actor. The test also expected `audit_logs.new_values->>'is_active' = 'true'`; the audit snapshot returned null for that field. `HasAuditLog` snapshots the Eloquent model's current attributes on creation, while `is_active` is supplied by the database default.
- **Correction:** Assert the active state on the course row and assert the audit action/actor independently; do not require the database-defaulted flag in the audit payload.
- **Evidence:** [attempt 04 report](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-04.md), [trace and screenshots](runs/assets/hr07-training-complete-attempt-04/), `api/app/Common/Traits/HasAuditLog.php`, and `api/app/Modules/HR/Models/Training.php`.
- **Status:** Resolved in E2E test code; attempt 05 passed course creation, active-state, and audit action/actor assertions from a fresh baseline. The next step stopped at a separate employee-heading expectation, TST-HR-046. No application code changed.

### TST-HR-046 — Employee detail heading expectation retained baseline whitespace

- **Classification:** Test issue (reference-data display normalization)
- **Process / journey:** HR-07, `HR07-TRAINING-COMPLETE` attempt 05
- **Step / role:** Open target Employee detail / `hr_officer`
- **Observed:** HR found and opened the seeded Employee from the Employees list. The baseline has `last_name = "department head  hr"`; the detail heading renders it with repeated whitespace collapsed to `E2E department head hr`. Trimming only the name components' edges did not normalize the internal whitespace, so attempt 06 repeated the locator failure before Trainings or assignment.
- **Correction:** Trim and collapse repeated whitespace within each baseline name component before joining them, while retaining the exact heading assertion.
- **Evidence:** [attempt 05 report](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-05.md), [attempt 06 report](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-06.md), [attempt 07 report](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-07.md), and the read-only E2E baseline Employee row.
- **Status:** Resolved in E2E test code. Attempt 07's page snapshot confirmed the whitespace-normalized name as `E2E department head hr`; a separate status chip was included in the accessible heading and is tracked by TST-HR-047. No application code changed.

### TST-HR-047 — Employee detail heading locator omitted the status chip

- **Classification:** Test issue (accessible-name assertion)
- **Process / journey:** HR-07, `HR07-TRAINING-COMPLETE` attempt 07
- **Step / role:** Open target Employee detail / `hr_officer`
- **Observed:** HR found and opened the correct seeded Employee through the Employees table. The test expected an exact heading name containing only `E2E department head hr`, while the page's accessible heading includes the visible `Active` status chip: `E2E department head hr Active`. The test stopped before the Trainings tab or assignment action.
- **Correction:** Keep the exact accessible-name assertion and include the displayed status (`${employeeDisplayName} Active`). No selector was loosened and no application code changed.
- **Evidence:** [attempt 07 report, screenshots, trace, and journey context](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-07.md); `spa/src/pages/hr/employees/detail.tsx:210-214`.
- **Status:** Resolved in E2E test code. Attempt 10 passed the exact heading assertion, opened the correct employee detail, and reached the Assign training dialog. The later submit button locator failure is tracked by TST-HR-048. No application code changed.

### TST-HR-048 — Training assignment submit locator used the dialog title

- **Classification:** Test issue (accessible button locator)
- **Process / journey:** HR-07, `HR07-TRAINING-COMPLETE` attempt 10
- **Step / role:** Assign training to an Employee / `hr_officer`
- **Observed:** The named `Assign training` dialog opened, the run-scoped course and scheduled date were selected, and the exact button locator `Assign training` did not match. The visible dialog submit button is named `Assign`; the test timed out before clicking and sent no employee-training POST.
- **Correction:** Scope to the named dialog and click its exact `Assign` button. No application code changed.
- **Evidence:** [attempt 10 report, failure screenshots, trace, and context](runs/2026-11-04-HR07-TRAINING-COMPLETE-attempt-10.md); `spa/src/pages/hr/employees/detail.tsx:1207-1221`.
- **Status:** Resolved in E2E test code. Attempt 11 from a fresh baseline clicked the exact `Assign` button scoped to the named dialog and sent the UI assignment request. The request returned HTTP 500, now tracked separately as FND-HR-004 / GAP-HR-07-01. No application code changed.

### TST-HR-049 — Recruitment dashboard exposes two identical New Posting actions

- **Classification:** Test issue (ambiguous UI locator)
- **Process / journey:** HR-02, `HR02-HAPPY` attempt 02
- **Step / role:** Open the posting form / `hr_officer`
- **Observed:** Both the page-header action and the Recruitment empty-state action were visible and named `New Posting`. The exact but unscoped role locator matched both and failed in strict mode before either button was clicked. No create request or posting record was produced; the remaining nine steps were blocked.
- **Correction:** Scope the exact accessible button to `[data-layout="page-header"]`, which identifies the page's declared primary action without weakening the assertion.
- **Evidence:** [attempt 02 report, trace, screenshots, and journey context](runs/2026-11-04-HR02-HAPPY-attempt-02-locator.md); `spa/src/pages/hr/recruitment/index.tsx`; `spa/e2e-process/hr-recruitment.process.spec.ts`.
- **Status:** Resolved in E2E test code. Attempt 04 used the header-scoped action and reached the posting form. No application code changed.

### TST-HR-050 — Department label locator also matches the searchable-select button

- **Classification:** Test issue (ambiguous accessible locator)
- **Process / journey:** HR-02, `HR02-HAPPY` attempt 04
- **Step / role:** Create draft posting / `hr_officer`
- **Observed:** `getByLabel('Department')` matched both the searchable Department combobox and its suffix button named “Show Department”; strict mode failed before choosing a department or submitting the form. No create request or posting row was produced; steps 2–10 were blocked.
- **Correction:** Locate the control by `getByRole('combobox', { name: 'Department' })`, then choose the visible exact `Human Resources` option from its listbox.
- **Evidence:** [attempt 04 report, trace, screenshots, and journey context](runs/2026-11-04-HR02-HAPPY-attempt-04-department-combobox.md); `spa/src/components/ui/SearchableSelect.tsx`; `spa/e2e-process/hr-recruitment.process.spec.ts`.
- **Status:** Resolved in E2E test code. Attempt 05 selected the Department combobox and chose `Human Resources`, then created and published the posting. No application code changed.

### TST-HR-051 — Toast dismiss buttons collide with the posting Close action

- **Classification:** Test issue (ambiguous accessible locator)
- **Process / journey:** HR-02, `HR02-HAPPY` attempt 05
- **Step / role:** Verify publication / `hr_officer`
- **Observed:** After UI publication, `getByRole('button', { name: 'Close', exact: true })` matched the posting action and two toast dismiss buttons. The Open badge and read-only DB row confirmed publication (`status=open`, non-null `posted_at`), but the strict-mode failure stopped the step before audit assertions; steps 3–10 were blocked.
- **Correction:** Scope the Close action assertion to `[data-layout="page-header"]`.
- **Evidence:** [attempt 05 report, trace, screenshots, and journey context](runs/2026-11-04-HR02-HAPPY-attempt-05-close-locator.md); `spa/src/components/layout/PageHeader.tsx`; `spa/e2e-process/hr-recruitment.process.spec.ts`.
- **Status:** Resolved in E2E test code. Attempt 09 created and published a fresh posting; the page-header Close assertion passed and the posting's open status, posted timestamp, and audit entry were verified. No application code changed.

### TST-HR-052 — Public application checkbox locator matches newsletter consent too

- **Classification:** Test issue (ambiguous accessible locator)
- **Process / journey:** HR-02, `HR02-HAPPY` attempt 09
- **Step / role:** Submit public candidate application / public applicant
- **Observed:** The candidate form's checkbox and the footer newsletter checkbox both have privacy-policy wording. The unscoped checkbox locator failed strict mode after form fields and resume selection but before the Submit Application button was clicked. A read-only query found zero application rows for the candidate; steps 4–10 were blocked.
- **Correction:** Locate the checkbox by the candidate form's unique purpose text, “We use your application only to assess your candidacy.”
- **Evidence:** [attempt 09 report, trace, screenshots, and journey context](runs/2026-11-04-HR02-HAPPY-attempt-09-privacy-checkbox-locator.md); `spa/src/pages/careers/detail.tsx`; `spa/src/pages/landing/components/LandingFooter.tsx`; `spa/e2e-process/hr-recruitment.process.spec.ts`.
- **Status:** Resolved in E2E test code. Attempt 10 checked the consent checkbox matching the candidacy purpose, submitted through the public UI (HTTP 201), and verified the application and confirmation email. No application code changed.

### TST-HR-053 — External OpenStreetMap tile DNS errors fail the page diagnostic

- **Classification:** Test issue (known external network dependency)
- **Process / journey:** HR-02, `HR02-HAPPY` attempt 10
- **Step / role:** Public Careers application / public applicant
- **Observed:** The candidate submitted through the UI; status 201, application data, posting count, and confirmation email assertions passed. The step then failed because browser diagnostics recorded nine DNS failures for OpenStreetMap raster tiles loaded by the landing page's `PlantMap`. The subsequent HR notification hand-off, candidate status transitions, and observer checks were blocked.
- **Correction:** The test-only diagnostics now capture full console source origins, and the known-noise file narrowly allows `/16/` tile request/console failures only on `a`, `b`, and `c.tile.openstreetmap.org`. No ERP API path or HTTP 5xx is allowlisted.
- **Evidence:** [attempt 10 report and archived trace/screenshots/context](runs/2026-11-04-HR02-HAPPY-attempt-10-osm-tile-diagnostics.md); `spa/src/pages/landing/components/PlantMap.tsx`; `spa/e2e-process/helpers/page-diagnostics.ts`; `spa/e2e-process/known-network-noise.json`.
- **Status:** Resolved in E2E diagnostics. Attempt 12's fresh-baseline candidate step passed diagnostics after the narrow tile-host allowlist was applied. No application code changed.

### TST-HR-054 — Origin-qualified console evidence missed the auth 401 allowlist

- **Classification:** Test issue (E2E diagnostics allowlist mismatch)
- **Process / journey:** E2E auth setup before HR-02, `HR02-HAPPY` attempt 11
- **Step / role:** Sign in selected E2E actors / `hr_officer`
- **Observed:** The real UI login returned HTTP 200 and reached Dashboard, then diagnostics rejected the expected unauthenticated initial `/api/v1/auth/user` 401 because console evidence now included `https://nginx` while the allowlist entry did not. Auth setup failed and the process journey did not run; no process transaction occurred.
- **Correction:** Qualify the existing exact 401 allowlist fragment with `https://nginx` to match diagnostic evidence without broadening the endpoint/status exception.
- **Evidence:** [attempt 11 report, error context, screenshots, and result](runs/2026-11-04-HR02-HAPPY-attempt-11-auth-allowlist-regression.md); `spa/e2e-process/helpers/page-diagnostics.ts`; `spa/e2e-process/known-network-noise.json`.
- **Status:** Resolved in E2E test tooling. Attempt 12's fresh-baseline auth setup passed for both selected actors with the origin-qualified exact 401 allowlist. No application code changed.

### TST-HR-055 — Concurrent role-page startup stalled the Employee Loans route

- **Classification:** Test issue (E2E resource contention)
- **Process / journey:** HR-05, `HR05-CASH-ADVANCE` attempt 01
- **Step / role:** Open Self-service Loans / Employee
- **Observed:** After all five actor contexts loaded their application pages together, the Employee Loans page remained in its loading state and no cash advance was submitted. Startup API requests were canceled during test teardown (HTTP 499); no server 5xx or transactional row was observed.
- **Correction:** Keep role contexts isolated, but load the Employee and HR observer pages sequentially and defer approval actors' dashboard navigation until their hand-off step.
- **Evidence:** [attempt 03 report](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-03-role-recipient-expectation.md); current screenshots and trace in its [artifact directory](runs/assets/hr05-cash-advance-attempt-03/).
- **Status:** Resolved in test orchestration. Attempt 03 reached and submitted from the Employee Loans form, then failed at the unrelated recipient-set expectation. No application code changed.

### TST-HR-056 — Required loan-field labels did not match exact locators

- **Classification:** Test issue (accessible-label locator mismatch)
- **Process / journey:** HR-05, `HR05-CASH-ADVANCE` attempt 02
- **Step / role:** Select cash advance type and fill Amount/periods / Employee
- **Observed:** The initial exact Type/Amount labels did not match the required-field label rendering in the dialog; attempt 02 stopped before submitting a request.
- **Correction:** Use a role-based combobox locator for Type and anchored label patterns for required Amount and periods fields.
- **Evidence:** [attempt 03 report and screenshots](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-03-role-recipient-expectation.md); the archived Employee dialog screenshot shows the labels and controls.
- **Status:** Resolved in E2E test code. Attempt 03 selected the type and filled all request fields, then submitted through the UI. No application code changed.

### TST-HR-057 — Cash advance submission assertion omitted the second configured Finance recipient

- **Classification:** Test issue (recipient expectation derived too narrowly)
- **Process / journey:** HR-05, `HR05-CASH-ADVANCE` attempt 03
- **Step / role:** Assert submission-notice recipients / Employee submission
- **Observed:** The UI request succeeded and the first-step process checks passed. The exact recipient assertion expected the primary Finance Officer only but found both active `finance_officer` accounts.
- **Correction:** The test now expects both same-role Finance accounts for the configured submission FYI and Finance approval step. The Department Head and VP recipients remain constrained to their current chain step; approval-stage checks continue to assert exact audiences.
- **Evidence:** [attempt 03 report, sanitized trace, screenshot, and context](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-03-role-recipient-expectation.md); setting `api/database/migrations/0376_seed_workflow_notification_roles.php`; recipient selection `api/app/Modules/Loans/Listeners/NotifyOnLoanSubmitted.php`; baseline users `api/database/seeders/E2EAccountSeeder.php`.
- **Status:** Resolved in E2E test code. Attempt 04's fresh-baseline run passed the exact two-recipient Finance FYI assertion. A separate initial hand-off locator/expectation issue is tracked by TST-HR-058. No application code changed.

### TST-HR-058 — Initial cash advance hand-off was assumed to be a notification

- **Classification:** Test expectation issue (the test selected one hand-off mechanism before checking the UI)
- **Process / journey:** HR-05, `HR05-CASH-ADVANCE` attempt 04
- **Step / role:** Department Head finds the pending first step / `department_head`
- **Observed:** The test asserted an `approval.action_required` notification and stopped when the read-only notification query returned no rows. It did not yet inspect the Department Head's Approval Queue. `LoanService::request()` invokes `notifyNextStep()` only when the Department Head step is skipped; the normal Department Head path leaves the pending approval in the queue without this notice.
- **Correction:** Navigate through the top-level sidebar Approval Queue and follow its “My action required” card if present. The next clean-baseline run will establish whether this actual UI hand-off is available. No product finding is assigned until it is checked.
- **Evidence:** [attempt 04 report, trace, screenshots, and context](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-04-initial-approval-notification.md); `api/app/Modules/Loans/Services/LoanService.php`; `api/app/Common/Services/ApprovalService.php`; `spa/src/pages/approvals/index.tsx`.
- **Status:** Resolved in the journey. Attempt 07 followed the sidebar Approval Queue with the Department Head's own session, opened the pending loan, and approved it through the UI. The state advanced and the acting role was recorded on the linked approval row. No application code changed.

### TST-HR-059 — Queued cash-advance submission notices were asserted before delivery

- **Classification:** Test issue (async assertion timing)
- **Process / journey:** HR-05, `HR05-CASH-ADVANCE` attempt 06
- **Step / role:** Assert Finance submission FYI recipients / Employee submission
- **Observed:** The request, pending approval chain, no-journal invariant, and live HR observer check passed. The immediate notification query returned no rows. After the test ended, a read-only query found both configured Finance notices with timestamps in the submission second. `NotifyOnLoanSubmitted` implements `ShouldQueue`.
- **Correction:** Poll the read-only notification state for at least the expected recipient count with a bounded 15-second timeout, then assert the exact recipient set. This waits on the queued side effect condition without a fixed sleep.
- **Evidence:** [attempt 06 report and sanitized artifacts](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-06-queued-notification-race.md); `api/app/Modules/Loans/Listeners/NotifyOnLoanSubmitted.php`.
- **Status:** Resolved in E2E test code. Attempt 07's clean-baseline run waited for the queued Finance notices and confirmed the exact two-recipient set. No application code changed.

### TST-HR-060 — Loan approval audit reader filtered out ApprovalRecord events

- **Classification:** Test issue (audit query did not follow the audited subject)
- **Process / journey:** HR-05, `HR05-CASH-ADVANCE` attempt 07
- **Step / role:** Verify approval audit actor / Department Head
- **Observed:** Department Head approved the cash advance through the UI. The current approval row recorded the seeded actor, but the test queried only `EmployeeLoan` audit rows. A read-only query found the `updated` audit entry against the linked `App\\Common\\Models\\ApprovalRecord`, attributed to the Department Head.
- **Correction:** Extend the read-only audit helper to include audit rows for ApprovalRecord IDs linked to the loan and assert the acting approver there.
- **Evidence:** [attempt 07 report and sanitized artifacts](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-07-approval-queue-audit-reader.md); `api/app/Common/Models/ApprovalRecord.php`; `spa/e2e-process/hr-loans.process.spec.ts`.
- **Status:** Resolved in the journey. Attempt 10 passed the linked-record audit actor assertion for the Department Head, Finance Officer, and Vice President after each UI approval. No application code changed.

### TST-HR-061 — Employee loan list does not display the internal loan number

- **Classification:** Test issue (realtime assertion used a hidden identifier)
- **Process / journey:** HR-05, `HR05-CASH-ADVANCE` attempt 08
- **Step / role:** Assert Employee's already-open loan list updates to Active / Employee
- **Observed:** The final request and all three approvals completed through the UI. HR loan detail and the final Employee failure screenshot displayed `Active`; the assertion locator first filtered the self-service row by `CA-202611-0001`, which is not shown in that table.
- **Correction:** Locate the single `Cash Advance` row inside the Employee's `Active loans` region and assert its rendered `Active` status. Keep the Employee page open; do not reload.
- **Evidence:** [attempt 08 report, screenshots, and sanitized trace](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-08-final-live-view-locator.md); `spa/src/pages/self-service/loans.tsx`.
- **Status:** Resolved in the journey. Attempt 10's Employee Active-loan row assertion passed while that page remained open through the VP approval; no reload was used. No application code changed.

### TST-HR-062 — Source-linked journal actor assertion used `created_by`

- **Classification:** Test issue (journal actor field expectation did not match source-linked posting policy)
- **Process / journey:** HR-05, `HR05-CASH-ADVANCE` attempt 08
- **Step / role:** Verify final disbursement journal actor / Vice President
- **Observed:** Read-only DB verification found the source-linked disbursement journal's `created_by` null and `posted_by` set to the Vice President. `JournalEntryService::create()` intentionally leaves `created_by` null when a reference type exists; the approval and journal audits record the acting approver.
- **Correction:** Assert `posted_by_email` equals the VP and `created_by_email` is null for this source-linked journal.
- **Evidence:** [attempt 08 report and sanitized artifacts](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-08-final-live-view-locator.md); `api/app/Modules/Accounting/Services/JournalEntryService.php:132-136`; `api/app/Modules/Loans/Services/LoanService.php:718-725`.
- **Status:** Resolved in the journey. Attempt 10 confirmed null `created_by` and the Vice President as `posted_by` for the balanced source-linked disbursement journal. No application code changed.

### TST-HR-063 — Journal snapshot SQL omitted the newly joined poster from `GROUP BY`

- **Classification:** Test issue (read-only SQL syntax)
- **Process / journey:** HR-05, `HR05-CASH-ADVANCE` attempt 09
- **Step / role:** Assert no journal exists before approval / Employee submission
- **Observed:** The Employee's UI submission completed. The read-only journal query failed because it selected `poster.email` while grouping only by journal ID and creator email; no approval step ran.
- **Correction:** Include `poster.email` in the query's `GROUP BY` and validate the helper with a fresh-baseline run.
- **Evidence:** [attempt 09 report and sanitized artifacts](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-09-journal-query-grouping.md); `spa/e2e-process/hr-loans.process.spec.ts`.
- **Status:** Resolved in the journey. Attempt 10's fresh-baseline first-step no-journal query and final posted-journal query both passed with `poster.email` grouped. No application code changed.

### TST-HR-064 — Loan outcome assertion expected the generic approval notification type

- **Classification:** Test issue (notification type not derived from the loan event handler)
- **Process / journey:** HR-05, `HR05-CASH-ADVANCE` attempt 10
- **Step / role:** Verify final approval notification / Vice President
- **Observed:** UI approvals, final Active state, audit actors, live views, balanced journal and VP poster passed. The invariant looked for `approval.completed`; loan outcome delivery is emitted by queued `NotifyOnLoanDecided` as `loans.approved`.
- **Correction:** Poll for the queued `loans.approved` notification, assert it is addressed only to the Employee, and assert no `approval.completed` loan notification exists.
- **Evidence:** [attempt 10 report and sanitized artifacts](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-10-final-notification-type.md); `api/app/Modules/Loans/Listeners/NotifyOnLoanDecided.php`; `api/app/Common/Services/ApprovalService.php`; prior read-only observation in [attempt 08](runs/2026-11-04-HR05-CASH-ADVANCE-attempt-08-final-live-view-locator.md).
- **Status:** Resolved in E2E test code by attempt 14: the bounded poll, exact Employee-only `loans.approved` audience, absence of `approval.completed`, and no-participant-email Mailpit assertion passed. The same run then failed on the separate VP read-scope issue FND-HR-006. No application code changed.

### TST-HR-065 — Rejection-reason locator did not account for the required marker

- **Classification:** Test issue (accessible-name locator)
- **Process / journey:** HR-05, `HR05-REJECT` attempt 01
- **Step / role:** Locate Department Head's required reason textbox
- **Observed:** Employee submission and the Department Head Approval Queue hand-off passed. `getByLabel('Reason for rejection', { exact: true })` did not match the rendered label once the required marker was included, so the test stopped before sending a rejection.
- **Correction:** Locate the field by textbox role and `/Reason for rejection/`; retain assertions for the HTML `required` attribute and for the Confirm reject button being disabled while empty and enabled after a reason is entered.
- **Evidence:** [attempt 01 report and sanitized artifacts](runs/2026-11-04-HR05-REJECT-attempt-01-test-locator.md); `spa/src/components/ui/Textarea.tsx`.
- **Status:** Resolved in E2E test code. Attempt 03 matched the field, verified required validation, and reached the rejection API action. No application code changed.

### TST-HR-066 — Finance role was not a valid live observer for the HR loan list

- **Classification:** Test issue (observer role/navigation assumption)
- **Process / journey:** HR-05, `HR05-LOAN-UNDER-THRESHOLD` attempt 01
- **Step / role:** Open the live loan list / Finance Officer 2
- **Observed:** All selected actors signed in, but Finance Officer 2 could not expand Human Resources to open the observer list before the Employee submitted. No loan was created.
- **Correction:** Keep the Employee self-service list open as the non-approver observer. It shows the same loan and receives the live status update without asking a role to navigate outside its module access.
- **Evidence:** [attempt 01 report and sanitized artifacts](runs/2026-11-04-HR05-LOAN-UNDER-THRESHOLD-attempt-01-observer-role.md); successful end-to-end walkthrough [attempt 07](runs/2026-11-04-HR05-LOAN-UNDER-THRESHOLD-attempt-07-full-run.md).
- **Status:** Resolved in E2E test code; attempt 07 verified the Employee's open loan row changed to Active without refresh. No application code changed.

### TST-HR-067 — Under-threshold company-loan notification assertions used the wrong hand-off timing

- **Classification:** Test issue (notification expectation not derived from loan submission and approval handlers)
- **Process / journey:** HR-05, `HR05-LOAN-UNDER-THRESHOLD` attempts 02, 05, and 06
- **Step / role:** Verify Department Head, HR, and Finance hand-offs
- **Observed:** The test expected an initial `approval.action_required` notice for Department Head, then used the completed approval step rather than the next pending step to calculate HR's hand-off. Source and DB observations showed company-loan submission sends Finance FYI only; Department Head locates the work in Approval Queue, and approving each stage sends the notice for the next eligible step.
- **Correction:** Expect no step-1 action-required notice; after Department Head approval expect HR at step 2; after HR expect both Finance users at step 3; omit VP for the below-threshold branch. Assert exact recipients and document link.
- **Evidence:** [attempt 02](runs/2026-11-04-HR05-LOAN-UNDER-THRESHOLD-attempt-02-initial-notification-assumption.md), [attempt 05](runs/2026-11-04-HR05-LOAN-UNDER-THRESHOLD-attempt-05-first-step-notification.md), [attempt 06](runs/2026-11-04-HR05-LOAN-UNDER-THRESHOLD-attempt-06-next-step-notification-matrix.md), and successful [attempt 07](runs/2026-11-04-HR05-LOAN-UNDER-THRESHOLD-attempt-07-full-run.md); `api/app/Common/Services/ApprovalService.php`; `api/app/Modules/Loans/Services/LoanService.php`.
- **Status:** Resolved in E2E test code; attempt 07 passed the complete below-threshold hand-off, notification audience, skipped VP, and final Finance posting assertions. No application code changed.


### TST-HR-068 — Unused payroll test import failed TypeScript checking

- **Classification:** Test issue (compile-time unused import)
- **Process / journey:** HR-06, `HR06-HAPPY`
- **Observed:** The E2E TypeScript check reported `TS6133` for an unused `assertAuditActor` import.
- **Correction:** Removed the unused import; the process-E2E TypeScript project passed.
- **Status:** Resolved in E2E test code. No browser action ran because of this compile issue; no application code changed.

### TST-HR-069 — New actor contexts opened on a blank page

- **Classification:** Test issue (test setup/navigation)
- **Process / journey:** HR-06, `HR06-HAPPY`
- **Observed:** Fresh actor contexts restored a valid login state but started at `about:blank`, so sidebar-driven navigation was unavailable.
- **Correction:** Open the normal `/dashboard` landing page and assert the module navigation and signed-in profile before each actor starts.
- **Status:** Resolved in E2E test code; this is normal authenticated landing navigation, not a transactional deep link.

### TST-HR-070 — Department option locator omitted the rendered department code

- **Classification:** Test issue (accessible locator did not match the option label)
- **Process / journey:** HR-06, `HR06-HAPPY`
- **Observed:** The form rendered `Production (PROD)`, while the initial locator expected only `Production`.
- **Correction:** Derive the exact accessible label, including the code, from read-only baseline reference data and select that UI option.
- **Status:** Resolved in E2E test code; no transactional record was created by the failed locator.

### TST-HR-071 — Period creation response was incorrectly treated as a detail response

- **Classification:** Test issue (response contract mismatch)
- **Process / journey:** HR-06, `HR06-HAPPY`
- **Observed:** The create response omits the detail-only `available_actions` field; the first invariant expected it directly in the create payload.
- **Correction:** Await the detail GET initiated by the UI, then assert action permissions from that read-only response.
- **Status:** Resolved in E2E test code. The remaining mismatch between detail API and rendered action is tracked separately as FND-HR-009.

### TST-HR-072 — Eligible payroll approver query used NULL-sensitive `NOT IN`

- **Classification:** Test issue (read-only SQL logic)
- **Process / journey:** HR-06, `HR06-HAPPY`
- **Observed:** A NULL `computed_by` value caused `NOT IN (..., NULL)` to return no eligible Finance approvers.
- **Correction:** Use `recipient.id <> created_by AND (computed_by IS NULL OR recipient.id <> computed_by)`.
- **Status:** Resolved in the read-only verification helper; no application data was changed.

### TST-HR-073 — Payroll status text locator matched multiple progress views

- **Classification:** Test issue (ambiguous accessible locator)
- **Process / journey:** HR-06, `HR06-HAPPY`
- **Observed:** `Computed` appeared in multiple page regions, so an unscoped locator matched several elements.
- **Correction:** Scope status assertions to the accessible `Document progress` region in one helper used by HR and Finance.
- **Status:** Resolved in E2E test code; no application code changed.

### TST-HR-074 — Queued payroll computation audit was attributed to the HR maker

- **Classification:** Test issue (audit actor expectation did not match system-managed transition)
- **Process / journey:** HR-06, `HR06-HAPPY`
- **Observed:** The test expected the queued `processing → computed` audit row to be attributed to HR. Source shows `PayrollPeriodService::releaseClaim()` records `actor_type=system`, action `updated`, and `source_command=payroll.compute.release`; HR remains the `computed_by` maker.
- **Correction:** Assert the system actor/source command for the worker transition and separately assert HR's `computed_by` identity.
- **Status:** Resolved in E2E test code; no application code changed.

### TST-HR-075 — Payroll create step was incorrectly coupled to the next Compute action

- **Classification:** Test issue (journey step boundary)
- **Process / journey:** HR-06, `HR06-HAPPY`
- **Observed:** The initial journey asserted that Compute was visible inside the create-period step, causing successful creation to be marked failed and later work blocked.
- **Correction:** Keep UI creation and its Draft/read-only invariants in step 1; assert and act on Compute in step 2, where an API/UI discrepancy is recorded as `test.fixme` / GAP-HR-06-01.
- **Status:** Resolved in E2E test structure. The product finding remains open; the test was not rerun after observing inconsistent behavior.

### TST-HR-076 — Finance notification check ran before opening the actor's app session

- **Classification:** Test issue (actor-context navigation)
- **Process / journey:** HR-08, `HR08-VOLUNTARY` attempt 01
- **Step / role:** Follow the separation notice / Finance Officer
- **Observed:** HR initiated the separation through the UI. Read-only checks confirmed the clearance, the configured exact notification audience including Finance, queued delivery, and the opted-in email. Finance's new Playwright page remained `about:blank` because the journey searched for the notification bell before entering the authenticated app, so the bell locator timed out before a Finance action.
- **Correction:** Open the allowed `/sign-in` entry route in Finance's actor context; the existing authenticated storage state restores the session to its Dashboard. Then use the rendered notification bell and its linked record as the hand-off.
- **Evidence:** [attempt 01 report and sanitized artifacts](runs/2026-11-05-HR08-VOLUNTARY-attempt-01-finance-session-blank.md); `spa/e2e-process/hr-separation.process.spec.ts`.
- **Status:** Resolved in E2E test code; attempt 01 remains failed and is not counted as a completed journey. No application code changed.

### TST-HR-077 — Finance clearance navigation used the pre-transform section name

- **Classification:** Test issue (role-specific sidebar label)
- **Process / journey:** HR-08, `HR08-VOLUNTARY` attempt 02
- **Step / role:** Finance signs its first clearance item / Finance Officer
- **Observed:** The Finance role successfully followed the real notification link to the clearance. Four earlier departmental signatures and live-observer assertions passed. The later sidebar helper found `/hr/separations` in Finance navigation but searched for the collapsed group under `data-module="Human Resources"`; `Sidebar.tsx` changes this rendered group label to `Payroll` for Finance.
- **Correction:** Attempt 02 passed the Finance pending-signal path after it used the rendered `Payroll` label. Attempt 03 exposed a second list-navigation helper used by the final-pay permission check; that helper has now also been changed to use `Payroll` for Finance.
- **Evidence:** [attempt 02 report and sanitized walkthrough](runs/2026-11-05-HR08-VOLUNTARY-attempt-02-finance-sidebar-label.md), [attempt 03 report and sanitized walkthrough](runs/2026-11-05-HR08-VOLUNTARY-attempt-03-finance-permission-path-label.md), [attempt 04 report](runs/2026-11-05-HR08-VOLUNTARY-attempt-04-journal-creator-expectation.md); `spa/src/components/layout/Sidebar.tsx`; `spa/e2e-process/hr-separation.process.spec.ts`.
- **Status:** Resolved in E2E test code. Attempt 04 passed the Finance record access and absence-of-final-pay-actions assertions. Attempts 02 and 03 remain failed historical runs; no application code changed.

### TST-HR-078 — Final-pay journal creator assertion ignored source-linked posting policy

- **Classification:** Test issue (read-only journal actor expectation)
- **Process / journey:** HR-08, `HR08-VOLUNTARY` attempt 04
- **Step / role:** Verify final-pay journal after HR finalization
- **Observed:** The complete process ran through role-owned UI actions. Finalization returned HTTP 200; read-only checks confirmed a finalized clearance, resigned employee, deactivated account, and one posted linked journal. The test expected `created_by` to be HR but observed null.
- **Source:** `FinalPayService::postJournalEntry()` supplies the Clearance source reference and then calls `JournalEntryService::postSystem($entry, $by->id)`. `JournalEntryService::create()` intentionally sets `created_by` to null for source-linked entries; `postSystem()` writes the posting actor to `posted_by`.
- **Correction:** Assert the source-linked journal's `created_by_email` is null and `posted_by_email` is HR; retain the balanced totals/lines, audit actor, and employment-history checks.
- **Evidence:** [attempt 04 report and sanitized walkthrough](runs/2026-11-05-HR08-VOLUNTARY-attempt-04-journal-creator-expectation.md), [attempt 05 report](runs/2026-11-05-HR08-VOLUNTARY-attempt-05-finalized-locator-ambiguous.md); `api/app/Modules/HR/Services/FinalPayService.php`; `api/app/Modules/Accounting/Services/JournalEntryService.php`; `spa/e2e-process/hr-separation.process.spec.ts`.
- **Status:** Resolved in the journey. Attempt 05 confirmed null `created_by`, HR `posted_by`, and the balanced journal lines. Attempt 04 remains failed historical evidence; no application code changed.

### TST-HR-079 — Finalized status locator matched both the badge and progress tracker

- **Classification:** Test issue (ambiguous exact text locator)
- **Process / journey:** HR-08, `HR08-VOLUNTARY` attempt 05
- **Step / role:** Final status and observer invariants / HR Officer and System Administrator observer
- **Observed:** All process actions and all read-only state, journal, audit, and employment-history assertions passed. The final UI invariant used an unscoped exact-text locator for `Finalized`; the status chip and the `Document progress` tracker each rendered that text, so Playwright strict mode failed before checking the linked journal display and the observer's final update.
- **Correction:** Scope the badge assertion to `[data-layout="page-header"]` and assert the `Finalized` state separately inside the accessible `Document progress` region. Apply the same header scope to the live observer status check.
- **Evidence:** [attempt 05 report and sanitized walkthrough](runs/2026-11-05-HR08-VOLUNTARY-attempt-05-finalized-locator-ambiguous.md), [attempt 06 full-run report and walkthrough](runs/2026-11-05-HR08-VOLUNTARY-attempt-06-full-run.md); `spa/src/components/layout/PageHeader.tsx`; `spa/src/components/chain/ChainHeader.tsx`; `spa/e2e-process/hr-separation.process.spec.ts`.
- **Status:** Resolved in E2E test code. Attempt 06 passed both the scoped header badge and separate progress-tracker assertions on HR and the live System Administrator observer. Attempt 05 remains failed historical evidence. No application code changed.

### TST-HR-080 — Clearance status assertion ran on the Employee detail page

- **Classification:** Test issue (assertion used the next page’s status on the current page)
- **Process / journey:** HR-08, `HR08-CLEARANCE-BLOCKED` attempt 01
- **Step / role:** HR initiation invariant / HR Officer
- **Observed:** HR created the separation through the Employee UI. Read-only checks confirmed the `in_progress` clearance, 11 pending items, `on_leave` employee, active account, audit, and configured initiation notices. The page was still the Employee detail, which visibly showed `On leave`; the test instead searched for the clearance’s `In progress` status before navigating to Separation Clearance. The out-of-order finalize attempt did not run.
- **Correction:** Assert the Employee page’s `On leave` status for the initiation step. In the next step, follow the Separation Clearance sidebar link and open the record before asserting its status and Finalize availability. Preserve the exact negative 422 API assertion and side-effect checks.
- **Evidence:** [attempt 01 report, screenshots, sanitized trace, and context](runs/2026-11-05-HR08-CLEARANCE-BLOCKED-attempt-01-status-locator.md); `spa/e2e-process/hr-separation.process.spec.ts`.
- **Status:** Resolved in E2E test code. Attempt 02 verified the Employee status assertion and sidebar navigation, then passed the UI absence check and authenticated out-of-order 422 with no state, audit, employment-history, or journal side effect. Attempt 03 passed the expanded full notification-type before/after snapshot, confirming the refused action did not change notifications. No application code changed.

### TST-MNT-001 — Department option locator used a partial seeded label

- **Classification:** Test issue (exact native-select option label mismatch)
- **Process / journey:** MNT-01, `MNT01-BREAKDOWN`
- **Observed:** The test selected `Maintenance`, while `DepartmentSeeder` and the UI expose `Maintenance & Engineering`. The closed select screenshot showed only its selected placeholder and did not prove its option list was empty.
- **Correction:** Select the full seeded department label. The HR prerequisite completed through the UI with read-only schedule and audit assertions in attempt 13.
- **Status:** Resolved in E2E test code; no application code changed. The full MNT journey later passed in attempt 25.
- **Evidence:** [Attempt 12](runs/2026-11-05-MNT01-BREAKDOWN-attempt-12-department-label.md), [attempt 13](runs/2026-11-05-MNT01-BREAKDOWN-attempt-13-runner-interruption.md), and [attempt 25](runs/2026-11-05-MNT01-BREAKDOWN-attempt-25-full-run.md).

### TST-MNT-002 — Assignment notification expectation used the wrong row order

- **Classification:** Test issue (notification assertion ordering)
- **Process / journey:** MNT-01, `MNT01-BREAKDOWN`
- **Observed:** The step-4 assignment action and UI status passed. Its read-only notification assertion received the expected notification types and recipients in the order specified by the helper's SQL (`ORDER BY notice.type, recipient.email`), while the test expected the same rows in a different order.
- **Correction:** Reordered the expected rows to match the deterministic query sort. Exact recipient and type assertions remain intact.
- **Status:** Resolved in E2E test code; attempt 25 passed the exact notification-order assertion after supervisor assignment. No application code changed.
- **Evidence:** [Attempt 16 report and sanitized artifacts](runs/2026-11-05-MNT01-BREAKDOWN-attempt-16-notification-order.md), [attempt 20 report and sanitized artifacts](runs/2026-11-05-MNT01-BREAKDOWN-attempt-20-shift-ended.md), [attempt 21 report and artifacts](runs/2026-11-05-MNT01-BREAKDOWN-attempt-21-clock-order.md), [attempt 22 report and artifacts](runs/2026-11-05-MNT01-BREAKDOWN-attempt-22-clock-session.md), and [attempt 25 full run and sanitized walkthrough](runs/2026-11-05-MNT01-BREAKDOWN-attempt-25-full-run.md); `spa/e2e-process/maintenance-breakdown.process.spec.ts`.

### TST-MNT-003 — Maintenance assignment fixture must cover the current shift and session clock

- **Classification:** Test issue (time-dependent test setup)
- **Process / journey:** MNT-01, `MNT01-BREAKDOWN`
- **Observed:** Attempt 20 reached Assign worker after the configured Day Shift (06:00–14:00 Manila) had ended at 14:03; the UI correctly showed no available technician and disabled assignment. Attempts 21–22 introduced a fixed 09:00 Manila clock; both redirected the HR session to sign-in before the shift assignment was submitted. The sanitized attempt-22 trace shows 401 responses from authenticated API routes after a multi-hour backward time jump.
- **Cause:** The test must align maintenance coverage to the simulated work minute. A multi-hour clock jump also conflicts with the cookie-backed database session's host-clock expiration/write behavior.
- **Correction:** Freeze browser and backend time at the current host instant rounded down to the minute. Choose the seeded Extended Day shift for 06:00–18:00 Manila and Night Shift for 18:00–06:00. For a run before 06:00, assign the overnight shift effective from the previous work date. Keep the candidate-availability and physical-presence assertions exact; all transactional changes remain through the HR and Maintenance role UIs.
- **Status:** Resolved in attempt 25. The current-minute browser/backend clock, HR shift assignment, supervisor eligibility, physical-presence confirmation, and later work steps all passed. No application code changed.
- **Evidence:** [Attempt 20 shift-ended report](runs/2026-11-05-MNT01-BREAKDOWN-attempt-20-shift-ended.md), [attempt 21 clock-order report](runs/2026-11-05-MNT01-BREAKDOWN-attempt-21-clock-order.md), [attempt 22 clock/session report and sanitized trace](runs/2026-11-05-MNT01-BREAKDOWN-attempt-22-clock-session.md), and [attempt 25 full run](runs/2026-11-05-MNT01-BREAKDOWN-attempt-25-full-run.md); `api/app/Modules/Maintenance/Services/MaintenanceAssigneeSuggestionService.php`; `api/app/Modules/Attendance/Services/EmployeeAvailabilityService.php`; `api/app/Common/Support/E2EClock.php`; `spa/e2e-process/helpers/clock.ts`.

### TST-MNT-004 — Work-order notification was asserted before its queued delivery

- **Classification:** Test issue (async notification assertion)
- **Process / journey:** MNT-01, `MNT01-BREAKDOWN`
- **Observed:** Attempt 23 created an Open maintenance work order through the Technician UI. The immediate read-only notification assertion returned no `maintenance.assignment_needed` row and stopped the journey. A later read-only query found exactly one such notification for the intended Maintenance Supervisor; no matching queued job or failed job remained.
- **Cause:** The work-order notification is delivered asynchronously, while the journey checked the database immediately after the UI action.
- **Correction:** Use a bounded Playwright `expect.poll` to read the exact expected notification rows and recipients. The step still fails if delivery does not occur within 15 seconds; no sleep or transaction shortcut is used.
- **Status:** Resolved in attempt 25. The bounded exact-row poll passed, and the hand-off completed through Supervisor and Technician sessions.
- **Evidence:** [Attempt 23 report and sanitized trace](runs/2026-11-05-MNT01-BREAKDOWN-attempt-23-queued-notification.md) and [attempt 25 full run and screenshots](runs/2026-11-05-MNT01-BREAKDOWN-attempt-25-full-run.md); `api/app/Modules/Maintenance/Listeners/NotifyMaintenanceWorkOrder.php`; `spa/e2e-process/maintenance-breakdown.process.spec.ts`.

## UX observations (not process blockers)

### UX-HR-001 — Profile-change badge link omits its actionable signal filter

- **Classification:** UX issue (Low severity; queue row remains visible and actionable)
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Observed:** The HR sidebar badge reports one “Profile changes you can review.” Selecting its “Awaiting my action” link opens `/hr/profile-update-requests` without `?signal=awaiting_my_action`. The list still shows the pending bank-change row and its HR action.
- **Expected by existing UI behavior test:** The badge menu link targets `/hr/profile-update-requests?signal=awaiting_my_action`.
- **Evidence:** [sixth-attempt report and trace](runs/2026-11-04-HR07-BANK-CHANGE-sixth-attempt.md); `spa/src/components/layout/Sidebar.tsx` (`badgeDestination` omits `profile_requests`); `spa/src/components/layout/Sidebar.behavior.test.tsx`.
- **Status:** Open; no app code changed. The process journey can continue through the visible queue row.

### UX-HR-002 — Finance review details omit the HR reviewer name

- **Classification:** UX issue (Low severity; Finance can see the pending request and act)
- **Process / journey:** HR-07, `HR07-BANK-CHANGE`
- **Step / role:** Finance receives the HR-approved bank change / `finance_officer`
- **Observed:** After HR approved the request through the UI, Finance found it through the live badge and expanded it. The record showed the change, request note, Awaiting Finance status, and Finance Approve action, but not the HR approver name. The page conditionally renders “HR approved by …” when `row.reviewer` exists; the review list only eager-loads employee and requester, so the resource leaves reviewer null. Backend reviewer/audit attribution will be checked read-only after the HR step in the next clean-baseline run.
- **Evidence:** [eleventh-attempt report and archived trace](runs/2026-11-04-HR07-BANK-CHANGE-eleventh-attempt.md); `spa/src/pages/hr/profile-update-requests/index.tsx`; `api/app/Modules/HR/Services/ProfileUpdateRequestService.php`; `api/app/Modules/HR/Resources/ProfileUpdateRequestResource.php`.
- **Status:** Open; the user-visible approval path remains actionable. No app change made.

### UX-HR-003 — Leave status labels differ between the options endpoint and enum resource

- **Classification:** UX issue (Low; status value and available actions are correct)
- **Process / journey:** HR-04, `HR04-APPROVE`
- **Step / role:** Employee detail and pending approval queue / `employee`, `department_head`, `hr_officer`
- **Observed:** The Employee detail and Department Head pending row rendered “Pending dept.” `/api/v1/leaves/options` formats status values by replacing underscores, yielding “Pending dept” and “Pending hr”; `LeaveRequestStatus::label()` and `LeaveRequestResource` use “Pending department head” and “Pending HR approval.” HR's queue row has not yet been observed in a passing journey.
- **Impact:** The API's options labels and resource labels disagree for the same stored values. The tested Employee and Department Head views displayed the short label; the remaining user-visible impact is not yet fully established. Stored status, workflow stage, and available actions were correct.
- **Evidence:** [HR04-APPROVE fourth-, eighth-, and ninth-attempt reports](runs/2026-11-04-HR04-APPROVE-attempt-04.md), [attempt 08](runs/2026-11-04-HR04-APPROVE-attempt-08.md), and [attempt 09](runs/2026-11-04-HR04-APPROVE-attempt-09.md); `api/app/Modules/Leave/Controllers/LeaveRequestController.php`; `api/app/Modules/Leave/Enums/LeaveRequestStatus.php`; `api/app/Modules/Leave/Resources/LeaveRequestResource.php`; `spa/src/pages/leaves/index.tsx`.
- **Status:** Open source-level UX inconsistency; journey expectations follow the rendered short labels. No application change made.

### UX-HR-004 — Insufficient-balance toast hides the business-rule reason

- **Classification:** UX issue (Low; refusal is correct and no transaction is created)
- **Process / journey:** HR-04, `HR04-INSUFFICIENT-BALANCE`
- **Step / role:** Employee submits a request beyond the available balance / `employee`
- **Observed:** The API returned `Insufficient leave balance for 2026 (15.0 remaining; 16 requested).`, but the self-service form displayed only “Failed to submit leave request.” and kept the form open. The Employee cannot see how much leave remains or how many days the request covers from the refusal message.
- **Evidence:** [attempt 02 report and screenshot](runs/2026-11-04-HR04-INSUFFICIENT-BALANCE-attempt-02.md); `spa/src/pages/self-service/leave.tsx` error handler; `api/app/Modules/Leave/Controllers/LeaveRequestController.php` 422 response.
- **Status:** Open; no application code changed. The business refusal is covered independently of this copy issue.

### UX-INV-001 — Withdrawn adjustment notice retains its approval title

- **Classification:** UX issue (Low severity; no unauthorized action remains available)
- **Process / journey:** INV-01, `INV01-HIGH-WITHDRAW`
- **Step / role:** Follow original approval notice after withdrawal / `finance_officer`
- **Observed:** Warehouse withdrew its pending adjustment through the UI. Finance's original unread “Stock adjustment needs approval” notice remained in the inbox. Following it opened the record in `withdrawn` status with Approve/Reject unavailable; opening the notice marked it read. The stock movement did not post.
- **Impact:** The inbox title can still look actionable until Finance opens the record; the current state and buttons are accurate after navigation.
- **Evidence:** [INV01-HIGH-WITHDRAW full run](runs/2026-11-04-INV01-HIGH-WITHDRAW-full-run.md); `StockAdjustmentService::withdraw()` does not dispatch an outcome notice or invalidate the approval notice.
- **Business rule to confirm:** Should the original notice remain as history, be marked resolved, or be updated to say the requester withdrew the adjustment? The catalog proposes updating it to a non-actionable “withdrawn” notice while retaining the audit history.
- **Status:** Open for UX/business-rule review. No application code changed.

