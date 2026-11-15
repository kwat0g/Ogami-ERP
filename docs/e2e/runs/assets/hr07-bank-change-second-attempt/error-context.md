# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-profile.process.spec.ts >> HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval
- Location: e2e-process/hr-profile.process.spec.ts:178:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('button', { name: 'My profile', exact: true })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByRole('button', { name: 'My profile', exact: true }) with timeout 8000ms
  - waiting for getByRole('button', { name: 'My profile', exact: true })

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
    - link "Dashboard":
      - /url: /dashboard
    - img
    - text: Default
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications":
    - img
  - 'status "Real-time updates: Live. Last updated 6:34 AM"': Live
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E employee":
    - img "E2E employee": EE
    - text: E2E employee
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
          - link "Approval Queue":
            - /url: /approvals
            - img
            - text: Approval Queue
        - listitem:
          - link "Notifications":
            - /url: /notifications
            - img
            - text: Notifications
- main:
  - heading "Welcome, E2E employee Updated just now" [level=1]
  - button "Customize dashboard":
    - img
    - text: Customize
  - button "Reset dashboard layout to role default":
    - img
    - text: Reset
  - button "Time in"
  - region "Needs your attention":
    - heading "Needs your attention" [level=2]
    - link "View action center":
      - /url: /action-center
    - status: All clear.
  - text: As of Nov 4, 2026 · 2:34 PM
  - heading "Latest Payslip" [level=3]
  - link "Open →":
    - /url: /self-service/payslips
  - button "Export Latest Payslip as CSV":
    - img
  - text: —
  - paragraph: No finalized payslip yet
  - paragraph: Updated 6:34 AM
  - heading "My Leave Balance" [level=3]
  - link "Open →":
    - /url: /self-service/leaves
  - button "Export My Leave Balance as CSV":
    - img
  - text: "0"
  - paragraph: remaining leave days
  - paragraph: Updated 6:34 AM
  - heading "My Shift Today" [level=3]
  - link "Open →":
    - /url: /self-service/dtr
  - button "Export My Shift Today as CSV":
    - img
  - text: —
  - paragraph: No hours recorded yet
  - paragraph: Updated 6:34 AM
  - heading "My Pending Requests" [level=3]
  - link "Open →":
    - /url: /self-service
  - button "Export My Pending Requests as CSV":
    - img
  - text: "0"
  - paragraph: your pending requests
  - paragraph: Updated 6:34 AM
```

# Test source

```ts
  120 |       FROM audit_logs audit
  121 |       JOIN profile_update_requests request ON request.hash_id = ${sqlText(requestHashId)}
  122 |       LEFT JOIN users actor ON actor.id = audit.user_id
  123 |      WHERE audit.model_type ILIKE '%Employee'
  124 |        AND audit.model_id = request.employee_id
  125 |        AND audit.new_values->>'bank_name' = ${sqlText(bankName)}
  126 |      ORDER BY audit.id
  127 |   `);
  128 | }
  129 | 
  130 | async function requestAudit(requestHashId: string): Promise<AuditSnapshot[]> {
  131 |   return readOnlyDatabaseJson<AuditSnapshot[]>(`
  132 |     SELECT audit.action, actor.email AS actor_email,
  133 |            audit.new_values->>'status' AS status
  134 |       FROM audit_logs audit
  135 |       JOIN profile_update_requests request
  136 |         ON audit.model_type ILIKE '%ProfileUpdateRequest'
  137 |        AND audit.model_id = request.id
  138 |       LEFT JOIN users actor ON actor.id = audit.user_id
  139 |      WHERE request.hash_id = ${sqlText(requestHashId)}
  140 |      ORDER BY audit.id
  141 |   `);
  142 | }
  143 | 
  144 | async function expectRequestStatus(requestHashId: string, expected: string): Promise<RequestSnapshot> {
  145 |   let rows: RequestSnapshot[] = [];
  146 |   await expect.poll(async () => {
  147 |     rows = await requestSnapshot(requestHashId);
  148 |     return rows[0]?.status ?? 'missing';
  149 |   }, { timeout: 15_000, message: `profile update request should reach ${expected}` }).toBe(expected);
  150 |   if (!rows[0]) throw new Error(`Profile update request ${requestHashId} disappeared.`);
  151 |   return rows[0];
  152 | }
  153 | 
  154 | async function clickBadgeHandoff(session: ActorPage, itemLabel: string, expectedCount: number): Promise<void> {
  155 |   const badge = session.page.getByRole('button', {
  156 |     name: new RegExp(`View all ${itemLabel} signals: ${expectedCount} Awaiting my action`),
  157 |   });
  158 |   await expect(badge, `${itemLabel} should expose the actionable work count`).toBeVisible();
  159 |   await expect(badge).toContainText(String(expectedCount));
  160 |   await badge.click();
  161 |   const signalMenu = session.page.getByRole('dialog', { name: `${itemLabel} signals` });
  162 |   await signalMenu.getByRole('link', { name: `Awaiting my action: ${expectedCount}` }).click();
  163 |   await expect(session.page).toHaveURL(/signal=awaiting_my_action/);
  164 | }
  165 | 
  166 | async function expandEmployeeRequest(session: ActorPage, employeeName: string): Promise<void> {
  167 |   const row = session.page.getByRole('row').filter({ hasText: employeeName });
  168 |   await expect(row, 'the employee request should be present in this role\'s actionable queue').toHaveCount(1);
  169 |   await row.locator('td').last().click();
  170 |   await expect(session.page.getByRole('region', { name: 'Requested changes' })).toBeVisible();
  171 | }
  172 | 
  173 | async function mailpitForActors(emails: string[]): Promise<MailpitSummary[]> {
  174 |   const messages = await Promise.all(emails.map((email) => findMailpitMessagesFor(email)));
  175 |   return messages.flat();
  176 | }
  177 | 
  178 | test('HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval', async ({ browser }, testInfo) => {
  179 |   test.setTimeout(180_000);
  180 | 
  181 |   const actors = await loadActors();
  182 |   const employeeUser = actorByName(actors, 'employee');
  183 |   const hrUser = actorByName(actors, 'hr_officer');
  184 |   const financeUser = actorByName(actors, 'finance_officer');
  185 |   const employee = await actorPage(browser, employeeUser.actor);
  186 |   const hr = await actorPage(browser, hrUser.actor);
  187 |   const finance = await actorPage(browser, financeUser.actor);
  188 |   const journey = createJourneyContext('HR07-BANK-CHANGE');
  189 |   const seenIdentifiers = new Set<string>();
  190 |   const bankName = `E2E BANK ${journey.runId.slice(0, 8).toUpperCase()}`;
  191 |   const accountNumber = `E2E${journey.runId.replaceAll('-', '').slice(0, 10).toUpperCase()}7426`;
  192 |   const employeeRows = await readOnlyDatabaseJson<EmployeeBaseline[]>(`
  193 |     SELECT employee.id AS employee_id, employee.employee_no,
  194 |            employee.first_name, employee.last_name, employee.bank_name,
  195 |            employee.bank_account_no AS bank_account_cipher
  196 |       FROM employees employee
  197 |       JOIN users actor ON actor.employee_id = employee.id
  198 |      WHERE actor.email = ${sqlText(employeeUser.email)}
  199 |   `);
  200 |   expect(employeeRows, 'the E2E employee actor should resolve to one seeded employee').toHaveLength(1);
  201 |   const baseline = employeeRows[0];
  202 |   const employeeName = `${baseline.first_name} ${baseline.last_name}`;
  203 |   const mailBefore = await mailpitForActors([employeeUser.email, hrUser.email, financeUser.email]);
  204 |   let requestHashId = '';
  205 | 
  206 |   try {
  207 |     await runProcessStep({
  208 |       name: plannedSteps[0].name,
  209 |       actor: plannedSteps[0].actor,
  210 |       uiPath: plannedSteps[0].uiPath,
  211 |       expectedStatus: plannedSteps[0].expectedStatus,
  212 |       page: employee.page,
  213 |       plannedSteps,
  214 |       context: journey,
  215 |       testInfo,
  216 |       action: async () => {
  217 |         // Start at the authenticated landing page established by UI sign-in;
  218 |         // then follow the in-app My profile action.
  219 |         await employee.page.goto('/dashboard');
> 220 |         await expect(employee.page.getByRole('button', { name: 'My profile', exact: true })).toBeVisible();
      |                                                                                              ^ Error: expect(locator).toBeVisible() failed
  221 |         await employee.page.getByRole('button', { name: 'My profile', exact: true }).click();
  222 |         await expect(employee.page.getByRole('heading', { name: /E2E employee/i })).toBeVisible();
  223 |         await expect(employee.page.getByRole('heading', { name: 'Bank account', exact: true })).toBeVisible();
  224 |         await expect(employee.page.getByRole('button', { name: 'Request update', exact: true })).toBeVisible();
  225 |         await expect(employee.page.getByText('No change requests', { exact: true })).toBeVisible();
  226 | 
  227 |         await openQueue(hr, 'Human Resources', 'Profile Change Requests');
  228 |         await expect(hr.page.getByText('No requests', { exact: true })).toBeVisible();
  229 |         await openQueue(finance, 'Payroll', 'Bank Account Changes');
  230 |         await expect(finance.page.getByText('No requests', { exact: true })).toBeVisible();
  231 |       },
  232 |       verify: async () => {
  233 |         expect(await requestSnapshot('non-existent-e2e-profile-request')).toHaveLength(0);
  234 |         expect(baseline.bank_name).toBeNull();
  235 |         expect(baseline.bank_account_cipher).toBeNull();
  236 |       },
  237 |       assertInvariants: async () => {
  238 |         employee.diagnostics.assertClean('employee baseline profile view');
  239 |         hr.diagnostics.assertClean('HR baseline profile queue');
  240 |         finance.diagnostics.assertClean('Finance baseline bank-change queue');
  241 |       },
  242 |     });
  243 | 
  244 |     await runProcessStep({
  245 |       name: plannedSteps[1].name,
  246 |       actor: plannedSteps[1].actor,
  247 |       uiPath: plannedSteps[1].uiPath,
  248 |       expectedStatus: plannedSteps[1].expectedStatus,
  249 |       page: employee.page,
  250 |       plannedSteps,
  251 |       context: journey,
  252 |       testInfo,
  253 |       action: async () => {
  254 |         await employee.page.getByRole('button', { name: 'Request update', exact: true }).click();
  255 |         const dialog = employee.page.getByRole('dialog', { name: 'Request Bank Account Update' });
  256 |         await expect(dialog).toBeVisible();
  257 |         await dialog.getByLabel('Bank name').fill(bankName);
  258 |         await dialog.getByLabel('New account number').fill(accountNumber);
  259 |         const responsePromise = employee.page.waitForResponse((response) =>
  260 |           response.url().includes('/hr/self-service/profile/request-update') && response.request().method() === 'POST',
  261 |         );
  262 |         await dialog.getByRole('button', { name: 'Submit request', exact: true }).click();
  263 |         const response = await responsePromise;
  264 |         expect(response.status(), 'employee UI request should be created').toBe(201);
  265 |         const payload = await response.json() as { data?: { id?: string; status?: string } };
  266 |         requestHashId = payload.data?.id ?? '';
  267 |         expect(requestHashId, 'UI create response should provide the public request identifier').not.toBe('');
  268 |         expect(payload.data?.status).toBe('pending');
  269 |         journey.values.profileUpdateRequestId = requestHashId;
  270 |         journey.values.bankName = bankName;
  271 |         journey.values.accountLast4 = accountNumber.slice(-4);
  272 |         expect(seenIdentifiers.has(requestHashId), 'the UI should not return a duplicate request identifier').toBe(false);
  273 |         seenIdentifiers.add(requestHashId);
  274 | 
  275 |         await expect(employee.page.getByText('Bank change submitted — requires HR + Finance approval.', { exact: true })).toBeVisible();
  276 |         await expect(employee.page.getByText('Pending HR', { exact: true })).toBeVisible();
  277 |         await expect(hr.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(1);
  278 |         await clickBadgeHandoff(hr, 'Profile Change Requests', 1);
  279 |         await expandEmployeeRequest(hr, employeeName);
  280 |         await expect(hr.page.getByText(bankName, { exact: true })).toBeVisible();
  281 |         await expect(hr.page.getByRole('button', { name: 'HR Approve', exact: true })).toBeVisible();
  282 |         await expect(hr.page.getByRole('button', { name: 'Finance Approve', exact: true })).toHaveCount(0);
  283 |         await expect(finance.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(0);
  284 |       },
  285 |       verify: async () => {
  286 |         const record = await expectRequestStatus(requestHashId, 'pending');
  287 |         expect(record.requires_finance).toBe(true);
  288 |         expect(record.requester_email).toBe(employeeUser.email);
  289 |         expect(record.employee_bank_name).toBe(baseline.bank_name);
  290 |         expect(record.employee_bank_account_cipher).toBe(baseline.bank_account_cipher);
  291 |         expect(record.hr_reviewer_email).toBeNull();
  292 |         expect(record.finance_reviewer_email).toBeNull();
  293 |         expect(record.notification_count).toBe(0);
  294 |       },
  295 |       assertInvariants: async () => {
  296 |         await expect(employee.page.getByText('Pending HR', { exact: true })).toBeVisible();
  297 |         const badge = hr.page.getByRole('button', { name: /View all Profile Change Requests signals: 1 Awaiting my action/ });
  298 |         await expect(badge).toBeVisible();
  299 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  300 |         employee.diagnostics.assertClean('employee submits bank change');
  301 |         hr.diagnostics.assertClean('HR receives bank change');
  302 |         finance.diagnostics.assertClean('Finance has no pre-HR bank change');
  303 |       },
  304 |     });
  305 |     await captureAnnotatedStep(hr.page, 'hr_officer', 'HR finds bank change in actionable queue', 2, testInfo);
  306 | 
  307 |     await runProcessStep({
  308 |       name: plannedSteps[2].name,
  309 |       actor: plannedSteps[2].actor,
  310 |       uiPath: plannedSteps[2].uiPath,
  311 |       expectedStatus: plannedSteps[2].expectedStatus,
  312 |       page: hr.page,
  313 |       plannedSteps,
  314 |       context: journey,
  315 |       testInfo,
  316 |       action: async () => {
  317 |         const responsePromise = hr.page.waitForResponse((response) =>
  318 |           response.url().includes(`/hr/profile-update-requests/${requestHashId}/review`) && response.request().method() === 'PATCH',
  319 |         );
  320 |         await hr.page.getByRole('button', { name: 'HR Approve', exact: true }).click();
```