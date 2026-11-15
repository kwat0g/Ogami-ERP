# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-profile.process.spec.ts >> HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval
- Location: e2e-process/hr-profile.process.spec.ts:193:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('E2E BANK 5A7AB9A0', { exact: true })
Expected: visible
Error: strict mode violation: getByText('E2E BANK 5A7AB9A0', { exact: true }) resolved to 2 elements:
    1) <dd class="break-words font-mono tabular-nums text-secondary">E2E BANK 5A7AB9A0</dd> aka getByLabel('Records').getByText('E2E BANK 5A7AB9A0')
    2) <dd class="break-words font-mono tabular-nums text-secondary">E2E BANK 5A7AB9A0</dd> aka getByRole('region', { name: 'Requested changes' }).getByText('E2E BANK 5A7AB9A0')

Call log:
  - Expect "toBeVisible" getByText('E2E BANK 5A7AB9A0', { exact: true }) with timeout 8000ms
  - waiting for getByText('E2E BANK 5A7AB9A0', { exact: true })

```

# Test source

```ts
  197 |   const employeeUser = actorByName(actors, 'employee');
  198 |   const hrUser = actorByName(actors, 'hr_officer');
  199 |   const financeUser = actorByName(actors, 'finance_officer');
  200 |   const employee = await actorPage(browser, employeeUser.actor);
  201 |   const hr = await actorPage(browser, hrUser.actor);
  202 |   const finance = await actorPage(browser, financeUser.actor);
  203 |   const journey = createJourneyContext('HR07-BANK-CHANGE');
  204 |   const seenIdentifiers = new Set<string>();
  205 |   const bankName = `E2E BANK ${journey.runId.slice(0, 8).toUpperCase()}`;
  206 |   const accountNumber = `E2E${journey.runId.replaceAll('-', '').slice(0, 10).toUpperCase()}7426`;
  207 |   const employeeRows = await readOnlyDatabaseJson<EmployeeBaseline[]>(`
  208 |     SELECT employee.id AS employee_id, employee.employee_no,
  209 |            employee.first_name, employee.last_name, employee.bank_name,
  210 |            employee.bank_account_no AS bank_account_cipher
  211 |       FROM employees employee
  212 |       JOIN users actor ON actor.employee_id = employee.id
  213 |      WHERE actor.email = ${sqlText(employeeUser.email)}
  214 |   `);
  215 |   expect(employeeRows, 'the E2E employee actor should resolve to one seeded employee').toHaveLength(1);
  216 |   const baseline = employeeRows[0];
  217 |   const employeeName = `${baseline.first_name} ${baseline.last_name}`;
  218 |   const mailBefore = await mailpitForActors([employeeUser.email, hrUser.email, financeUser.email]);
  219 |   let requestHashId = '';
  220 | 
  221 |   try {
  222 |     await runProcessStep({
  223 |       name: plannedSteps[0].name,
  224 |       actor: plannedSteps[0].actor,
  225 |       uiPath: plannedSteps[0].uiPath,
  226 |       expectedStatus: plannedSteps[0].expectedStatus,
  227 |       page: employee.page,
  228 |       plannedSteps,
  229 |       context: journey,
  230 |       testInfo,
  231 |       action: async () => {
  232 |         // Start at the authenticated landing page established by UI sign-in;
  233 |         // then follow the profile dropdown's self-service menu item.
  234 |         await employee.page.goto('/dashboard');
  235 |         const profileMenuButton = employee.page.getByRole('button', { name: `Profile menu for ${employeeName}`, exact: true });
  236 |         await expect(profileMenuButton).toBeVisible();
  237 |         await profileMenuButton.click();
  238 |         await employee.page.getByRole('menu').getByRole('menuitem', { name: 'Profile', exact: true }).click();
  239 |         await expect(employee.page.getByRole('heading', { name: /E2E employee/i })).toBeVisible();
  240 |         await expect(employee.page.getByRole('heading', { name: 'Bank account', exact: true })).toBeVisible();
  241 |         await expect(employee.page.getByRole('button', { name: 'Request update', exact: true })).toBeVisible();
  242 |         await expect(employee.page.getByText('No change requests', { exact: true })).toBeVisible();
  243 | 
  244 |         await openQueue(hr, 'Human Resources', 'Profile Change Requests');
  245 |         await expect(hr.page.getByRole('heading', { name: 'No requests', exact: true })).toBeVisible();
  246 |         await openQueue(finance, 'Payroll', 'Bank Account Changes');
  247 |         await expect(finance.page.getByRole('heading', { name: 'No requests', exact: true })).toBeVisible();
  248 |       },
  249 |       verify: async () => {
  250 |         expect(await requestSnapshot(employeeUser.email, journey.startedAt)).toHaveLength(0);
  251 |         expect(baseline.bank_name).toBeNull();
  252 |         expect(baseline.bank_account_cipher).toBeNull();
  253 |       },
  254 |       assertInvariants: async () => {
  255 |         employee.diagnostics.assertClean('employee baseline profile view');
  256 |         hr.diagnostics.assertClean('HR baseline profile queue');
  257 |         finance.diagnostics.assertClean('Finance baseline bank-change queue');
  258 |       },
  259 |     });
  260 | 
  261 |     await runProcessStep({
  262 |       name: plannedSteps[1].name,
  263 |       actor: plannedSteps[1].actor,
  264 |       uiPath: plannedSteps[1].uiPath,
  265 |       expectedStatus: plannedSteps[1].expectedStatus,
  266 |       page: employee.page,
  267 |       plannedSteps,
  268 |       context: journey,
  269 |       testInfo,
  270 |       action: async () => {
  271 |         await employee.page.getByRole('button', { name: 'Request update', exact: true }).click();
  272 |         const dialog = employee.page.getByRole('dialog', { name: 'Request Bank Account Update' });
  273 |         await expect(dialog).toBeVisible();
  274 |         await dialog.getByLabel('Bank name').fill(bankName);
  275 |         await dialog.getByLabel('New account number').fill(accountNumber);
  276 |         const responsePromise = employee.page.waitForResponse((response) =>
  277 |           response.url().includes('/hr/self-service/profile/request-update') && response.request().method() === 'POST',
  278 |         );
  279 |         await dialog.getByRole('button', { name: 'Submit request', exact: true }).click();
  280 |         const response = await responsePromise;
  281 |         expect(response.status(), 'employee UI request should be created').toBe(201);
  282 |         const payload = await response.json() as { data?: { id?: string; status?: string } };
  283 |         requestHashId = payload.data?.id ?? '';
  284 |         expect(requestHashId, 'UI create response should provide the public request identifier').not.toBe('');
  285 |         expect(payload.data?.status).toBe('pending');
  286 |         journey.values.profileUpdateRequestId = requestHashId;
  287 |         journey.values.bankName = bankName;
  288 |         journey.values.accountLast4 = accountNumber.slice(-4);
  289 |         expect(seenIdentifiers.has(requestHashId), 'the UI should not return a duplicate request identifier').toBe(false);
  290 |         seenIdentifiers.add(requestHashId);
  291 | 
  292 |         await expect(employee.page.getByText('Bank change submitted — requires HR + Finance approval.', { exact: true })).toBeVisible();
  293 |         await expect(employee.page.getByText('Pending HR', { exact: true })).toBeVisible();
  294 |         await expect(hr.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(1);
  295 |         await clickBadgeHandoff(hr, 'Profile Change Requests', 1);
  296 |         await expandEmployeeRequest(hr, employeeName);
> 297 |         await expect(hr.page.getByText(bankName, { exact: true })).toBeVisible();
      |                                                                    ^ Error: expect(locator).toBeVisible() failed
  298 |         await expect(hr.page.getByRole('button', { name: 'HR Approve', exact: true })).toBeVisible();
  299 |         await expect(hr.page.getByRole('button', { name: 'Finance Approve', exact: true })).toHaveCount(0);
  300 |         await expect(finance.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(0);
  301 |       },
  302 |       verify: async () => {
  303 |         const record = await expectRequestStatus(employeeUser.email, journey.startedAt, 'pending');
  304 |         expect(record.requires_finance).toBe(true);
  305 |         expect(record.requester_email).toBe(employeeUser.email);
  306 |         expect(record.employee_bank_name).toBe(baseline.bank_name);
  307 |         expect(record.employee_bank_account_cipher).toBe(baseline.bank_account_cipher);
  308 |         expect(record.hr_reviewer_email).toBeNull();
  309 |         expect(record.finance_reviewer_email).toBeNull();
  310 |         expect(record.notification_count).toBe(0);
  311 |       },
  312 |       assertInvariants: async () => {
  313 |         await expect(employee.page.getByText('Pending HR', { exact: true })).toBeVisible();
  314 |         const badge = hr.page.getByRole('button', { name: /View all Profile Change Requests signals: 1 Awaiting my action/ });
  315 |         await expect(badge).toBeVisible();
  316 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  317 |         employee.diagnostics.assertClean('employee submits bank change');
  318 |         hr.diagnostics.assertClean('HR receives bank change');
  319 |         finance.diagnostics.assertClean('Finance has no pre-HR bank change');
  320 |       },
  321 |     });
  322 |     await captureAnnotatedStep(hr.page, 'hr_officer', 'HR finds bank change in actionable queue', 2, testInfo);
  323 | 
  324 |     await runProcessStep({
  325 |       name: plannedSteps[2].name,
  326 |       actor: plannedSteps[2].actor,
  327 |       uiPath: plannedSteps[2].uiPath,
  328 |       expectedStatus: plannedSteps[2].expectedStatus,
  329 |       page: hr.page,
  330 |       plannedSteps,
  331 |       context: journey,
  332 |       testInfo,
  333 |       action: async () => {
  334 |         const responsePromise = hr.page.waitForResponse((response) =>
  335 |           response.url().includes(`/hr/profile-update-requests/${requestHashId}/review`) && response.request().method() === 'PATCH',
  336 |         );
  337 |         await hr.page.getByRole('button', { name: 'HR Approve', exact: true }).click();
  338 |         const confirm = hr.page.getByRole('dialog');
  339 |         await expect(confirm.getByText(/change still needs Finance approval/i)).toBeVisible();
  340 |         await confirm.getByRole('button', { name: 'Approve', exact: true }).click();
  341 |         const response = await responsePromise;
  342 |         expect(response.status(), 'HR UI approval should be accepted').toBe(200);
  343 |         await expect(hr.page.getByText('Awaiting Finance', { exact: true })).toBeVisible();
  344 |         await expect(employee.page.getByText('Awaiting Finance', { exact: true })).toBeVisible();
  345 |         const hrRow = hr.page.getByRole('row').filter({ hasText: employeeName });
  346 |         await expect(hrRow.getByRole('button', { name: 'HR Approve', exact: true })).toHaveCount(0);
  347 |         await expect(finance.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(1);
  348 |         await clickBadgeHandoff(finance, 'Bank Account Changes', 1);
  349 |         await expandEmployeeRequest(finance, employeeName);
  350 |         await expect(finance.page.getByText(`HR approved by E2E hr officer`, { exact: true })).toBeVisible();
  351 |         await expect(finance.page.getByRole('button', { name: 'Finance Approve', exact: true })).toBeVisible();
  352 |         await expect(finance.page.getByRole('button', { name: 'HR Approve', exact: true })).toHaveCount(0);
  353 |       },
  354 |       verify: async () => {
  355 |         const record = await expectRequestStatus(employeeUser.email, journey.startedAt, 'pending_finance');
  356 |         expect(record.requires_finance).toBe(true);
  357 |         expect(record.hr_reviewer_email).toBe(hrUser.email);
  358 |         expect(record.finance_reviewer_email).toBeNull();
  359 |         expect(record.employee_bank_name).toBe(baseline.bank_name);
  360 |         expect(record.employee_bank_account_cipher).toBe(baseline.bank_account_cipher);
  361 |       },
  362 |       assertInvariants: async () => {
  363 |         await expect(employee.page.getByText('Awaiting Finance', { exact: true })).toBeVisible();
  364 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals: 1 Awaiting my action/ })).toBeVisible();
  365 |         await expect(hr.page.getByRole('button', { name: /View all Profile Change Requests signals/ })).toHaveCount(0);
  366 |         employee.diagnostics.assertClean('employee observes HR approval');
  367 |         hr.diagnostics.assertClean('HR approves first leg');
  368 |         finance.diagnostics.assertClean('Finance receives second leg');
  369 |       },
  370 |     });
  371 |     await captureAnnotatedStep(employee.page, 'employee', 'Employee sees Finance review pending', 3, testInfo);
  372 | 
  373 |     await runProcessStep({
  374 |       name: plannedSteps[3].name,
  375 |       actor: plannedSteps[3].actor,
  376 |       uiPath: plannedSteps[3].uiPath,
  377 |       expectedStatus: plannedSteps[3].expectedStatus,
  378 |       page: finance.page,
  379 |       plannedSteps,
  380 |       context: journey,
  381 |       testInfo,
  382 |       action: async () => {
  383 |         const responsePromise = finance.page.waitForResponse((response) =>
  384 |           response.url().includes(`/hr/profile-update-requests/${requestHashId}/finance-review`) && response.request().method() === 'PATCH',
  385 |         );
  386 |         await finance.page.getByRole('button', { name: 'Finance Approve', exact: true }).click();
  387 |         const confirm = finance.page.getByRole('dialog');
  388 |         await expect(confirm.getByText(/applies the new bank account/i)).toBeVisible();
  389 |         await confirm.getByRole('button', { name: 'Approve', exact: true }).click();
  390 |         const response = await responsePromise;
  391 |         expect(response.status(), 'Finance UI approval should be accepted').toBe(200);
  392 |         await expect(employee.page.getByText('Approved', { exact: true })).toBeVisible();
  393 |         await expect(employee.page.getByText(bankName, { exact: true })).toBeVisible();
  394 |         await expect(employee.page.getByText(accountNumber.slice(-4), { exact: true })).toBeVisible();
  395 |         await expect(employee.page.getByText(accountNumber, { exact: true })).toHaveCount(0);
  396 |         await expect(finance.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(0);
  397 |         await expect(hr.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(0);
```