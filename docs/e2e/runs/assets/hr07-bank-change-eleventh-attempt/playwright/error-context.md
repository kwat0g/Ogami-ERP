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

Locator: getByText('HR approved by E2E hr officer', { exact: true })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByText('HR approved by E2E hr officer', { exact: true }) with timeout 8000ms
  - waiting for getByText('HR approved by E2E hr officer', { exact: true })

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
  - button "Notifications, 23 unread":
    - img
  - 'status "Real-time updates: Live. Last updated 7:14 AM"': Live
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E finance officer":
    - img "E2E finance officer": EF
    - text: E2E finance officer
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
          - 'button "View all Notifications signals: 23 Unread notifications"': "23"
    - button "Finance"
    - button "Procurement"
    - 'button "Payroll: Profile changes you can review: 1" [disabled] [expanded]': Payroll
    - list:
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
        - link "Bank Account Changes":
          - /url: /hr/profile-update-requests
          - img
          - text: Bank Account Changes
        - 'button "View all Bank Account Changes signals: 1 Profile changes you can review"': "1"
      - listitem:
        - link "Payroll Processing":
          - /url: /payroll/periods
          - img
          - text: Payroll Processing
    - button "Assets"
  - group "Module controls":
    - button "Expand all"
- main:
  - heading "Bank Account Changes" [level=1]
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
      - row "Collapse row E2E employee E2E employee E2E-2FDC0177057D3A5 Banking 2 E2E employee Nov 4, 2026 · 3:14 PM Awaiting Finance":
        - cell "Collapse row":
          - button "Collapse row" [expanded]:
            - img
        - cell "E2E employee E2E employee E2E-2FDC0177057D3A5":
          - img "E2E employee": EE
          - text: E2E employee E2E-2FDC0177057D3A5
        - cell "Banking"
        - cell "2"
        - cell "E2E employee Nov 4, 2026 · 3:14 PM"
        - cell "Awaiting Finance"
      - row "Requested changes Review context":
        - cell "Requested changes Review context":
          - region "Requested changes":
            - heading "Requested changes" [level=2]
            - term: Bank
            - definition: E2E BANK 7A552498
            - term: Bank account number
            - definition: E2E7A552498387426
          - region "Review context":
            - paragraph: Employee noteBank account change
            - button "Reject"
            - button "Finance Approve"
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
  343 |         const hrRow = hr.page.getByRole('row').filter({ hasText: employeeName });
  344 |         await expect(hrRow.getByText('Awaiting Finance', { exact: true })).toBeVisible();
  345 |         const employeeRequest = employee.page.getByRole('listitem').filter({ hasText: 'Bank account no.' });
  346 |         await expect(employeeRequest.getByText('Awaiting Finance', { exact: true })).toBeVisible();
  347 |         await expect(hrRow.getByRole('button', { name: 'HR Approve', exact: true })).toHaveCount(0);
  348 |         await expect(finance.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(1);
  349 |         await clickBadgeHandoff(finance, 'Bank Account Changes', 1);
  350 |         await expandEmployeeRequest(finance, employeeName);
> 351 |         await expect(finance.page.getByText(`HR approved by E2E hr officer`, { exact: true })).toBeVisible();
      |                                                                                                ^ Error: expect(locator).toBeVisible() failed
  352 |         await expect(finance.page.getByRole('button', { name: 'Finance Approve', exact: true })).toBeVisible();
  353 |         await expect(finance.page.getByRole('button', { name: 'HR Approve', exact: true })).toHaveCount(0);
  354 |       },
  355 |       verify: async () => {
  356 |         const record = await expectRequestStatus(employeeUser.email, journey.startedAt, 'pending_finance');
  357 |         expect(record.requires_finance).toBe(true);
  358 |         expect(record.hr_reviewer_email).toBe(hrUser.email);
  359 |         expect(record.finance_reviewer_email).toBeNull();
  360 |         expect(record.employee_bank_name).toBe(baseline.bank_name);
  361 |         expect(record.employee_bank_account_cipher).toBe(baseline.bank_account_cipher);
  362 |       },
  363 |       assertInvariants: async () => {
  364 |         const employeeRequest = employee.page.getByRole('listitem').filter({ hasText: 'Bank account no.' });
  365 |         await expect(employeeRequest.getByText('Awaiting Finance', { exact: true })).toBeVisible();
  366 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals: 1 Profile changes you can review/ })).toBeVisible();
  367 |         await expect(hr.page.getByRole('button', { name: /View all Profile Change Requests signals/ })).toHaveCount(0);
  368 |         employee.diagnostics.assertClean('employee observes HR approval');
  369 |         hr.diagnostics.assertClean('HR approves first leg');
  370 |         finance.diagnostics.assertClean('Finance receives second leg');
  371 |       },
  372 |     });
  373 |     await captureAnnotatedStep(employee.page, 'employee', 'Employee sees Finance review pending', 3, testInfo);
  374 | 
  375 |     await runProcessStep({
  376 |       name: plannedSteps[3].name,
  377 |       actor: plannedSteps[3].actor,
  378 |       uiPath: plannedSteps[3].uiPath,
  379 |       expectedStatus: plannedSteps[3].expectedStatus,
  380 |       page: finance.page,
  381 |       plannedSteps,
  382 |       context: journey,
  383 |       testInfo,
  384 |       action: async () => {
  385 |         const responsePromise = finance.page.waitForResponse((response) =>
  386 |           response.url().includes(`/hr/profile-update-requests/${requestHashId}/finance-review`) && response.request().method() === 'PATCH',
  387 |         );
  388 |         await finance.page.getByRole('button', { name: 'Finance Approve', exact: true }).click();
  389 |         const confirm = finance.page.getByRole('dialog');
  390 |         await expect(confirm.getByText(/applies the new bank account/i)).toBeVisible();
  391 |         await confirm.getByRole('button', { name: 'Approve', exact: true }).click();
  392 |         const response = await responsePromise;
  393 |         expect(response.status(), 'Finance UI approval should be accepted').toBe(200);
  394 |         await expect(employee.page.getByText('Approved', { exact: true })).toBeVisible();
  395 |         await expect(employee.page.getByText(bankName, { exact: true })).toBeVisible();
  396 |         await expect(employee.page.getByText(accountNumber.slice(-4), { exact: true })).toBeVisible();
  397 |         await expect(employee.page.getByText(accountNumber, { exact: true })).toHaveCount(0);
  398 |         await expect(finance.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(0);
  399 |         await expect(hr.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(0);
  400 |       },
  401 |       verify: async () => {
  402 |         const record = await expectRequestStatus(employeeUser.email, journey.startedAt, 'approved');
  403 |         const verifiedRecord = (await requestSnapshot(employeeUser.email, journey.startedAt, bankName))[0];
  404 |         expect(verifiedRecord, 'read-only verification should find the approved profile change').toBeDefined();
  405 |         const finalRecord = verifiedRecord!;
  406 |         expect(finalRecord.request_id).toBe(record.request_id);
  407 |         expect(finalRecord.requires_finance).toBe(true);
  408 |         expect(finalRecord.requester_email).toBe(employeeUser.email);
  409 |         expect(finalRecord.hr_reviewer_email).toBe(hrUser.email);
  410 |         expect(finalRecord.finance_reviewer_email).toBe(financeUser.email);
  411 |         expect(finalRecord.employee_bank_name).toBe(bankName);
  412 |         expect(finalRecord.employee_bank_account_cipher).not.toBeNull();
  413 |         expect(finalRecord.employee_bank_account_cipher).not.toBe(baseline.bank_account_cipher);
  414 |         expect(finalRecord.request_audit_count).toBeGreaterThanOrEqual(4);
  415 |         expect(finalRecord.employee_bank_audit_count).toBeGreaterThanOrEqual(1);
  416 |         expect(finalRecord.notification_count).toBe(0);
  417 | 
  418 |         const audit = await requestAudit(employeeUser.email, journey.startedAt);
  419 |         const created = audit.find((entry) => entry.action === 'created');
  420 |         const hrApproval = audit.find((entry) => entry.status === 'pending_finance');
  421 |         const financeApproval = audit.find((entry) => entry.status === 'approved');
  422 |         expect(created).toBeDefined();
  423 |         expect(hrApproval).toBeDefined();
  424 |         expect(financeApproval).toBeDefined();
  425 |         assertAuditActor({ actor_email: created?.actor_email ?? undefined }, employeeUser.email);
  426 |         assertAuditActor({ actor_email: hrApproval?.actor_email ?? undefined }, hrUser.email);
  427 |         assertAuditActor({ actor_email: financeApproval?.actor_email ?? undefined }, financeUser.email);
  428 |         const employeeAudit = await employeeBankAudit(employeeUser.email, journey.startedAt, bankName);
  429 |         expect(employeeAudit.length).toBeGreaterThanOrEqual(1);
  430 |         assertAuditActor({ actor_email: employeeAudit.at(-1)?.actor_email ?? undefined }, financeUser.email);
  431 |       },
  432 |       assertInvariants: async () => {
  433 |         await expect(employee.page.getByText('Approved', { exact: true })).toBeVisible();
  434 |         await expect(employee.page.getByText(accountNumber.slice(-4), { exact: true })).toBeVisible();
  435 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  436 |         await expect(hr.page.getByRole('button', { name: /View all Profile Change Requests signals/ })).toHaveCount(0);
  437 | 
  438 |         const mailAfter = await mailpitForActors([employeeUser.email, hrUser.email, financeUser.email]);
  439 |         const beforeIds = new Set(mailBefore.map((message) => message.ID));
  440 |         const newEmails = mailAfter.filter((message) => !beforeIds.has(message.ID));
  441 |         assertRecipientsExactly(
  442 |           newEmails.flatMap((message) => message.To.map((recipient) => recipient.Address)),
  443 |           [],
  444 |         );
  445 |         employee.diagnostics.assertClean('employee sees approved bank change');
  446 |         hr.diagnostics.assertClean('HR queue closes after Finance approval');
  447 |         finance.diagnostics.assertClean('Finance completes bank change');
  448 |       },
  449 |     });
  450 |     await captureAnnotatedStep(employee.page, 'employee', 'Employee profile reflects approved bank change', 4, testInfo);
  451 |   } finally {
```