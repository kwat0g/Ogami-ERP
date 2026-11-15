# HR transaction demo layer

`HrTransactionHistoryLayer` is the service-backed HR transaction layer. It depends on `workforce-identities` and `hr-leave-balances`; add its class to the demo layer registry after those layers. The dependency declarations enforce that order.

## Seeded slice

| Data | Planned volume | Resulting workflow states |
| --- | ---: | --- |
| Attendance / DTR | 8 recent working dates per active employee; 4 payroll personas with up to 9 monthly samples from Jan–Oct 2026 (September skipped while its payroll periods are approved); 50 employees per query chunk; up to 2 additional historic holiday punches when both types are in the app calendar | App-computed Present, Late and Absent rows; deterministic occasional undertime; regular and special-holiday work outcomes; October overtime sample |
| Overtime | 3 app-detected requests for PAY-02 when present; otherwise a distinct seeded employee at `small` scale | 1 pending, 1 approved, 1 rejected |
| Paid leave | 5 one-day Vacation Leave requests | 1 pending department approval, 1 pending HR approval, 1 approved, 1 rejected, 1 cancelled by the employee |
| Company loan / cash advance | 3 applications | 1 active company loan, 1 pending cash advance, 1 rejected cash advance |

For planning, the workforce layer has 21 employees at `small` scale and 241 at `demo`/`full` scale. Eight workdays yield 168 and 1,928 regular-history rows respectively; the seeded 2026 calendar adds two historic holiday rows when both regular and special holidays precede the `as-of` date (170 and 1,930 total). The layer excludes Sundays and dates returned by the app's `HolidayService`. Attendance statuses, DTR totals, and OT detection come from `AttendanceService` and its configured rules; the seeder supplies shift punches and lets the app calculate outcomes.

The payroll-month slice is intentionally small (up to 36 extra rows) and persona-based: a clean present control, chronic-late pattern, undertime pattern, and absence pattern. It also creates a single October OT candidate for PAY-02 using the configured auto-detection threshold. Dates are resolved against the employee's assigned shift and app holiday calendar. The layer creates October 1–15 and October 16–31 draft payroll periods through `PayrollPeriodService`, making both available for live computation in the UI. Approved September payroll inputs are not edited. The October periods have no payroll rows until an operator computes them.

The overtime punches extend past shift end by the configured auto-detection threshold plus 15 minutes. The layer fails with a specific error if auto-detection is absent or disabled. The leave examples use the app's paid, active, document-free `VL` type. Approval decisions go through the employee's department head and the seeded HR reviewer. The company loan and cash advance records go through `LoanService`; configured limits, interest and approval workflow are read from app settings/workflows. The selected loan principal is capped by the app's own loan limit. Approving the company loan therefore also creates the journal entry through the real service.

All domain records are created through `AttendanceService`, `OvertimeService`, `LeaveRequestService`, and `LoanService`. The layer uses controlled Asia/Manila timestamps, stable demo markers for replay detection, and notification suppression. It does not create payroll periods, payroll rows, payslips, statutory contributions, or payroll deductions.

## Registry integration

Add `Database\\Seeders\\Demo\\HrTransactionHistoryLayer::class` to `config/demo.php`'s layer list. Its declared dependencies are `workforce-identities` and `hr-leave-balances`; no other registry edits are needed. It remembers useful transaction keys such as `hr.overtime.pending`, `hr.leave.approved`, and `hr.loan.active-company-loan` in the per-run registry for later layers.

## Verification status

The focused test is `api/tests/Feature/Demo/HrTransactionHistoryLayerTest.php`. PHP syntax lint passed. The focused PHPUnit run did not complete: its first run hit a PostgreSQL migration deadlock while another process was using `ogami_test`; a later approved attempt was stopped at the parent agent's request when a different focused test took priority. No live database was used by this layer work. Service-level outcomes remain unverified until the focused test can run alone against `ogami_test`.

The 2026 attendance history for the payroll cohort is seeded separately by [`payroll-attendance-history`](payroll-attendance-history.md). It adds DTR and approved OT through the app services, while leaving September locked payroll inputs untouched.
