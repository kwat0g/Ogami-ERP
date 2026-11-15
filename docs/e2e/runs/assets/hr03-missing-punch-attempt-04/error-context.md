# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-attendance.process.spec.ts >> HR03-MISSING-PUNCH: employee time-in is corrected by HR and the open employee DTR updates live
- Location: e2e-process/hr-attendance.process.spec.ts:605:1

# Error details

```
Error: expect(locator).toContainText(expected) failed

Locator: getByRole('row').filter({ hasText: 'E2E-2FDC0177057D3A5' })
Expected substring: "2:00 PM"
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toContainText" getByRole('row').filter({ hasText: 'E2E-2FDC0177057D3A5' }) with timeout 8000ms
  - waiting for getByRole('row').filter({ hasText: 'E2E-2FDC0177057D3A5' })
    3 × locator resolved to <tr tabindex="0" aria-keyshortcuts="j k ArrowDown ArrowUp Enter Space Control+A Meta+A" class="border-b border-subtle transition-colors duration-fast motion-reduce:transition-none relative group cursor-pointer active:scale-[0.998] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-inset hover:bg-surface hover:z-10 h-row-compact">…</tr>
      - unexpected value "Nov 5, 2026E2E employeeE2E-2FDC0177057D3A5Day Shift6:00 AMMissing0.000.000.00—Present"

```

```yaml
- link "Skip to content":
  - /url: "#main-content"
- banner:
  - button "Toggle sidebar":
    - img
  - link "Ogami ERP Philippine Ogami Corporation":
    - /url: /dashboard
    - img "Ogami ERP"
    - text: Philippine Ogami Corporation
  - navigation "Breadcrumb":
    - link "Human Resources":
      - /url: /hr/employees
    - img
    - text: Attendance
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications":
    - img
  - 'status "Real-time updates: Live. Last updated 12:01 AM"': Live
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E hr officer":
    - img "E2E hr officer": EH
    - text: E2E hr officer
    - img
- complementary "Sidebar":
  - navigation "Modules":
    - group "Work":
      - text: Work
      - list:
        - listitem:
          - link "Dashboard":
            - /url: /dashboard
            - img
            - text: Dashboard
        - listitem:
          - link "Action Center":
            - /url: /action-center
            - img
            - text: Action Center
        - listitem:
          - link "Decision Center":
            - /url: /decision-center
            - img
            - text: Decision Center
        - listitem:
          - link "Approval Queue":
            - /url: /approvals
            - img
            - text: Approval Queue
        - listitem:
          - link "Notifications":
            - /url: /notifications
            - img
            - text: Notifications
    - button "Human Resources" [disabled] [expanded]
    - list:
      - listitem:
        - link "Recruitment":
          - /url: /hr/recruitment
          - img
          - text: Recruitment
      - listitem:
        - link "Training Programs":
          - /url: /hr/trainings
          - img
          - text: Training Programs
      - listitem:
        - link "Employee Training Records":
          - /url: /hr/employee-trainings
          - img
          - text: Employee Training Records
      - listitem:
        - link "Employee Loans":
          - /url: /hr/loans
          - img
          - text: Employee Loans
      - listitem:
        - link "Separation Clearance":
          - /url: /hr/separations
          - img
          - text: Separation Clearance
      - listitem:
        - link "Profile Change Requests":
          - /url: /hr/profile-update-requests
          - img
          - text: Profile Change Requests
      - listitem:
        - link "Employees":
          - /url: /hr/employees
          - img
          - text: Employees
      - listitem:
        - link "Departments":
          - /url: /hr/departments
          - img
          - text: Departments
      - listitem:
        - link "Attendance & DTR":
          - /url: /hr/attendance
          - img
          - text: Attendance & DTR
      - listitem:
        - link "Overtime":
          - /url: /hr/attendance/overtime
          - img
          - text: Overtime
      - listitem:
        - link "Leave Management":
          - /url: /hr/leaves
          - img
          - text: Leave Management
      - listitem:
        - link "Payroll Processing":
          - /url: /payroll/periods
          - img
          - text: Payroll Processing
      - listitem:
        - link "Payroll Adjustments":
          - /url: /payroll/adjustments
          - img
          - text: Payroll Adjustments
      - listitem:
        - link "Payroll Statutory Exports":
          - /url: /payroll/statutory
          - img
          - text: Payroll Statutory Exports
    - button "Administration"
  - group "Module controls":
    - button "Expand all"
- main:
  - heading "Daily Time Records" [level=1]
  - text: 0 records
  - button "Manual DTR":
    - img
    - text: Manual DTR
  - button "Shifts":
    - img
    - text: Shifts
  - button "Holidays":
    - img
    - text: Holidays
  - button "Import DTR":
    - img
    - text: Import DTR
  - search:
    - textbox "Search employee no or name…"
  - button "Saved views": Views
  - combobox "Department": "Department: All"
  - button "Show Department"
  - combobox "Status": "Status: All"
  - button "Show Status"
  - combobox "Time out": Missing time-out
  - button "Show Time out"
  - text: Date
  - textbox "Date from": 2026-11-05
  - textbox "Date to": 2026-11-05
  - group "Active filters":
    - 'button "Remove Time out: Missing time-out"': "Time out: Missing time-out"
    - 'button "Remove Date from: 2026-11-05"': "Date from: 2026-11-05"
    - 'button "Remove Date to: 2026-11-05"': "Date to: 2026-11-05"
    - text: 2 active filters
    - button "Clear all"
  - radiogroup "Visibility":
    - radio "Show active records" [checked]: Active
    - radio "Show active and archived records":
      - img
      - text: All
    - radio "Show archived records only":
      - img
      - text: Archived
  - img
  - heading "No attendance found" [level=3]
  - paragraph: Import a biometric CSV to get started.
  - button "Add manual DTR"
```

# Test source

```ts
  640 |         await freezeClock(request, localInstant(baseline.work_date, baseline.shift_start));
  641 |         clockFrozen = true;
  642 |         await openEmployeeClock(employee);
  643 |         const responsePromise = employee.page.waitForResponse((response) =>
  644 |           response.url().endsWith('/api/v1/attendance/punch')
  645 |           && response.request().method() === 'POST');
  646 |         await employee.page.getByRole('button', { name: 'Time in', exact: true }).click();
  647 |         const response = await responsePromise;
  648 |         expect(response.status(), 'Employee time-in should create the attendance through the UI').toBe(201);
  649 |       },
  650 |       verify: async (page) => {
  651 |         await expect(page.getByRole('button', { name: 'Time out', exact: true })).toBeEnabled();
  652 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  653 |         expect(snapshot.attendance_status).toBe('present');
  654 |         expect(snapshot.time_in_local).toBe(localDateTime(baseline.work_date, baseline.shift_start));
  655 |         expect(snapshot.time_out_local).toBeNull();
  656 |         expect(snapshot.punches[0].direction).toBe('in');
  657 |         expect(snapshot.punches[0].punched_at_local).toBe(localDateTime(baseline.work_date, baseline.shift_start));
  658 |       },
  659 |       assertInvariants: async () => {
  660 |         employee.diagnostics.assertClean('Employee records a time-in for the missing-punch journey');
  661 |       },
  662 |     });
  663 | 
  664 |     await runProcessStep({
  665 |       ...missingPunchSteps[1],
  666 |       page: hr.page,
  667 |       plannedSteps: missingPunchSteps,
  668 |       context: journey,
  669 |       testInfo,
  670 |       action: async () => {
  671 |         const nextDay = localInstant(nextLocalDate(baseline.work_date), '00:01:00');
  672 |         await freezeClock(request, nextDay);
  673 |         await hr.page.clock.setFixedTime(new Date(nextDay));
  674 |         await openMissingPunchList(hr, baseline.work_date, baseline.employee_no);
  675 |       },
  676 |       verify: async () => {
  677 |         const row = hr.page.getByRole('row').filter({ hasText: baseline.employee_no });
  678 |         await expect(row).toContainText('Present');
  679 |         await expect(row).toContainText('Missing');
  680 |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_start)));
  681 |       },
  682 |       assertInvariants: async () => {
  683 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  684 |         expect(snapshot.time_out_local).toBeNull();
  685 |         expect(snapshot.overtime_count).toBe(0);
  686 |         expect(snapshot.attendance_notification_count).toBe(0);
  687 |         hr.diagnostics.assertClean('HR finds the previous-day missing time-out through the DTR filters');
  688 |       },
  689 |     });
  690 | 
  691 |     await runProcessStep({
  692 |       ...missingPunchSteps[2],
  693 |       page: employee.page,
  694 |       plannedSteps: missingPunchSteps,
  695 |       context: journey,
  696 |       testInfo,
  697 |       action: async () => {
  698 |         await employee.page.clock.setFixedTime(new Date(localInstant(nextLocalDate(baseline.work_date), '00:01:00')));
  699 |         await openSelfServiceDtr(employee, baseline.work_date);
  700 |       },
  701 |       verify: async (page) => {
  702 |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  703 |         await expect(row).toHaveCount(1);
  704 |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_start)));
  705 |       },
  706 |       assertInvariants: async () => {
  707 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  708 |         expect(snapshot.attendance_status).toBe('present');
  709 |         expect(snapshot.time_out_local).toBeNull();
  710 |         employee.diagnostics.assertClean('Employee opens DTR while HR prepares the correction');
  711 |       },
  712 |     });
  713 | 
  714 |     await runProcessStep({
  715 |       ...missingPunchSteps[3],
  716 |       page: hr.page,
  717 |       plannedSteps: missingPunchSteps,
  718 |       context: journey,
  719 |       testInfo,
  720 |       action: async (page) => {
  721 |         const row = page.getByRole('row').filter({ hasText: baseline.employee_no });
  722 |         await row.click();
  723 |         const modal = page.getByRole('dialog', { name: 'Correct attendance record' });
  724 |         await expect(modal).toBeVisible();
  725 |         await expect(modal.getByLabel('Time in')).toHaveValue(baseline.shift_start.slice(0, 5));
  726 |         await expect(modal.getByLabel('Time out')).toHaveValue('');
  727 |         await modal.getByLabel('Time out').fill(baseline.shift_end.slice(0, 5));
  728 |         await modal.getByLabel('Remarks').fill(`E2E ${journey.runId}: correct missed time-out from shift record`);
  729 |         const correction = page.waitForResponse((response) =>
  730 |           response.url().match(/\/api\/v1\/attendance\/attendances\/[^/]+$/) !== null
  731 |           && response.request().method() === 'PUT');
  732 |         await modal.getByRole('button', { name: 'Save correction', exact: true }).click();
  733 |         const response = await correction;
  734 |         const body = await response.text();
  735 |         expect(response.status(), `HR correction submitted through the UI should succeed; response: ${body.slice(0, 500)}`).toBe(200);
  736 |       },
  737 |       verify: async (page) => {
  738 |         await expect(page.getByRole('dialog', { name: 'Correct attendance record' })).toHaveCount(0);
  739 |         const row = page.getByRole('row').filter({ hasText: baseline.employee_no });
> 740 |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
      |                           ^ Error: expect(locator).toContainText(expected) failed
  741 |         await expect(row).toContainText('7.50');
  742 |         await expect(row).toContainText('Present');
  743 |       },
  744 |       assertInvariants: async () => {
  745 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  746 |         expect(snapshot.attendance_status).toBe('present');
  747 |         expect(snapshot.time_in_local).toBe(localDateTime(baseline.work_date, baseline.shift_start));
  748 |         expect(snapshot.time_out_local).toBe(localDateTime(baseline.work_date, baseline.shift_end));
  749 |         expect(snapshot.regular_hours).toBe('7.50');
  750 |         expect(snapshot.overtime_hours).toBe('0.00');
  751 |         expect(snapshot.tardiness_minutes).toBe(0);
  752 |         expect(snapshot.undertime_minutes).toBe(0);
  753 |         expect(snapshot.punches).toHaveLength(1);
  754 |         expect(snapshot.attendance_notification_count).toBe(0);
  755 |         expect(snapshot.audits.some((audit) => audit.actor_email?.toLowerCase() === hrActor.email.toLowerCase())).toBe(true);
  756 |         hr.diagnostics.assertClean('HR saves the attendance correction through its own session');
  757 |       },
  758 |     });
  759 | 
  760 |     await runProcessStep({
  761 |       ...missingPunchSteps[4],
  762 |       page: employee.page,
  763 |       plannedSteps: missingPunchSteps,
  764 |       context: journey,
  765 |       testInfo,
  766 |       action: async () => undefined,
  767 |       verify: async (page) => {
  768 |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  769 |         await captureAnnotatedStep(page, 'employee', 'Employee DTR remains open after HR correction', 5, testInfo);
  770 |         await expect(row, 'attendance_changed should refresh the open employee DTR without a manual reload')
  771 |           .toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
  772 |         await expect(row).toContainText('7.5');
  773 |       },
  774 |       assertInvariants: async () => {
  775 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  776 |         expect(snapshot.time_out_local).toBe(localDateTime(baseline.work_date, baseline.shift_end));
  777 |         employee.diagnostics.assertClean('Employee observes HR correction in the already-open DTR');
  778 |         hr.diagnostics.assertClean('HR retains the corrected attendance list');
  779 |         expect((await listMailpitMessages(500)).map((message) => message.ID).sort()).toEqual(mailIdsBefore);
  780 |       },
  781 |     });
  782 | 
  783 |     await runProcessStep({
  784 |       ...missingPunchSteps[5],
  785 |       page: employee.page,
  786 |       plannedSteps: missingPunchSteps,
  787 |       context: journey,
  788 |       testInfo,
  789 |       action: async (page) => { await page.reload(); },
  790 |       verify: async (page) => {
  791 |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  792 |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
  793 |         await expect(row).toContainText('7.5');
  794 |         await expect(row).toContainText('Present');
  795 |       },
  796 |       assertInvariants: async () => {
  797 |         employee.diagnostics.assertClean('Employee confirms saved DTR after reloading');
  798 |       },
  799 |     });
  800 |   } catch (error) {
  801 |     primaryError = error;
  802 |     throw error;
  803 |   } finally {
  804 |     const cleanupErrors: string[] = [];
  805 |     try {
  806 |       if (clockFrozen) await clearClock(request);
  807 |     } catch (error) {
  808 |       cleanupErrors.push(`clock reset: ${error instanceof Error ? error.message : String(error)}`);
  809 |     } finally {
  810 |       const closeResults = await Promise.allSettled([employee.context.close(), hr.context.close()]);
  811 |       closeResults.forEach((result, index) => {
  812 |         if (result.status === 'rejected') {
  813 |           cleanupErrors.push(`${index === 0 ? 'employee' : 'HR'} context close: ${result.reason instanceof Error ? result.reason.message : String(result.reason)}`);
  814 |         }
  815 |       });
  816 |     }
  817 |     if (cleanupErrors.length > 0) {
  818 |       await testInfo.attach('journey-cleanup-errors.txt', {
  819 |         body: cleanupErrors.join('\n'),
  820 |         contentType: 'text/plain',
  821 |       });
  822 |       if (primaryError === undefined) throw new Error(cleanupErrors.join('\n'));
  823 |     }
  824 |   }
  825 | });
  826 | 
  827 | function nextLocalDate(date: string): string {
  828 |   const [year, month, day] = date.split('-').map(Number);
  829 |   const next = new Date(Date.UTC(year, month - 1, day + 1));
  830 |   return `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, '0')}-${String(next.getUTCDate()).padStart(2, '0')}`;
  831 | }
  832 | 
```