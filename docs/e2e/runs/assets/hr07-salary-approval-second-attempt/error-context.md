# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-profile.process.spec.ts >> HR07-SALARY-APPROVAL — salary adjustment request is blocked by missing UI (GAP-UI-02)
- Location: e2e-process/hr-profile.process.spec.ts:1201:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('Compensation is managed through the salary-adjustment approval workflow.', { exact: true })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByText('Compensation is managed through the salary-adjustment approval workflow.', { exact: true }) with timeout 8000ms
  - waiting for getByText('Compensation is managed through the salary-adjustment approval workflow.', { exact: true })

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
    - link "…":
      - /url: /hr/employees/k0vVWAP1Be
    - img
    - text: Edit
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications":
    - img
  - 'status "Real-time updates: Live. Last updated 9:24 AM"': Live
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
  - link "Profile":
    - /url: /hr/employees/k0vVWAP1Be
    - img
    - text: Profile
  - heading "Edit E2E employee" [level=1]
  - text: E2E-2FDC0177057D3A5
  - group "Personal information":
    - text: Personal information First name
    - textbox "First name": E2E
    - text: Middle name
    - textbox "Middle name"
    - text: Last name
    - textbox "Last name": employee
    - text: Suffix
    - textbox "Suffix":
      - /placeholder: Jr., Sr., III
    - text: Birth date
    - textbox "Birth date": 1996-11-04
    - text: Gender
    - combobox "Gender":
      - option "— Select —"
      - option "Male" [selected]
      - option "Female"
    - text: Civil status
    - combobox "Civil status":
      - option "— Select —"
      - option "Single" [selected]
      - option "Married"
      - option "Widowed"
      - option "Separated"
      - option "Divorced"
    - text: Nationality
    - textbox "Nationality": Filipino
  - group "Address":
    - text: Address Street address
    - textbox "Street address"
    - text: Barangay
    - textbox "Barangay"
    - text: City
    - textbox "City"
    - text: Province
    - textbox "Province"
    - text: ZIP code
    - textbox "ZIP code":
      - /placeholder: "1234"
  - group "Contact":
    - text: Contact Mobile number
    - textbox "Mobile number":
      - /placeholder: 09XX XXX XXXX
    - text: 11 digits, starts with 09 Email
    - textbox "Email": e2e+employee@ogami-e2e.test
    - text: Emergency contact name
    - textbox "Emergency contact name"
    - text: Relation
    - textbox "Relation"
    - text: Emergency phone
    - textbox "Emergency phone":
      - /placeholder: 09XX XXX XXXX
    - text: 7-15 digits
  - group "Employment":
    - text: Employment Department
    - combobox "Department":
      - option "— Select —"
      - option "Admin & General Affairs"
      - option "Customer Service"
      - option "Executive"
      - option "Finance & Accounting"
      - option "Human Resources"
      - option "Import/Export"
      - option "Information Technology"
      - option "Maintenance & Engineering"
      - option "Mold Department"
      - option "Production" [selected]
      - option "Production Planning"
      - option "Purchasing & Procurement"
      - option "Quality Control"
      - option "Sales & Marketing"
      - option "Warehouse & Logistics"
    - text: Position
    - combobox "Position":
      - option "— Select —"
      - option "Plant Manager" [selected]
      - option "Processing Head"
      - option "Production Head"
      - option "Production Manager"
      - option "Production Operator"
    - text: Employment type
    - combobox "Employment type":
      - option "— Select —"
      - option "Regular" [selected]
      - option "Probationary"
      - option "Contractual"
      - option "Project-Based"
    - text: Pay type
    - combobox "Pay type" [disabled]:
      - option "— Select —"
      - option "Monthly" [selected]
      - option "Semi-monthly"
    - text: Date hired
    - textbox "Date hired": 2024-11-04
    - text: Date regularized
    - textbox "Date regularized": 2025-11-04
    - text: Compensation is managed through the salary-adjustment approval workflow.
    - link "Open salary adjustments":
      - /url: /hr/employees
    - text: .
  - group "Shift assignment · Changes take effect today.":
    - text: Shift assignment · Changes take effect today. Shift
    - combobox "Shift":
      - option "— Not assigned —" [selected]
      - option "Day Shift (6:00 AM–2:00 PM)"
      - option "Extended Day (6:00 AM–6:00 PM)"
      - option "Night Shift (6:00 PM–6:00 AM)"
      - option "Office Hours (8:00 AM–5:00 PM)"
  - group "Government IDs · Stored encrypted at rest. Digits-only — formatting is automatic.":
    - text: Government IDs · Stored encrypted at rest. Digits-only — formatting is automatic. SSS number
    - textbox "SSS number":
      - /placeholder: XX-XXXXXXX-X
    - text: 10 digits PhilHealth
    - textbox "PhilHealth":
      - /placeholder: XX-XXXXXXXXX-X
    - text: 12 digits Pag-IBIG
    - textbox "Pag-IBIG":
      - /placeholder: XXXX-XXXX-XXXX
    - text: 12 digits TIN
    - textbox "TIN":
      - /placeholder: XXX-XXX-XXX(-XXX)
    - text: 9 or 12 digits
  - group "Banking · read-only here":
    - text: Banking · read-only here Bank name
    - textbox "Bank name" [disabled]
    - text: Account number
    - textbox "Account number" [disabled]:
      - /placeholder: Enter bank account number
    - paragraph:
      - text: Bank details can't be changed here. They go through an approval request that HR and Finance review.
      - link "Open Bank Account Changes":
        - /url: /hr/profile-update-requests
      - text: .
  - paragraph: No changes to save.
  - button "Cancel"
  - button "Save changes" [disabled]
```

# Test source

```ts
  1148 |         const confirm = hr.page.getByRole('dialog');
  1149 |         await expect(confirm.getByText(/whitelisted fields will be applied/i)).toBeVisible();
  1150 |         await confirm.getByRole('button', { name: 'Approve', exact: true }).click();
  1151 |         const response = await responsePromise;
  1152 |         expect(response.status(), 'HR UI approval should be accepted').toBe(200);
  1153 | 
  1154 |         await expect(employee.page.getByRole('listitem').filter({ hasText: 'Mobile' }).getByText('Approved', { exact: true })).toBeVisible();
  1155 |         await expect(employee.page.getByText(mobileNumber, { exact: true })).toBeVisible();
  1156 |         const hrRow = requestRow(hr, employeeName, 'Approved');
  1157 |         await expect(hrRow).toBeVisible();
  1158 |         await expect(hrRow.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  1159 |         await expect(hrRow.getByRole('button', { name: 'Reject', exact: true })).toHaveCount(0);
  1160 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  1161 |       },
  1162 |       verify: async () => {
  1163 |         const rows = await ordinaryProfileRequestForJourney(employeeUser.email, journey.startedAt);
  1164 |         expect(rows).toHaveLength(1);
  1165 |         expect(rows[0].status).toBe('approved');
  1166 |         expect(rows[0].requires_finance).toBe(false);
  1167 |         expect(rows[0].requester_email).toBe(employeeUser.email);
  1168 |         expect(rows[0].hr_reviewer_email).toBe(hrUser.email);
  1169 |         expect(rows[0].employee_mobile_number).toBe(mobileNumber);
  1170 |         expect(rows[0].request_audit_count).toBeGreaterThanOrEqual(2);
  1171 |         expect(rows[0].notification_count).toBe(0);
  1172 | 
  1173 |         const audits = await ordinaryProfileAuditsForJourney(employeeUser.email, journey.startedAt);
  1174 |         const created = audits.find((audit) => audit.request_id === rows[0].request_id && audit.action === 'created');
  1175 |         const approved = audits.find((audit) => audit.request_id === rows[0].request_id && audit.status === 'approved');
  1176 |         expect(created).toBeDefined();
  1177 |         expect(approved).toBeDefined();
  1178 |         assertAuditActor({ actor_email: created?.actor_email ?? undefined }, employeeUser.email);
  1179 |         assertAuditActor({ actor_email: approved?.actor_email ?? undefined }, hrUser.email);
  1180 | 
  1181 |         const employeeAudits = await employeeMobileAudit(baseline.employee_id, journey.startedAt);
  1182 |         expect(employeeAudits.some((audit) => audit.actor_email === hrUser.email && audit.mobile_number_audited)).toBe(true);
  1183 |         const mailAfter = await mailpitForActors(recipients);
  1184 |         expect(mailAfter.map((message) => message.ID).sort()).toEqual(mailBefore.map((message) => message.ID).sort());
  1185 |       },
  1186 |       assertInvariants: async () => {
  1187 |         await expect(employee.page.getByText(/0 pending · 1 total/)).toBeVisible();
  1188 |         await expect(hr.page.getByRole('button', { name: /View all Profile Change Requests signals/ })).toHaveCount(0);
  1189 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  1190 |         employee.diagnostics.assertClean('employee sees the approved contact change live');
  1191 |         hr.diagnostics.assertClean('HR completes the ordinary profile review');
  1192 |         finance.diagnostics.assertClean('Finance has no action for the ordinary change');
  1193 |       },
  1194 |     });
  1195 |     await captureAnnotatedStep(employee.page, 'employee', 'Employee sees the approved contact value and closed request', 3, testInfo);
  1196 |   } finally {
  1197 |     await Promise.all([employee.context.close(), hr.context.close(), finance.context.close()]);
  1198 |   }
  1199 | });
  1200 | 
  1201 | test('HR07-SALARY-APPROVAL — salary adjustment request is blocked by missing UI (GAP-UI-02)', async ({ browser }, testInfo) => {
  1202 |   test.setTimeout(90_000);
  1203 | 
  1204 |   const actors = await loadActors();
  1205 |   const hrUser = actorByName(actors, 'hr_officer');
  1206 |   const employeeUser = actorByName(actors, 'employee');
  1207 |   const hr = await actorPage(browser, hrUser.actor);
  1208 |   const journey = createJourneyContext('HR07-SALARY-APPROVAL');
  1209 |   const employees = await readOnlyDatabaseJson<Array<{
  1210 |     employee_id: number;
  1211 |     employee_no: string;
  1212 |     first_name: string;
  1213 |     last_name: string;
  1214 |     basic_monthly_salary: string | null;
  1215 |     salary_adjustment_count: number;
  1216 |   }>>(`
  1217 |     SELECT employee.id AS employee_id, employee.employee_no,
  1218 |            employee.first_name, employee.last_name, employee.basic_monthly_salary,
  1219 |            (SELECT COUNT(*)::int FROM salary_adjustments adjustment
  1220 |              WHERE adjustment.employee_id = employee.id) AS salary_adjustment_count
  1221 |       FROM employees employee
  1222 |       JOIN users actor ON actor.employee_id = employee.id
  1223 |      WHERE actor.email = ${sqlText(employeeUser.email)}
  1224 |   `);
  1225 |   expect(employees, 'the seeded Employee should resolve to one compensation record').toHaveLength(1);
  1226 |   const target = employees[0];
  1227 |   expect(target.salary_adjustment_count, 'a fresh baseline should not contain prior salary transactions').toBe(0);
  1228 |   const employeeName = `${target.first_name} ${target.last_name}`;
  1229 |   const plannedSteps = [
  1230 |     { name: 'HR requests an employee salary adjustment through the UI', actor: 'hr_officer', uiPath: 'Human Resources → Employees → Employee detail → Edit / Salary Adjustments', expectedStatus: 'pending; PM checker receives the request' },
  1231 |     { name: 'Production Manager checks the salary adjustment', actor: 'production_manager', uiPath: 'Human Resources → Salary Adjustments → Approve or Reject', expectedStatus: 'pending for VP after PM approval; rejected terminal on PM rejection' },
  1232 |     { name: 'Vice President approves the final salary adjustment', actor: 'vice_president', uiPath: 'Human Resources → Salary Adjustments → Approve or Reject', expectedStatus: 'approved; pay history and effective-date behavior applied' },
  1233 |   ];
  1234 | 
  1235 |   try {
  1236 |     await test.step('HR inspects the employee compensation UI and salary-adjustment queue', async () => {
  1237 |       await hr.page.goto('/dashboard');
  1238 |       await expect(hr.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  1239 |       await openSidebarItem(hr, 'Human Resources', '/hr/employees', 'Employees');
  1240 |       await expect(hr.page.getByRole('heading', { name: 'Employees', exact: true })).toBeVisible();
  1241 |       await hr.page.getByPlaceholder('Search name or employee no…').fill(target.employee_no);
  1242 |       const employeeRow = hr.page.getByRole('row').filter({ hasText: target.employee_no });
  1243 |       await expect(employeeRow).toHaveCount(1);
  1244 |       await expect(employeeRow).toContainText(employeeName);
  1245 |       await employeeRow.click();
  1246 |       await expect(hr.page.getByRole('heading', { name: new RegExp(employeeName) })).toBeVisible();
  1247 |       await hr.page.getByRole('button', { name: 'Edit', exact: true }).click();
> 1248 |       await expect(hr.page.getByText('Compensation is managed through the salary-adjustment approval workflow.', { exact: true })).toBeVisible();
       |                                                                                                                                    ^ Error: expect(locator).toBeVisible() failed
  1249 |       await expect(hr.page.getByRole('link', { name: 'Open salary adjustments', exact: true })).toBeVisible();
  1250 |       await expect(hr.page.getByLabel('Monthly salary', { exact: true })).toHaveCount(0);
  1251 |       await expect(hr.page.getByLabel('Semi-monthly rate', { exact: true })).toHaveCount(0);
  1252 |       await expect(hr.page.getByRole('button', { name: /Request salary adjustment|New salary adjustment/i })).toHaveCount(0);
  1253 | 
  1254 |       await openSidebarItem(hr, 'Human Resources', '/hr/employees', 'Employees');
  1255 |       await hr.page.getByRole('button', { name: 'Salary Adjustments', exact: true }).click();
  1256 |       await expect(hr.page.getByRole('heading', { name: 'Salary Adjustments', exact: true })).toBeVisible();
  1257 |       await expect(hr.page.getByText('No adjustments', { exact: true })).toBeVisible();
  1258 |       await expect(hr.page.getByRole('button', { name: /Request salary adjustment|New salary adjustment/i })).toHaveCount(0);
  1259 |       journey.values.employeeNo = target.employee_no;
  1260 |       await captureAnnotatedStep(hr.page, 'hr_officer', 'Salary-adjustment queue exposes reviewers but no request action', 1, testInfo);
  1261 |       hr.diagnostics.assertClean('HR inspects salary adjustment entry points');
  1262 |     });
  1263 | 
  1264 |     await runProcessStep({
  1265 |       name: plannedSteps[0].name,
  1266 |       actor: plannedSteps[0].actor,
  1267 |       uiPath: plannedSteps[0].uiPath,
  1268 |       expectedStatus: plannedSteps[0].expectedStatus,
  1269 |       page: hr.page,
  1270 |       plannedSteps,
  1271 |       context: journey,
  1272 |       testInfo,
  1273 |       action: async () => {
  1274 |         test.fixme(true, 'GAP-UI-02: SalaryAdjustment has an API request endpoint and active PM → VP approval chain, but the SPA exposes no HR request form/action. The employee edit screen removes compensation fields; the salary-adjustment page is an approval queue only. Do not create this transactional record through the API.');
  1275 |       },
  1276 |       verify: async () => {
  1277 |         expect(target.salary_adjustment_count).toBe(0);
  1278 |       },
  1279 |       assertInvariants: async () => {
  1280 |         hr.diagnostics.assertClean('salary-adjustment request is blocked at the missing UI stage');
  1281 |       },
  1282 |     });
  1283 |   } finally {
  1284 |     await hr.context.close();
  1285 |   }
  1286 | });
  1287 | 
```