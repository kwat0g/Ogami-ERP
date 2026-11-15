# HR03-MISSING-PUNCH — attempt 02

**Result:** Selected Employee and HR authentication passed. The Employee time-in step passed. The HR search step stopped on a strict-mode locator issue; Employee DTR, HR correction, realtime observation and refreshed confirmation were blocked. This attempt's annotated step screenshot and report remain, but its trace and error-context files were not retained; see TST-INV-012.

- **Run ID:** `96531c8a-20f7-48db-bd22-29af1a02ac1b`
- **Selected date / shift:** 2026-11-05 / Day Shift, 06:00–14:00 (Asia/Manila)
- **Step / role:** HR finds the previous-day row in the Missing time-out filter / `hr_officer`
- **Observed:** The UI time-in returned HTTP 201 and created one `present` attendance row with `time_in=06:00`, `time_out=NULL`, and one geofenced punch. HR opened Attendance & DTR and applied the date filter. `getByLabel('Time out')` matched five accessible elements: two sidebar signal buttons, the hidden native select, the visible searchable combobox and its Show button. Playwright strict mode stopped before HR selected Missing time-out or opened the record.
- **Remaining effects:** No correction was submitted. The one attendance row and punch remained only in the disposable E2E database and were removed by the next guarded reset. No API or database mutation advanced the process.
- **Classification:** Test issue TST-HR-033; no application finding inferred.
- **Evidence:** [step screenshot](assets/hr03-missing-punch-attempt-02/001-employee-records-time-in-for-a-scheduled-shift.png). The trace, error context and journey context were not retained.

