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

Locator: getByText('Awaiting Finance', { exact: true })
Expected: visible
Error: strict mode violation: getByText('Awaiting Finance', { exact: true }) resolved to 3 elements:
    1) <option value="pending_finance">Awaiting Finance</option> aka locator('select')
    2) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-caption font-medium leading-tight whitespace-nowrap transition-colors duration-fast bg-info-bg text-info-fg">Awaiting Finance</span> aka getByLabel('Records').getByText('Awaiting Finance', { exact: true })
    3) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-caption font-medium leading-tight whitespace-nowrap transition-colors duration-fast bg-info-bg text-info-fg">Awaiting Finance</span> aka getByRole('table').getByText('Awaiting Finance', { exact: true })

Call log:
  - Expect "toBeVisible" getByText('Awaiting Finance', { exact: true }) with timeout 8000ms
  - waiting for getByText('Awaiting Finance', { exact: true })

```

# Test source

```ts
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
  297 |         await expect(hr.page.getByRole('region', { name: 'Requested changes' }).getByText(bankName, { exact: true })).toBeVisible();
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
  314 |         const badge = hr.page.getByRole('button', { name: /View all Profile Change Requests signals: 1 Profile changes you can review/ });
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
> 343 |         await expect(hr.page.getByText('Awaiting Finance', { exact: true })).toBeVisible();
      |                                                                              ^ Error: expect(locator).toBeVisible() failed
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
  398 |       },
  399 |       verify: async () => {
  400 |         const record = await expectRequestStatus(employeeUser.email, journey.startedAt, 'approved');
  401 |         const verifiedRecord = (await requestSnapshot(employeeUser.email, journey.startedAt, bankName))[0];
  402 |         expect(verifiedRecord, 'read-only verification should find the approved profile change').toBeDefined();
  403 |         const finalRecord = verifiedRecord!;
  404 |         expect(finalRecord.request_id).toBe(record.request_id);
  405 |         expect(finalRecord.requires_finance).toBe(true);
  406 |         expect(finalRecord.requester_email).toBe(employeeUser.email);
  407 |         expect(finalRecord.hr_reviewer_email).toBe(hrUser.email);
  408 |         expect(finalRecord.finance_reviewer_email).toBe(financeUser.email);
  409 |         expect(finalRecord.employee_bank_name).toBe(bankName);
  410 |         expect(finalRecord.employee_bank_account_cipher).not.toBeNull();
  411 |         expect(finalRecord.employee_bank_account_cipher).not.toBe(baseline.bank_account_cipher);
  412 |         expect(finalRecord.request_audit_count).toBeGreaterThanOrEqual(4);
  413 |         expect(finalRecord.employee_bank_audit_count).toBeGreaterThanOrEqual(1);
  414 |         expect(finalRecord.notification_count).toBe(0);
  415 | 
  416 |         const audit = await requestAudit(employeeUser.email, journey.startedAt);
  417 |         const created = audit.find((entry) => entry.action === 'created');
  418 |         const hrApproval = audit.find((entry) => entry.status === 'pending_finance');
  419 |         const financeApproval = audit.find((entry) => entry.status === 'approved');
  420 |         expect(created).toBeDefined();
  421 |         expect(hrApproval).toBeDefined();
  422 |         expect(financeApproval).toBeDefined();
  423 |         assertAuditActor({ actor_email: created?.actor_email ?? undefined }, employeeUser.email);
  424 |         assertAuditActor({ actor_email: hrApproval?.actor_email ?? undefined }, hrUser.email);
  425 |         assertAuditActor({ actor_email: financeApproval?.actor_email ?? undefined }, financeUser.email);
  426 |         const employeeAudit = await employeeBankAudit(employeeUser.email, journey.startedAt, bankName);
  427 |         expect(employeeAudit.length).toBeGreaterThanOrEqual(1);
  428 |         assertAuditActor({ actor_email: employeeAudit.at(-1)?.actor_email ?? undefined }, financeUser.email);
  429 |       },
  430 |       assertInvariants: async () => {
  431 |         await expect(employee.page.getByText('Approved', { exact: true })).toBeVisible();
  432 |         await expect(employee.page.getByText(accountNumber.slice(-4), { exact: true })).toBeVisible();
  433 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  434 |         await expect(hr.page.getByRole('button', { name: /View all Profile Change Requests signals/ })).toHaveCount(0);
  435 | 
  436 |         const mailAfter = await mailpitForActors([employeeUser.email, hrUser.email, financeUser.email]);
  437 |         const beforeIds = new Set(mailBefore.map((message) => message.ID));
  438 |         const newEmails = mailAfter.filter((message) => !beforeIds.has(message.ID));
  439 |         assertRecipientsExactly(
  440 |           newEmails.flatMap((message) => message.To.map((recipient) => recipient.Address)),
  441 |           [],
  442 |         );
  443 |         employee.diagnostics.assertClean('employee sees approved bank change');
```