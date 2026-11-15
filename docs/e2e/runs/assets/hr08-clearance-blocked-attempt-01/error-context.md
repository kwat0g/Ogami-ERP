# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-separation.process.spec.ts >> HR08-CLEARANCE-BLOCKED — finalization is refused until every checklist item is signed
- Location: e2e-process/hr-separation.process.spec.ts:760:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('In progress', { exact: true })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByText('In progress', { exact: true }) with timeout 8000ms
  - waiting for getByText('In progress', { exact: true })

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
    - link "Employees":
      - /url: /hr/employees
    - img
    - text: E2E employee
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications":
    - img
  - 'status "Real-time updates: Reconnecting. Last updated 4:39 PM"': Reconnecting
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
    - 'button "Human Resources: Clearances with pending items you can sign: 1" [disabled] [expanded]': Human Resources
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
        - 'button "View all Separation Clearance signals: 1 Clearances with pending items you can sign"': "1"
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
  - link "Employees":
    - /url: /hr/employees
    - img
    - text: Employees
  - heading "E2E employee On leave" [level=1]
  - text: E2E-2FDC0177057D3A5 · Plant Manager · Production
  - button "Edit":
    - img
    - text: Edit
  - heading "Onboarding" [level=3]
  - text: Profile 11/5/2026 Shift Leave Balances 11/5/2026 System Account 11/5/2026 Dept Team Notified HR confirmation is required for this step.
  - button "Mark team notified"
  - text: Government IDs Banking Info
  - tablist "Employee details":
    - tab "Overview" [selected]
    - tab "Employment history"
    - tab "Attendance"
    - tab "Leaves"
    - tab "Loans"
    - tab "Documents"
    - tab "Trainings"
    - tab "Skills"
    - tab "Payroll"
    - tab "Activity"
  - heading "Personal" [level=3]
  - img
  - text: Upload
  - term: Full name
  - definition: E2E employee
  - term: Birth date
  - definition: Nov 6, 1996
  - term: Gender
  - definition: Male
  - term: Civil status
  - definition: Single
  - term: Nationality
  - definition: Filipino
  - heading "Contact" [level=3]
  - term: Mobile
  - definition: —
  - term: Email
  - definition: e2e+employee@ogami-e2e.test
  - term: Address
  - definition: —
  - term: Emergency contact
  - definition: —
  - heading "Government IDs" [level=3]
  - text: Masked. Click reveal to view.
  - button "Reveal":
    - img
    - text: Reveal
  - term: SSS
  - definition: —
  - term: PhilHealth
  - definition: —
  - term: Pag-IBIG
  - definition: —
  - term: TIN
  - definition: —
  - heading "Banking" [level=3]
  - term: Bank
  - definition: —
  - term: Account number
  - definition: —
  - heading "At a glance" [level=3]
  - term: Employee no
  - definition: E2E-2FDC0177057D3A5
  - term: Hired
  - definition: Nov 6, 2024
  - term: Pay type
  - definition: Monthly
  - term: Monthly salary
  - definition: ₱30,000.00
  - term: Linked user
  - definition: e2e+employee@ogami-e2e.test
  - heading "System Account" [level=3]
  - text: "Active Last login: Nov 6, 2026 · 12:39 AM Email: e2e+employee@ogami-e2e.test Role: Employee"
```

# Test source

```ts
  748 |   clearanceNo: string,
  749 | ): Promise<{ status: string }> {
  750 |   // Sidebar.tsx renders the HR section as Payroll for finance_officer.
  751 |   await openClearanceFromList(finance, employeeNo, 'Payroll');
  752 |   await expect(finance.page.getByRole('heading', { name: clearanceNo, exact: true })).toBeVisible();
  753 |   await expect(finance.page.getByText('Completed', { exact: true })).toBeVisible();
  754 |   const snapshots = await readOnlyDatabaseJson<Array<{ status: string }>>(`
  755 |     SELECT status::text AS status FROM clearances WHERE clearance_no = ${sqlText(clearanceNo)}
  756 |   `);
  757 |   return snapshots[0] ?? { status: 'missing' };
  758 | }
  759 | 
  760 | test('HR08-CLEARANCE-BLOCKED — finalization is refused until every checklist item is signed', async ({ browser, request }, testInfo) => {
  761 |   test.setTimeout(15 * 60 * 1000);
  762 |   const actors = await loadActors();
  763 |   const checklist = await configuredChecklist();
  764 |   const journey = createJourneyContext('HR08-CLEARANCE-BLOCKED');
  765 |   const hrActor = actorByName(actors, 'hr_officer');
  766 |   const employeeActor = actorByName(actors, 'employee');
  767 |   const remarks = `${journey.values.runPrefix} incomplete clearance finalization guard`;
  768 |   const hr = await actorPage(browser, 'hr_officer');
  769 |   const plannedSteps = [
  770 |     {
  771 |       name: 'HR starts separation and leaves the configured checklist pending',
  772 |       actor: 'hr_officer',
  773 |       uiPath: 'Human Resources → Employees → employee → Separate → Start separation',
  774 |       expectedStatus: 'clearance in_progress; every configured item pending; employee on_leave; account remains active',
  775 |     },
  776 |     {
  777 |       name: 'HR cannot finalize through the UI or out-of-order API while clearance items remain pending',
  778 |       actor: 'hr_officer',
  779 |       uiPath: 'Human Resources → Separation Clearance → employee clearance; negative finalize request',
  780 |       expectedStatus: 'Finalize unavailable; negative request returns 422; no final-pay journal, finalization audit, notification or status change',
  781 |     },
  782 |   ];
  783 |   let frozenClock = false;
  784 |   try {
  785 |     const initialClock = await readClock(request);
  786 |     await freezeClock(request, initialClock);
  787 |     frozenClock = true;
  788 |     const effectiveDate = manilaDate(initialClock);
  789 |     const targetRows = await employeeBaseline(employeeActor.email);
  790 |     expect(targetRows, 'the seeded employee actor should resolve to one reference Employee').toHaveLength(1);
  791 |     const target = targetRows[0];
  792 |     expect(target.status, 'the isolated E2E employee should start active').toBe('active');
  793 |     expect(target.user_active, 'the isolated E2E employee account should start enabled').toBe(true);
  794 | 
  795 |     await runProcessStep({
  796 |       ...plannedSteps[0], page: hr.page, plannedSteps, context: journey, testInfo,
  797 |       action: async (page) => {
  798 |         await openDashboard(hr);
  799 |         await openSidebarItem(hr, 'Human Resources', '/hr/employees', 'Employees');
  800 |         await expect(page.getByRole('heading', { name: 'Employees', exact: true })).toBeVisible();
  801 |         await page.getByPlaceholder(/Search name or employee no/).fill(target.employee_no);
  802 |         const employeeRow = page.getByRole('row').filter({ hasText: target.employee_no });
  803 |         await expect(employeeRow).toHaveCount(1);
  804 |         await employeeRow.click();
  805 |         await expect(page.getByRole('button', { name: 'Separate', exact: true })).toBeEnabled();
  806 |         await page.getByRole('button', { name: 'Separate', exact: true }).click();
  807 |         const modal = page.getByRole('dialog', { name: 'Separate employee' });
  808 |         await expect(modal).toBeVisible();
  809 |         await modal.getByLabel('Reason').selectOption('resigned');
  810 |         await modal.getByLabel('Effective date').fill(effectiveDate);
  811 |         await modal.getByLabel('Remarks').fill(remarks);
  812 |         const responseWait = page.waitForResponse((response) => response.request().method() === 'POST'
  813 |           && /\/employees\/[^/]+\/separation(?:\?|$)/.test(new URL(response.url()).pathname));
  814 |         await modal.getByRole('button', { name: 'Start separation', exact: true }).click();
  815 |         const response = await responseWait;
  816 |         expect(response.status(), 'HR initiation should create the clearance through the UI').toBe(201);
  817 |         await expect(page.getByText(/Separation initiated\. Complete clearance and final pay/)).toBeVisible();
  818 |       },
  819 |       verify: async () => {
  820 |         const clearance = await waitForClearance(target.employee_no, remarks, 'in_progress');
  821 |         expect(clearance.separation_reason).toBe('resigned');
  822 |         expect(clearance.separation_date).toBe(effectiveDate);
  823 |         expect(clearance.employee_status).toBe('on_leave');
  824 |         expect(clearance.employee_user_active).toBe(true);
  825 |         expect(clearance.final_pay_computed).toBe(false);
  826 |         expect(clearance.journal_entry_id).toBeNull();
  827 |         expect(clearance.items).toHaveLength(checklist.length);
  828 |         expect(clearance.items.every((item) => item.status === 'pending')).toBe(true);
  829 |         journey.values.clearanceId = clearance.clearance_id;
  830 |         journey.values.clearanceNo = clearance.clearance_no;
  831 |         journey.values.employeeNo = target.employee_no;
  832 |         journey.values.effectiveDate = effectiveDate;
  833 |         journey.values.checklist = checklist;
  834 |         const audits = await clearanceAudits(clearance.clearance_id);
  835 |         expect(audits.map((entry) => entry.action)).toEqual(['created']);
  836 |         assertClearanceAuditActor(audits[0], hrActor.email);
  837 |         const expected = await expectedNotificationRecipients(clearance.clearance_id);
  838 |         let notices: NotificationSnapshot[] = [];
  839 |         await expect.poll(async () => {
  840 |           notices = await separationNotifications(clearance.clearance_id);
  841 |           return notices.length;
  842 |         }, { timeout: 20_000, message: 'the initiated clearance notification should reach its configured audience' })
  843 |           .toBe(expected.length);
  844 |         assertRecipientsExactly(notices.map((notice) => notice.recipient_email), expected);
  845 |         await assertInitiationEmails(clearance.clearance_id, initialClock);
  846 |       },
  847 |       assertInvariants: async () => {
> 848 |         await expect(hr.page.getByText('In progress', { exact: true })).toBeVisible();
      |                                                                         ^ Error: expect(locator).toBeVisible() failed
  849 |         hr.diagnostics.assertClean('HR starts a clearance-blocked separation');
  850 |       },
  851 |     });
  852 | 
  853 |     await runProcessStep({
  854 |       ...plannedSteps[1], page: hr.page, plannedSteps, context: journey, testInfo,
  855 |       action: async () => {
  856 |         await openClearanceFromList(hr, target.employee_no);
  857 |         const before = (await clearanceSnapshot(target.employee_no, remarks))[0];
  858 |         await expect(hr.page.getByRole('heading', { name: before.clearance_no, exact: true })).toBeVisible();
  859 |         await expect(hr.page.getByRole('button', { name: 'Finalize', exact: true }),
  860 |           'HR must not be offered finalization while checklist items are pending').toHaveCount(0);
  861 |         const origin = new URL(hr.page.url()).origin;
  862 |         const csrfCookie = (await hr.context.cookies(origin)).find((cookie) => cookie.name === 'XSRF-TOKEN');
  863 |         expect(csrfCookie, 'the HR browser session should carry its Sanctum CSRF cookie').toBeDefined();
  864 |         const response = await hr.context.request.fetch(`/api/v1/hr/clearances/${before.clearance_id}/finalize`, {
  865 |           method: 'PATCH',
  866 |           data: {},
  867 |           failOnStatusCode: false,
  868 |           headers: {
  869 |             Accept: 'application/json',
  870 |             'X-Requested-With': 'XMLHttpRequest',
  871 |             Origin: origin,
  872 |             Referer: hr.page.url(),
  873 |             'X-XSRF-TOKEN': decodeURIComponent(csrfCookie!.value),
  874 |           },
  875 |         });
  876 |         expect(response.status(), 'the HR actor out-of-order finalization attempt must be refused as a business-rule error').toBe(422);
  877 |         const body = await response.json() as { message?: string; errors?: Record<string, string[]> };
  878 |         expect(body.message).toBe('All clearance items must be signed before finalization.');
  879 |         expect(body.errors?.error).toContain('All clearance items must be signed before finalization.');
  880 |       },
  881 |       verify: async () => {
  882 |         const after = (await clearanceSnapshot(target.employee_no, remarks))[0];
  883 |         expect(after.status).toBe('in_progress');
  884 |         expect(after.employee_status).toBe('on_leave');
  885 |         expect(after.employee_user_active).toBe(true);
  886 |         expect(after.final_pay_computed).toBe(false);
  887 |         expect(after.final_pay_amount).toBeNull();
  888 |         expect(after.journal_entry_id).toBeNull();
  889 |         expect(after.items.every((item) => item.status === 'pending')).toBe(true);
  890 |         expect(await journalSnapshot(after.clearance_id)).toHaveLength(0);
  891 |         expect((await separationHistory(after.employee_id)).map((entry) => entry.status)).toEqual(['in_progress']);
  892 |         const audits = await clearanceAudits(after.clearance_id);
  893 |         expect(audits.map((entry) => entry.action)).toEqual(['created']);
  894 |         assertClearanceAuditActor(audits[0], hrActor.email);
  895 |         const notices = await separationNotifications(after.clearance_id);
  896 |         assertRecipientsExactly(notices.map((notice) => notice.recipient_email), await expectedNotificationRecipients(after.clearance_id));
  897 |       },
  898 |       assertInvariants: async () => {
  899 |         await expect(hr.page.getByRole('button', { name: 'Finalize', exact: true })).toHaveCount(0);
  900 |         await expect(hr.page.locator('[data-layout="page-header"]')
  901 |           .getByText('In progress', { exact: true })).toBeVisible();
  902 |         hr.diagnostics.assertClean('HR observes that out-of-order finalization had no effect');
  903 |       },
  904 |     });
  905 |   } finally {
  906 |     if (frozenClock) await clearClock(request);
  907 |     await hr.context.close();
  908 |   }
  909 | });
  910 | 
```