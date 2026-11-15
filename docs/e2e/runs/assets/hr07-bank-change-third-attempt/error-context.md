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

Locator: getByText('No requests', { exact: true })
Expected: visible
Error: strict mode violation: getByText('No requests', { exact: true }) resolved to 2 elements:
    1) <h3 class="font-medium text-primary mb-1 text-sm">No requests</h3> aka getByLabel('Records').getByText('No requests')
    2) <h3 class="font-medium text-primary mb-1 text-sm">No requests</h3> aka getByRole('heading', { name: 'No requests' })

Call log:
  - Expect "toBeVisible" getByText('No requests', { exact: true }) with timeout 8000ms
  - waiting for getByText('No requests', { exact: true })

```

# Test source

```ts
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
  218 |         // then follow the profile dropdown's self-service menu item.
  219 |         await employee.page.goto('/dashboard');
  220 |         const profileMenuButton = employee.page.getByRole('button', { name: `Profile menu for ${employeeName}`, exact: true });
  221 |         await expect(profileMenuButton).toBeVisible();
  222 |         await profileMenuButton.click();
  223 |         await employee.page.getByRole('menu').getByRole('menuitem', { name: 'Profile', exact: true }).click();
  224 |         await expect(employee.page.getByRole('heading', { name: /E2E employee/i })).toBeVisible();
  225 |         await expect(employee.page.getByRole('heading', { name: 'Bank account', exact: true })).toBeVisible();
  226 |         await expect(employee.page.getByRole('button', { name: 'Request update', exact: true })).toBeVisible();
  227 |         await expect(employee.page.getByText('No change requests', { exact: true })).toBeVisible();
  228 | 
  229 |         await openQueue(hr, 'Human Resources', 'Profile Change Requests');
> 230 |         await expect(hr.page.getByText('No requests', { exact: true })).toBeVisible();
      |                                                                         ^ Error: expect(locator).toBeVisible() failed
  231 |         await openQueue(finance, 'Payroll', 'Bank Account Changes');
  232 |         await expect(finance.page.getByText('No requests', { exact: true })).toBeVisible();
  233 |       },
  234 |       verify: async () => {
  235 |         expect(await requestSnapshot('non-existent-e2e-profile-request')).toHaveLength(0);
  236 |         expect(baseline.bank_name).toBeNull();
  237 |         expect(baseline.bank_account_cipher).toBeNull();
  238 |       },
  239 |       assertInvariants: async () => {
  240 |         employee.diagnostics.assertClean('employee baseline profile view');
  241 |         hr.diagnostics.assertClean('HR baseline profile queue');
  242 |         finance.diagnostics.assertClean('Finance baseline bank-change queue');
  243 |       },
  244 |     });
  245 | 
  246 |     await runProcessStep({
  247 |       name: plannedSteps[1].name,
  248 |       actor: plannedSteps[1].actor,
  249 |       uiPath: plannedSteps[1].uiPath,
  250 |       expectedStatus: plannedSteps[1].expectedStatus,
  251 |       page: employee.page,
  252 |       plannedSteps,
  253 |       context: journey,
  254 |       testInfo,
  255 |       action: async () => {
  256 |         await employee.page.getByRole('button', { name: 'Request update', exact: true }).click();
  257 |         const dialog = employee.page.getByRole('dialog', { name: 'Request Bank Account Update' });
  258 |         await expect(dialog).toBeVisible();
  259 |         await dialog.getByLabel('Bank name').fill(bankName);
  260 |         await dialog.getByLabel('New account number').fill(accountNumber);
  261 |         const responsePromise = employee.page.waitForResponse((response) =>
  262 |           response.url().includes('/hr/self-service/profile/request-update') && response.request().method() === 'POST',
  263 |         );
  264 |         await dialog.getByRole('button', { name: 'Submit request', exact: true }).click();
  265 |         const response = await responsePromise;
  266 |         expect(response.status(), 'employee UI request should be created').toBe(201);
  267 |         const payload = await response.json() as { data?: { id?: string; status?: string } };
  268 |         requestHashId = payload.data?.id ?? '';
  269 |         expect(requestHashId, 'UI create response should provide the public request identifier').not.toBe('');
  270 |         expect(payload.data?.status).toBe('pending');
  271 |         journey.values.profileUpdateRequestId = requestHashId;
  272 |         journey.values.bankName = bankName;
  273 |         journey.values.accountLast4 = accountNumber.slice(-4);
  274 |         expect(seenIdentifiers.has(requestHashId), 'the UI should not return a duplicate request identifier').toBe(false);
  275 |         seenIdentifiers.add(requestHashId);
  276 | 
  277 |         await expect(employee.page.getByText('Bank change submitted — requires HR + Finance approval.', { exact: true })).toBeVisible();
  278 |         await expect(employee.page.getByText('Pending HR', { exact: true })).toBeVisible();
  279 |         await expect(hr.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(1);
  280 |         await clickBadgeHandoff(hr, 'Profile Change Requests', 1);
  281 |         await expandEmployeeRequest(hr, employeeName);
  282 |         await expect(hr.page.getByText(bankName, { exact: true })).toBeVisible();
  283 |         await expect(hr.page.getByRole('button', { name: 'HR Approve', exact: true })).toBeVisible();
  284 |         await expect(hr.page.getByRole('button', { name: 'Finance Approve', exact: true })).toHaveCount(0);
  285 |         await expect(finance.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(0);
  286 |       },
  287 |       verify: async () => {
  288 |         const record = await expectRequestStatus(requestHashId, 'pending');
  289 |         expect(record.requires_finance).toBe(true);
  290 |         expect(record.requester_email).toBe(employeeUser.email);
  291 |         expect(record.employee_bank_name).toBe(baseline.bank_name);
  292 |         expect(record.employee_bank_account_cipher).toBe(baseline.bank_account_cipher);
  293 |         expect(record.hr_reviewer_email).toBeNull();
  294 |         expect(record.finance_reviewer_email).toBeNull();
  295 |         expect(record.notification_count).toBe(0);
  296 |       },
  297 |       assertInvariants: async () => {
  298 |         await expect(employee.page.getByText('Pending HR', { exact: true })).toBeVisible();
  299 |         const badge = hr.page.getByRole('button', { name: /View all Profile Change Requests signals: 1 Awaiting my action/ });
  300 |         await expect(badge).toBeVisible();
  301 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  302 |         employee.diagnostics.assertClean('employee submits bank change');
  303 |         hr.diagnostics.assertClean('HR receives bank change');
  304 |         finance.diagnostics.assertClean('Finance has no pre-HR bank change');
  305 |       },
  306 |     });
  307 |     await captureAnnotatedStep(hr.page, 'hr_officer', 'HR finds bank change in actionable queue', 2, testInfo);
  308 | 
  309 |     await runProcessStep({
  310 |       name: plannedSteps[2].name,
  311 |       actor: plannedSteps[2].actor,
  312 |       uiPath: plannedSteps[2].uiPath,
  313 |       expectedStatus: plannedSteps[2].expectedStatus,
  314 |       page: hr.page,
  315 |       plannedSteps,
  316 |       context: journey,
  317 |       testInfo,
  318 |       action: async () => {
  319 |         const responsePromise = hr.page.waitForResponse((response) =>
  320 |           response.url().includes(`/hr/profile-update-requests/${requestHashId}/review`) && response.request().method() === 'PATCH',
  321 |         );
  322 |         await hr.page.getByRole('button', { name: 'HR Approve', exact: true }).click();
  323 |         const confirm = hr.page.getByRole('dialog');
  324 |         await expect(confirm.getByText(/change still needs Finance approval/i)).toBeVisible();
  325 |         await confirm.getByRole('button', { name: 'Approve', exact: true }).click();
  326 |         const response = await responsePromise;
  327 |         expect(response.status(), 'HR UI approval should be accepted').toBe(200);
  328 |         await expect(hr.page.getByText('Awaiting Finance', { exact: true })).toBeVisible();
  329 |         await expect(employee.page.getByText('Awaiting Finance', { exact: true })).toBeVisible();
  330 |         const hrRow = hr.page.getByRole('row').filter({ hasText: employeeName });
```