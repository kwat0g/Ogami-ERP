# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-profile.process.spec.ts >> HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval
- Location: e2e-process/hr-profile.process.spec.ts:193:1

# Error details

```
Error: expect(received).toBeGreaterThanOrEqual(expected)

Expected: >= 1
Received:    0
```

# Test source

```ts
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
  343 |         const hrRow = hr.page.getByRole('row').filter({ hasText: employeeName });
  344 |         await expect(hrRow.getByText('Awaiting Finance', { exact: true })).toBeVisible();
  345 |         const employeeRequest = employee.page.getByRole('listitem').filter({ hasText: 'Bank account no.' });
  346 |         await expect(employeeRequest.getByText('Awaiting Finance', { exact: true })).toBeVisible();
  347 |         await expect(hrRow.getByRole('button', { name: 'HR Approve', exact: true })).toHaveCount(0);
  348 |         await expect(finance.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(1);
  349 |         await clickBadgeHandoff(finance, 'Bank Account Changes', 1);
  350 |         await expandEmployeeRequest(finance, employeeName);
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
  363 |         const employeeRequest = employee.page.getByRole('listitem').filter({ hasText: 'Bank account no.' });
  364 |         await expect(employeeRequest.getByText('Awaiting Finance', { exact: true })).toBeVisible();
  365 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals: 1 Profile changes you can review/ })).toBeVisible();
  366 |         await expect(hr.page.getByRole('button', { name: /View all Profile Change Requests signals/ })).toHaveCount(0);
  367 |         employee.diagnostics.assertClean('employee observes HR approval');
  368 |         hr.diagnostics.assertClean('HR approves first leg');
  369 |         finance.diagnostics.assertClean('Finance receives second leg');
  370 |       },
  371 |     });
  372 |     await captureAnnotatedStep(employee.page, 'employee', 'Employee sees Finance review pending', 3, testInfo);
  373 | 
  374 |     await runProcessStep({
  375 |       name: plannedSteps[3].name,
  376 |       actor: plannedSteps[3].actor,
  377 |       uiPath: plannedSteps[3].uiPath,
  378 |       expectedStatus: plannedSteps[3].expectedStatus,
  379 |       page: finance.page,
  380 |       plannedSteps,
  381 |       context: journey,
  382 |       testInfo,
  383 |       action: async () => {
  384 |         const responsePromise = finance.page.waitForResponse((response) =>
  385 |           response.url().includes(`/hr/profile-update-requests/${requestHashId}/finance-review`) && response.request().method() === 'PATCH',
  386 |         );
  387 |         await finance.page.getByRole('button', { name: 'Finance Approve', exact: true }).click();
  388 |         const confirm = finance.page.getByRole('dialog');
  389 |         await expect(confirm.getByText(/applies the new bank account/i)).toBeVisible();
  390 |         await confirm.getByRole('button', { name: 'Approve', exact: true }).click();
  391 |         const response = await responsePromise;
  392 |         expect(response.status(), 'Finance UI approval should be accepted').toBe(200);
  393 |         await expect(employee.page.getByText('Approved', { exact: true })).toBeVisible();
  394 |         await expect(employee.page.getByText(bankName, { exact: true })).toBeVisible();
  395 |         await expect(employee.page.getByRole('definition').filter({ hasText: `••••${accountNumber.slice(-4)}` })).toBeVisible();
  396 |         await expect(employee.page.getByText(accountNumber, { exact: true })).toHaveCount(0);
  397 |         const financeRow = finance.page.getByRole('row').filter({ hasText: employeeName });
  398 |         await expect(financeRow.getByText('Approved', { exact: true })).toBeVisible();
  399 |         await expect(financeRow.getByRole('button', { name: 'Finance Approve', exact: true })).toHaveCount(0);
  400 |         const hrRow = hr.page.getByRole('row').filter({ hasText: employeeName });
  401 |         await expect(hrRow.getByText('Approved', { exact: true })).toBeVisible();
  402 |         await expect(hrRow.getByRole('button', { name: 'HR Approve', exact: true })).toHaveCount(0);
  403 |       },
  404 |       verify: async () => {
  405 |         const record = await expectRequestStatus(employeeUser.email, journey.startedAt, 'approved');
  406 |         const verifiedRecord = (await requestSnapshot(employeeUser.email, journey.startedAt, bankName))[0];
  407 |         expect(verifiedRecord, 'read-only verification should find the approved profile change').toBeDefined();
  408 |         const finalRecord = verifiedRecord!;
  409 |         expect(finalRecord.request_id).toBe(record.request_id);
  410 |         expect(finalRecord.requires_finance).toBe(true);
  411 |         expect(finalRecord.requester_email).toBe(employeeUser.email);
  412 |         expect(finalRecord.hr_reviewer_email).toBe(hrUser.email);
  413 |         expect(finalRecord.finance_reviewer_email).toBe(financeUser.email);
  414 |         expect(finalRecord.employee_bank_name).toBe(bankName);
  415 |         expect(finalRecord.employee_bank_account_cipher).not.toBeNull();
  416 |         expect(finalRecord.employee_bank_account_cipher).not.toBe(baseline.bank_account_cipher);
  417 |         expect(finalRecord.request_audit_count).toBeGreaterThanOrEqual(4);
> 418 |         expect(finalRecord.employee_bank_audit_count).toBeGreaterThanOrEqual(1);
      |                                                       ^ Error: expect(received).toBeGreaterThanOrEqual(expected)
  419 |         expect(finalRecord.notification_count).toBe(0);
  420 | 
  421 |         const audit = await requestAudit(employeeUser.email, journey.startedAt);
  422 |         const created = audit.find((entry) => entry.action === 'created');
  423 |         const hrApproval = audit.find((entry) => entry.status === 'pending_finance');
  424 |         const financeApproval = audit.find((entry) => entry.status === 'approved');
  425 |         expect(created).toBeDefined();
  426 |         expect(hrApproval).toBeDefined();
  427 |         expect(financeApproval).toBeDefined();
  428 |         assertAuditActor({ actor_email: created?.actor_email ?? undefined }, employeeUser.email);
  429 |         assertAuditActor({ actor_email: hrApproval?.actor_email ?? undefined }, hrUser.email);
  430 |         assertAuditActor({ actor_email: financeApproval?.actor_email ?? undefined }, financeUser.email);
  431 |         const employeeAudit = await employeeBankAudit(employeeUser.email, journey.startedAt, bankName);
  432 |         expect(employeeAudit.length).toBeGreaterThanOrEqual(1);
  433 |         assertAuditActor({ actor_email: employeeAudit.at(-1)?.actor_email ?? undefined }, financeUser.email);
  434 |       },
  435 |       assertInvariants: async () => {
  436 |         await expect(employee.page.getByText('Approved', { exact: true })).toBeVisible();
  437 |         await expect(employee.page.getByRole('definition').filter({ hasText: `••••${accountNumber.slice(-4)}` })).toBeVisible();
  438 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  439 |         await expect(hr.page.getByRole('button', { name: /View all Profile Change Requests signals/ })).toHaveCount(0);
  440 | 
  441 |         const mailAfter = await mailpitForActors([employeeUser.email, hrUser.email, financeUser.email]);
  442 |         const beforeIds = new Set(mailBefore.map((message) => message.ID));
  443 |         const newEmails = mailAfter.filter((message) => !beforeIds.has(message.ID));
  444 |         assertRecipientsExactly(
  445 |           newEmails.flatMap((message) => message.To.map((recipient) => recipient.Address)),
  446 |           [],
  447 |         );
  448 |         employee.diagnostics.assertClean('employee sees approved bank change');
  449 |         hr.diagnostics.assertClean('HR queue closes after Finance approval');
  450 |         finance.diagnostics.assertClean('Finance completes bank change');
  451 |       },
  452 |     });
  453 |     await captureAnnotatedStep(employee.page, 'employee', 'Employee profile reflects approved bank change', 4, testInfo);
  454 |   } finally {
  455 |     await Promise.all([employee.context.close(), hr.context.close(), finance.context.close()]);
  456 |   }
  457 | });
  458 | 
```