# MNT01-BREAKDOWN — attempt 12: seeded Department label mismatch

**Result:** Test issue at the HR shift setup step; no shift assignment or maintenance work order was created.

- **Environment:** Guarded isolated E2E stack, fresh baseline, headless Chromium, one worker. HR Officer, Maintenance Technician, Maintenance Supervisor, and Production Manager completed real UI sign-in.
- **Observed:** HR navigated through Human Resources → Attendance & DTR → Shifts → Bulk assign. The test called `selectOption({ label: 'Maintenance' })` and timed out because no option had that exact label. The screenshot shows the closed native select's current placeholder; it does not establish that its option list was empty.
- **Root cause:** `DepartmentSeeder` names the seeded department **Maintenance & Engineering**. The page renders `Department.name`, so the E2E locator used a partial label that did not match the actual option.
- **Correction:** The test now selects the seeded label `Maintenance & Engineering`. Attempt 13 confirmed that the HR shift-assignment step completes through the UI and its read-only database/audit assertions pass.
- **Classification:** Test issue `TST-MNT-001`, resolved in E2E test code. No application defect or process gap is inferred.

The failed locator trace, screenshot, and journey context are preserved in [attempt assets](assets/mnt01-breakdown-attempt-12/).
