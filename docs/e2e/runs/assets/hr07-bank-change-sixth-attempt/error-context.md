# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-profile.process.spec.ts >> HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval
- Location: e2e-process/hr-profile.process.spec.ts:190:1

# Error details

```
Error: expect(page).toHaveURL(expected) failed

Expected pattern: /signal=awaiting_my_action/
Received string:  "https://nginx/hr/profile-update-requests"
Timeout: 8000ms

Call log:
  - Expect "toHaveURL" with timeout 8000ms
    19 × locator resolved to <html lang="en" data-theme="light">…</html>
       - unexpected value "https://nginx/hr/profile-update-requests"

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
    - text: Profile Update Requests
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications":
    - img
  - 'status "Real-time updates: Live. Last updated 6:48 AM"': Live
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
    - 'button "Human Resources: Profile changes you can review: 1" [disabled] [expanded]': Human Resources
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
        - 'button "View all Profile Change Requests signals: 1 Profile changes you can review"': "1"
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
  - heading "Profile Change Requests" [level=1]
  - text: 1 requests
  - button "Saved views": Views
  - text: 1 results
  - combobox "Status": All statuses
  - button "Show Status"
  - text: Showing 1 of 1 results, page 1
  - button "Customize columns":
    - img
    - text: Columns
  - radiogroup "Table density":
    - radio "Compact" [checked]
    - radio "Default"
    - radio "Spacious"
  - table:
    - rowgroup:
      - row "Expand row Employee Employee column width Change type Change type column width Fields Fields column width Requested Requested column width Status Status column width":
        - columnheader "Expand row"
        - columnheader "Employee Employee column width":
          - text: Employee
          - separator "Employee column width"
        - columnheader "Change type Change type column width":
          - text: Change type
          - separator "Change type column width"
        - columnheader "Fields Fields column width":
          - text: Fields
          - separator "Fields column width"
        - columnheader "Requested Requested column width":
          - text: Requested
          - separator "Requested column width"
        - columnheader "Status Status column width":
          - text: Status
          - separator "Status column width"
    - rowgroup:
      - row "Expand row E2E employee E2E employee E2E-2FDC0177057D3A5 · Production Banking 2 E2E employee Nov 4, 2026 · 2:48 PM Pending HR":
        - cell "Expand row":
          - button "Expand row":
            - img
        - cell "E2E employee E2E employee E2E-2FDC0177057D3A5 · Production":
          - img "E2E employee": EE
          - link "E2E employee":
            - /url: /hr/employees/k0vVWAP1Be
          - text: E2E-2FDC0177057D3A5 · Production
        - cell "Banking"
        - cell "2"
        - cell "E2E employee Nov 4, 2026 · 2:48 PM"
        - cell "Pending HR"
  - text: 1–1 of 1
  - button "First page" [disabled]:
    - img
  - button "Previous page" [disabled]:
    - img
  - text: Page 1 of 1
  - button "Next page" [disabled]:
    - img
  - button "Last page" [disabled]:
    - img
```

# Test source

```ts
  75  |   await target.click();
  76  | }
  77  | 
  78  | async function openQueue(session: ActorPage, section: string, heading: string): Promise<void> {
  79  |   // /dashboard is the destination used by the real sign-in flow; / is the
  80  |   // public marketing site and does not enter the authenticated ERP shell.
  81  |   await session.page.goto('/dashboard');
  82  |   await expect(session.page.getByRole('heading').first()).toBeVisible();
  83  |   await openSidebarItem(session, section, '/hr/profile-update-requests', heading);
  84  |   await expect(session.page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
  85  | }
  86  | 
  87  | async function requestSnapshot(requesterEmail: string, createdAfter: string, bankName?: string): Promise<RequestSnapshot[]> {
  88  |   return readOnlyDatabaseJson<RequestSnapshot[]>(`
  89  |     SELECT request.id AS request_id,
  90  |            request.status, request.requires_finance, request.employee_id,
  91  |            requester.email AS requester_email,
  92  |            employee.bank_name AS employee_bank_name,
  93  |            employee.bank_account_no AS employee_bank_account_cipher,
  94  |            hr.email AS hr_reviewer_email,
  95  |            finance.email AS finance_reviewer_email,
  96  |            (SELECT COUNT(*)::int FROM audit_logs audit
  97  |              WHERE audit.model_type ILIKE '%ProfileUpdateRequest'
  98  |                AND audit.model_id = request.id) AS request_audit_count,
  99  |            (SELECT COUNT(*)::int FROM audit_logs audit
  100 |              WHERE audit.model_type ILIKE '%Employee'
  101 |                AND audit.model_id = employee.id
  102 |                AND audit.new_values->>'bank_name' = ${sqlText(bankName ?? '')}) AS employee_bank_audit_count,
  103 |            (SELECT COUNT(*)::int FROM notifications notice
  104 |              WHERE notice.data->>'subject_type' ILIKE '%ProfileUpdateRequest'
  105 |                AND notice.data->>'subject_id' = request.id::text) AS notification_count
  106 |       FROM profile_update_requests request
  107 |       JOIN employees employee ON employee.id = request.employee_id
  108 |       JOIN users requester ON requester.id = request.requested_by
  109 |       LEFT JOIN users hr ON hr.id = request.reviewed_by
  110 |       LEFT JOIN users finance ON finance.id = request.finance_reviewed_by
  111 |      WHERE requester.email = ${sqlText(requesterEmail)}
  112 |        AND request.note = 'Bank account change'
  113 |        AND request.created_at >= ${sqlText(createdAfter)}::timestamptz
  114 |      ORDER BY request.id DESC
  115 |      LIMIT 1
  116 |   `);
  117 | }
  118 | 
  119 | async function employeeBankAudit(requesterEmail: string, createdAfter: string, bankName: string): Promise<AuditSnapshot[]> {
  120 |   return readOnlyDatabaseJson<AuditSnapshot[]>(`
  121 |     SELECT audit.action, actor.email AS actor_email,
  122 |            audit.new_values->>'bank_name' AS status
  123 |       FROM audit_logs audit
  124 |       JOIN profile_update_requests request ON audit.model_type ILIKE '%Employee'
  125 |        AND audit.model_id = request.employee_id
  126 |       JOIN users requester ON requester.id = request.requested_by
  127 |       LEFT JOIN users actor ON actor.id = audit.user_id
  128 |      WHERE requester.email = ${sqlText(requesterEmail)}
  129 |        AND request.note = 'Bank account change'
  130 |        AND request.created_at >= ${sqlText(createdAfter)}::timestamptz
  131 |        AND audit.new_values->>'bank_name' = ${sqlText(bankName)}
  132 |      ORDER BY audit.id
  133 |   `);
  134 | }
  135 | 
  136 | async function requestAudit(requesterEmail: string, createdAfter: string): Promise<AuditSnapshot[]> {
  137 |   return readOnlyDatabaseJson<AuditSnapshot[]>(`
  138 |     SELECT audit.action, actor.email AS actor_email,
  139 |            audit.new_values->>'status' AS status
  140 |       FROM audit_logs audit
  141 |       JOIN profile_update_requests request
  142 |         ON audit.model_type ILIKE '%ProfileUpdateRequest'
  143 |        AND audit.model_id = request.id
  144 |       JOIN users requester ON requester.id = request.requested_by
  145 |       LEFT JOIN users actor ON actor.id = audit.user_id
  146 |      WHERE requester.email = ${sqlText(requesterEmail)}
  147 |        AND request.note = 'Bank account change'
  148 |        AND request.created_at >= ${sqlText(createdAfter)}::timestamptz
  149 |      ORDER BY audit.id
  150 |   `);
  151 | }
  152 | 
  153 | async function expectRequestStatus(requesterEmail: string, createdAfter: string, expected: string): Promise<RequestSnapshot> {
  154 |   let rows: RequestSnapshot[] = [];
  155 |   await expect.poll(async () => {
  156 |     rows = await requestSnapshot(requesterEmail, createdAfter);
  157 |     return rows[0]?.status ?? 'missing';
  158 |   }, { timeout: 15_000, message: `profile update request should reach ${expected}` }).toBe(expected);
  159 |   if (!rows[0]) throw new Error(`Profile update request for ${requesterEmail} disappeared.`);
  160 |   return rows[0];
  161 | }
  162 | 
  163 | async function clickBadgeHandoff(session: ActorPage, itemLabel: string, expectedCount: number): Promise<void> {
  164 |   const badge = session.page.getByRole('button', {
  165 |     name: new RegExp(`View all ${itemLabel} signals: ${expectedCount}`),
  166 |   });
  167 |   await expect(badge, `${itemLabel} should expose the actionable work count`).toBeVisible();
  168 |   await expect(badge).toContainText(String(expectedCount));
  169 |   await badge.click();
  170 |   const signalMenu = session.page.getByRole('dialog', { name: `${itemLabel} signals` });
  171 |   const actionable = signalMenu.getByRole('link').filter({ hasText: 'Awaiting my action' });
  172 |   await expect(actionable, `${itemLabel} should expose the actionable queue link`).toHaveCount(1);
  173 |   await expect(actionable).toContainText(String(expectedCount));
  174 |   await actionable.click();
> 175 |   await expect(session.page).toHaveURL(/signal=awaiting_my_action/);
      |                              ^ Error: expect(page).toHaveURL(expected) failed
  176 | }
  177 | 
  178 | async function expandEmployeeRequest(session: ActorPage, employeeName: string): Promise<void> {
  179 |   const row = session.page.getByRole('row').filter({ hasText: employeeName });
  180 |   await expect(row, 'the employee request should be present in this role\'s actionable queue').toHaveCount(1);
  181 |   await row.locator('td').last().click();
  182 |   await expect(session.page.getByRole('region', { name: 'Requested changes' })).toBeVisible();
  183 | }
  184 | 
  185 | async function mailpitForActors(emails: string[]): Promise<MailpitSummary[]> {
  186 |   const messages = await Promise.all(emails.map((email) => findMailpitMessagesFor(email)));
  187 |   return messages.flat();
  188 | }
  189 | 
  190 | test('HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval', async ({ browser }, testInfo) => {
  191 |   test.setTimeout(180_000);
  192 | 
  193 |   const actors = await loadActors();
  194 |   const employeeUser = actorByName(actors, 'employee');
  195 |   const hrUser = actorByName(actors, 'hr_officer');
  196 |   const financeUser = actorByName(actors, 'finance_officer');
  197 |   const employee = await actorPage(browser, employeeUser.actor);
  198 |   const hr = await actorPage(browser, hrUser.actor);
  199 |   const finance = await actorPage(browser, financeUser.actor);
  200 |   const journey = createJourneyContext('HR07-BANK-CHANGE');
  201 |   const seenIdentifiers = new Set<string>();
  202 |   const bankName = `E2E BANK ${journey.runId.slice(0, 8).toUpperCase()}`;
  203 |   const accountNumber = `E2E${journey.runId.replaceAll('-', '').slice(0, 10).toUpperCase()}7426`;
  204 |   const employeeRows = await readOnlyDatabaseJson<EmployeeBaseline[]>(`
  205 |     SELECT employee.id AS employee_id, employee.employee_no,
  206 |            employee.first_name, employee.last_name, employee.bank_name,
  207 |            employee.bank_account_no AS bank_account_cipher
  208 |       FROM employees employee
  209 |       JOIN users actor ON actor.employee_id = employee.id
  210 |      WHERE actor.email = ${sqlText(employeeUser.email)}
  211 |   `);
  212 |   expect(employeeRows, 'the E2E employee actor should resolve to one seeded employee').toHaveLength(1);
  213 |   const baseline = employeeRows[0];
  214 |   const employeeName = `${baseline.first_name} ${baseline.last_name}`;
  215 |   const mailBefore = await mailpitForActors([employeeUser.email, hrUser.email, financeUser.email]);
  216 |   let requestHashId = '';
  217 | 
  218 |   try {
  219 |     await runProcessStep({
  220 |       name: plannedSteps[0].name,
  221 |       actor: plannedSteps[0].actor,
  222 |       uiPath: plannedSteps[0].uiPath,
  223 |       expectedStatus: plannedSteps[0].expectedStatus,
  224 |       page: employee.page,
  225 |       plannedSteps,
  226 |       context: journey,
  227 |       testInfo,
  228 |       action: async () => {
  229 |         // Start at the authenticated landing page established by UI sign-in;
  230 |         // then follow the profile dropdown's self-service menu item.
  231 |         await employee.page.goto('/dashboard');
  232 |         const profileMenuButton = employee.page.getByRole('button', { name: `Profile menu for ${employeeName}`, exact: true });
  233 |         await expect(profileMenuButton).toBeVisible();
  234 |         await profileMenuButton.click();
  235 |         await employee.page.getByRole('menu').getByRole('menuitem', { name: 'Profile', exact: true }).click();
  236 |         await expect(employee.page.getByRole('heading', { name: /E2E employee/i })).toBeVisible();
  237 |         await expect(employee.page.getByRole('heading', { name: 'Bank account', exact: true })).toBeVisible();
  238 |         await expect(employee.page.getByRole('button', { name: 'Request update', exact: true })).toBeVisible();
  239 |         await expect(employee.page.getByText('No change requests', { exact: true })).toBeVisible();
  240 | 
  241 |         await openQueue(hr, 'Human Resources', 'Profile Change Requests');
  242 |         await expect(hr.page.getByRole('heading', { name: 'No requests', exact: true })).toBeVisible();
  243 |         await openQueue(finance, 'Payroll', 'Bank Account Changes');
  244 |         await expect(finance.page.getByRole('heading', { name: 'No requests', exact: true })).toBeVisible();
  245 |       },
  246 |       verify: async () => {
  247 |         expect(await requestSnapshot(employeeUser.email, journey.startedAt)).toHaveLength(0);
  248 |         expect(baseline.bank_name).toBeNull();
  249 |         expect(baseline.bank_account_cipher).toBeNull();
  250 |       },
  251 |       assertInvariants: async () => {
  252 |         employee.diagnostics.assertClean('employee baseline profile view');
  253 |         hr.diagnostics.assertClean('HR baseline profile queue');
  254 |         finance.diagnostics.assertClean('Finance baseline bank-change queue');
  255 |       },
  256 |     });
  257 | 
  258 |     await runProcessStep({
  259 |       name: plannedSteps[1].name,
  260 |       actor: plannedSteps[1].actor,
  261 |       uiPath: plannedSteps[1].uiPath,
  262 |       expectedStatus: plannedSteps[1].expectedStatus,
  263 |       page: employee.page,
  264 |       plannedSteps,
  265 |       context: journey,
  266 |       testInfo,
  267 |       action: async () => {
  268 |         await employee.page.getByRole('button', { name: 'Request update', exact: true }).click();
  269 |         const dialog = employee.page.getByRole('dialog', { name: 'Request Bank Account Update' });
  270 |         await expect(dialog).toBeVisible();
  271 |         await dialog.getByLabel('Bank name').fill(bankName);
  272 |         await dialog.getByLabel('New account number').fill(accountNumber);
  273 |         const responsePromise = employee.page.waitForResponse((response) =>
  274 |           response.url().includes('/hr/self-service/profile/request-update') && response.request().method() === 'POST',
  275 |         );
```