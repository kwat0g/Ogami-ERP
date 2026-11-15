# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-profile.process.spec.ts >> HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval
- Location: e2e-process/hr-profile.process.spec.ts:187:1

# Error details

```
Error: Profile Change Requests should expose the actionable work count

expect(locator).toBeVisible() failed

Locator: getByRole('button', { name: /View all Profile Change Requests signals: 1 Awaiting my action/ })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Profile Change Requests should expose the actionable work count getByRole('button', { name: /View all Profile Change Requests signals: 1 Awaiting my action/ }) with timeout 8000ms
  - waiting for getByRole('button', { name: /View all Profile Change Requests signals: 1 Awaiting my action/ })

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
  - 'status "Real-time updates: Reconnecting. Last updated 6:45 AM"': Reconnecting
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
          - 'button "View all Action Center signals: 13 Open items needing attention"': "13"
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
      - row "Expand row E2E employee E2E employee E2E-2FDC0177057D3A5 · Production Banking 2 E2E employee Nov 4, 2026 · 2:44 PM Pending HR":
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
        - cell "E2E employee Nov 4, 2026 · 2:44 PM"
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
  67  |   await expect(target, `the ${label} route should be available in this role's sidebar`).toHaveCount(1);
  68  |   if (!(await target.isVisible())) {
  69  |     const module = session.page.locator(`[data-module="${section}"]`);
  70  |     const toggle = module.getByRole('button');
  71  |     await expect(toggle, `the ${section} module should be expandable`).toHaveCount(1);
  72  |     if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click();
  73  |   }
  74  |   await expect(target).toBeVisible();
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
  165 |     name: new RegExp(`View all ${itemLabel} signals: ${expectedCount} Awaiting my action`),
  166 |   });
> 167 |   await expect(badge, `${itemLabel} should expose the actionable work count`).toBeVisible();
      |                                                                               ^ Error: Profile Change Requests should expose the actionable work count
  168 |   await expect(badge).toContainText(String(expectedCount));
  169 |   await badge.click();
  170 |   const signalMenu = session.page.getByRole('dialog', { name: `${itemLabel} signals` });
  171 |   await signalMenu.getByRole('link', { name: `Awaiting my action: ${expectedCount}` }).click();
  172 |   await expect(session.page).toHaveURL(/signal=awaiting_my_action/);
  173 | }
  174 | 
  175 | async function expandEmployeeRequest(session: ActorPage, employeeName: string): Promise<void> {
  176 |   const row = session.page.getByRole('row').filter({ hasText: employeeName });
  177 |   await expect(row, 'the employee request should be present in this role\'s actionable queue').toHaveCount(1);
  178 |   await row.locator('td').last().click();
  179 |   await expect(session.page.getByRole('region', { name: 'Requested changes' })).toBeVisible();
  180 | }
  181 | 
  182 | async function mailpitForActors(emails: string[]): Promise<MailpitSummary[]> {
  183 |   const messages = await Promise.all(emails.map((email) => findMailpitMessagesFor(email)));
  184 |   return messages.flat();
  185 | }
  186 | 
  187 | test('HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval', async ({ browser }, testInfo) => {
  188 |   test.setTimeout(180_000);
  189 | 
  190 |   const actors = await loadActors();
  191 |   const employeeUser = actorByName(actors, 'employee');
  192 |   const hrUser = actorByName(actors, 'hr_officer');
  193 |   const financeUser = actorByName(actors, 'finance_officer');
  194 |   const employee = await actorPage(browser, employeeUser.actor);
  195 |   const hr = await actorPage(browser, hrUser.actor);
  196 |   const finance = await actorPage(browser, financeUser.actor);
  197 |   const journey = createJourneyContext('HR07-BANK-CHANGE');
  198 |   const seenIdentifiers = new Set<string>();
  199 |   const bankName = `E2E BANK ${journey.runId.slice(0, 8).toUpperCase()}`;
  200 |   const accountNumber = `E2E${journey.runId.replaceAll('-', '').slice(0, 10).toUpperCase()}7426`;
  201 |   const employeeRows = await readOnlyDatabaseJson<EmployeeBaseline[]>(`
  202 |     SELECT employee.id AS employee_id, employee.employee_no,
  203 |            employee.first_name, employee.last_name, employee.bank_name,
  204 |            employee.bank_account_no AS bank_account_cipher
  205 |       FROM employees employee
  206 |       JOIN users actor ON actor.employee_id = employee.id
  207 |      WHERE actor.email = ${sqlText(employeeUser.email)}
  208 |   `);
  209 |   expect(employeeRows, 'the E2E employee actor should resolve to one seeded employee').toHaveLength(1);
  210 |   const baseline = employeeRows[0];
  211 |   const employeeName = `${baseline.first_name} ${baseline.last_name}`;
  212 |   const mailBefore = await mailpitForActors([employeeUser.email, hrUser.email, financeUser.email]);
  213 |   let requestHashId = '';
  214 | 
  215 |   try {
  216 |     await runProcessStep({
  217 |       name: plannedSteps[0].name,
  218 |       actor: plannedSteps[0].actor,
  219 |       uiPath: plannedSteps[0].uiPath,
  220 |       expectedStatus: plannedSteps[0].expectedStatus,
  221 |       page: employee.page,
  222 |       plannedSteps,
  223 |       context: journey,
  224 |       testInfo,
  225 |       action: async () => {
  226 |         // Start at the authenticated landing page established by UI sign-in;
  227 |         // then follow the profile dropdown's self-service menu item.
  228 |         await employee.page.goto('/dashboard');
  229 |         const profileMenuButton = employee.page.getByRole('button', { name: `Profile menu for ${employeeName}`, exact: true });
  230 |         await expect(profileMenuButton).toBeVisible();
  231 |         await profileMenuButton.click();
  232 |         await employee.page.getByRole('menu').getByRole('menuitem', { name: 'Profile', exact: true }).click();
  233 |         await expect(employee.page.getByRole('heading', { name: /E2E employee/i })).toBeVisible();
  234 |         await expect(employee.page.getByRole('heading', { name: 'Bank account', exact: true })).toBeVisible();
  235 |         await expect(employee.page.getByRole('button', { name: 'Request update', exact: true })).toBeVisible();
  236 |         await expect(employee.page.getByText('No change requests', { exact: true })).toBeVisible();
  237 | 
  238 |         await openQueue(hr, 'Human Resources', 'Profile Change Requests');
  239 |         await expect(hr.page.getByRole('heading', { name: 'No requests', exact: true })).toBeVisible();
  240 |         await openQueue(finance, 'Payroll', 'Bank Account Changes');
  241 |         await expect(finance.page.getByRole('heading', { name: 'No requests', exact: true })).toBeVisible();
  242 |       },
  243 |       verify: async () => {
  244 |         expect(await requestSnapshot(employeeUser.email, journey.startedAt)).toHaveLength(0);
  245 |         expect(baseline.bank_name).toBeNull();
  246 |         expect(baseline.bank_account_cipher).toBeNull();
  247 |       },
  248 |       assertInvariants: async () => {
  249 |         employee.diagnostics.assertClean('employee baseline profile view');
  250 |         hr.diagnostics.assertClean('HR baseline profile queue');
  251 |         finance.diagnostics.assertClean('Finance baseline bank-change queue');
  252 |       },
  253 |     });
  254 | 
  255 |     await runProcessStep({
  256 |       name: plannedSteps[1].name,
  257 |       actor: plannedSteps[1].actor,
  258 |       uiPath: plannedSteps[1].uiPath,
  259 |       expectedStatus: plannedSteps[1].expectedStatus,
  260 |       page: employee.page,
  261 |       plannedSteps,
  262 |       context: journey,
  263 |       testInfo,
  264 |       action: async () => {
  265 |         await employee.page.getByRole('button', { name: 'Request update', exact: true }).click();
  266 |         const dialog = employee.page.getByRole('dialog', { name: 'Request Bank Account Update' });
  267 |         await expect(dialog).toBeVisible();
```