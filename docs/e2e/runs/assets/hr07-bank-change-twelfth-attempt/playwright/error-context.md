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

Locator: getByText('7426', { exact: true })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByText('7426', { exact: true }) with timeout 8000ms
  - waiting for getByText('7426', { exact: true })

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
    - link "Self-service":
      - /url: /self-service
    - img
    - text: Profile
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications":
    - img
  - 'status "Real-time updates: Live. Last updated 7:19 AM"': Live
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
  - link "Self-service":
    - /url: /self-service
    - img
    - text: Self-service
  - heading "E2E employee" [level=1]
  - text: E2E-2FDC0177057D3A5 · Plant Manager · Production
  - button "Change password":
    - img
    - text: Change password
  - button "Sign out":
    - img
    - text: Sign out
  - heading "Contact" [level=3]
  - button "Edit":
    - img
    - text: Edit
  - term: Mobile
  - definition: —
  - term: Email
  - definition: e2e+employee@ogami-e2e.test
  - heading "Emergency contact" [level=3]
  - button "Edit":
    - img
    - text: Edit
  - term: Name
  - definition: —
  - term: Relationship
  - definition: —
  - term: Phone
  - definition: —
  - heading "Address" [level=3]
  - button "Edit":
    - img
    - text: Edit
  - term: Street
  - definition: —
  - term: Barangay
  - definition: —
  - term: City
  - definition: —
  - term: Province
  - definition: —
  - term: ZIP code
  - definition: —
  - heading "Bank account" [level=3]
  - text: HR + Finance approval
  - button "Request update"
  - term: Bank
  - definition: E2E BANK 1F8309F0
  - term: Account
  - definition: ••••7426
  - heading "Government IDs" [level=3]
  - text: managed by HR
  - term: SSS
  - definition: —
  - term: PhilHealth
  - definition: —
  - term: Pag-IBIG
  - definition: —
  - term: TIN
  - definition: —
  - heading "At a glance" [level=3]
  - img "E2E employee": EE
  - text: E2E employee E2E-2FDC0177057D3A5 Profile 47% complete. Missing details can be submitted for HR review from the panels below.
  - term: Position
  - definition: Plant Manager
  - term: Department
  - definition: Production
  - term: Employment type
  - definition: Regular
  - term: Pay type
  - definition: Monthly
  - term: Employment status
  - definition: Active
  - term: Birth date
  - definition: Nov 4, 1996
  - term: Nationality
  - definition: Filipino
  - term: Gender
  - definition: Male
  - term: Civil status
  - definition: Single
  - term: Date hired
  - definition: Nov 4, 2024
  - term: Date regularized
  - definition: Nov 4, 2025
  - term: Sign-in email
  - definition: e2e+employee@ogami-e2e.test
  - heading "Update requests" [level=3]
  - text: 0 pending · 1 total
  - list:
    - listitem: Bank, Bank account no. Nov 4, 2026 Approved
  - heading "Preferences" [level=3]
  - text: Theme
  - radiogroup "Theme":
    - radio "Light"
    - radio "Dark"
    - radio "System" [checked]
  - link "Notification preferences →":
    - /url: /self-service/notification-preferences
```

# Test source

```ts
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
> 395 |         await expect(employee.page.getByText(accountNumber.slice(-4), { exact: true })).toBeVisible();
      |                                                                                         ^ Error: expect(locator).toBeVisible() failed
  396 |         await expect(employee.page.getByText(accountNumber, { exact: true })).toHaveCount(0);
  397 |         await expect(finance.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(0);
  398 |         await expect(hr.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(0);
  399 |       },
  400 |       verify: async () => {
  401 |         const record = await expectRequestStatus(employeeUser.email, journey.startedAt, 'approved');
  402 |         const verifiedRecord = (await requestSnapshot(employeeUser.email, journey.startedAt, bankName))[0];
  403 |         expect(verifiedRecord, 'read-only verification should find the approved profile change').toBeDefined();
  404 |         const finalRecord = verifiedRecord!;
  405 |         expect(finalRecord.request_id).toBe(record.request_id);
  406 |         expect(finalRecord.requires_finance).toBe(true);
  407 |         expect(finalRecord.requester_email).toBe(employeeUser.email);
  408 |         expect(finalRecord.hr_reviewer_email).toBe(hrUser.email);
  409 |         expect(finalRecord.finance_reviewer_email).toBe(financeUser.email);
  410 |         expect(finalRecord.employee_bank_name).toBe(bankName);
  411 |         expect(finalRecord.employee_bank_account_cipher).not.toBeNull();
  412 |         expect(finalRecord.employee_bank_account_cipher).not.toBe(baseline.bank_account_cipher);
  413 |         expect(finalRecord.request_audit_count).toBeGreaterThanOrEqual(4);
  414 |         expect(finalRecord.employee_bank_audit_count).toBeGreaterThanOrEqual(1);
  415 |         expect(finalRecord.notification_count).toBe(0);
  416 | 
  417 |         const audit = await requestAudit(employeeUser.email, journey.startedAt);
  418 |         const created = audit.find((entry) => entry.action === 'created');
  419 |         const hrApproval = audit.find((entry) => entry.status === 'pending_finance');
  420 |         const financeApproval = audit.find((entry) => entry.status === 'approved');
  421 |         expect(created).toBeDefined();
  422 |         expect(hrApproval).toBeDefined();
  423 |         expect(financeApproval).toBeDefined();
  424 |         assertAuditActor({ actor_email: created?.actor_email ?? undefined }, employeeUser.email);
  425 |         assertAuditActor({ actor_email: hrApproval?.actor_email ?? undefined }, hrUser.email);
  426 |         assertAuditActor({ actor_email: financeApproval?.actor_email ?? undefined }, financeUser.email);
  427 |         const employeeAudit = await employeeBankAudit(employeeUser.email, journey.startedAt, bankName);
  428 |         expect(employeeAudit.length).toBeGreaterThanOrEqual(1);
  429 |         assertAuditActor({ actor_email: employeeAudit.at(-1)?.actor_email ?? undefined }, financeUser.email);
  430 |       },
  431 |       assertInvariants: async () => {
  432 |         await expect(employee.page.getByText('Approved', { exact: true })).toBeVisible();
  433 |         await expect(employee.page.getByText(accountNumber.slice(-4), { exact: true })).toBeVisible();
  434 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  435 |         await expect(hr.page.getByRole('button', { name: /View all Profile Change Requests signals/ })).toHaveCount(0);
  436 | 
  437 |         const mailAfter = await mailpitForActors([employeeUser.email, hrUser.email, financeUser.email]);
  438 |         const beforeIds = new Set(mailBefore.map((message) => message.ID));
  439 |         const newEmails = mailAfter.filter((message) => !beforeIds.has(message.ID));
  440 |         assertRecipientsExactly(
  441 |           newEmails.flatMap((message) => message.To.map((recipient) => recipient.Address)),
  442 |           [],
  443 |         );
  444 |         employee.diagnostics.assertClean('employee sees approved bank change');
  445 |         hr.diagnostics.assertClean('HR queue closes after Finance approval');
  446 |         finance.diagnostics.assertClean('Finance completes bank change');
  447 |       },
  448 |     });
  449 |     await captureAnnotatedStep(employee.page, 'employee', 'Employee profile reflects approved bank change', 4, testInfo);
  450 |   } finally {
  451 |     await Promise.all([employee.context.close(), hr.context.close(), finance.context.close()]);
  452 |   }
  453 | });
  454 | 
```