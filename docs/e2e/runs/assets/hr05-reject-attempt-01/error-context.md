# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-loans.process.spec.ts >> HR05-REJECT — Department Head rejects a cash advance and the remaining approval steps stop
- Location: e2e-process/hr-loans.process.spec.ts:466:1

# Error details

```
Error: expect(locator).toHaveAttribute(expected) failed

Locator: getByRole('dialog', { name: 'Reject loan request' }).getByLabel('Reason for rejection', { exact: true })
Expected: ""
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toHaveAttribute" getByRole('dialog', { name: 'Reject loan request' }).getByLabel('Reason for rejection', { exact: true }) with timeout 8000ms
  - waiting for getByRole('dialog', { name: 'Reject loan request' }).getByLabel('Reason for rejection', { exact: true })

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
    - link "Loans":
      - /url: /hr/loans
    - img
    - text: CA-202611-0001
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications":
    - img
  - 'status "Real-time updates: Live. Last updated 10:55 PM"': Live
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E department head":
    - img "E2E department head": ED
    - text: E2E department head
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
          - 'button "View all Approval Queue signals: 1 Approvals requiring your action"': "1"
        - listitem:
          - link "Notifications":
            - /url: /notifications
            - img
            - text: Notifications
    - button "Human Resources" [disabled] [expanded]
    - list:
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
    - button "Sales & CRM"
    - button "Procurement"
  - group "Module controls":
    - button "Expand all"
- main:
  - link "Loans":
    - /url: /hr/loans
    - img
    - text: Loans
  - heading "CA-202611-0001 Pending" [level=1]
  - text: E2E employee · Cash Advance
  - button "Approve":
    - img
    - text: Approve
  - button "Reject":
    - img
    - text: Reject
  - heading "Loan lifecycle" [level=3]
  - region "Document progress": Submitted Nov 4, 2026 Approved Disbursed Repaying Settled
  - heading "Loan summary" [level=3]
  - text: Principal ₱1,000.00 Total paid ₱0.00 Balance ₱1,000.00 Repayment progress 0.0%
  - term: Repayment months
  - definition: 2/2
  - term: Monthly amortization
  - definition: ₱500.00
  - term: Start date
  - definition: —
  - term: End date
  - definition: —
  - term: Interest rate
  - definition: 0.0%
  - term: Approval chain
  - definition: 3 steps
  - text: Purpose
  - paragraph: E2E-89ffb9a1-921 cash advance rejection verification
  - heading "Payments (0)" [level=3]
  - paragraph: No payments yet.
  - heading "Approval chain" [level=3]
  - list "Approval timeline":
    - listitem: Step 1 — Department head Pending
    - listitem: Step 2 — Finance officer Pending
    - listitem: Step 3 — Vice president Pending
  - heading "Employee" [level=3]
  - text: E2E employee E2E-2FDC0177057D3A5
  - dialog "Reject loan request":
    - heading "Reject loan request" [level=2]
    - button "Close modal":
      - img
    - text: Reason for rejection
    - textbox "Reason for rejection"
    - button "Cancel"
    - button "Confirm reject" [disabled]
```

# Test source

```ts
  482 |     { name: 'Employee submits a cash advance request', actor: 'employee', uiPath: 'Profile menu → Loans → Apply for a loan → Cash Advance', expectedStatus: 'pending; Department Head is the current approval step; no journal entry' },
  483 |     { name: 'Department Head finds and rejects the request', actor: 'department_head', uiPath: 'Sidebar → Approval Queue → My action required → Cash Advance loan → Reject with reason', expectedStatus: 'rejected; later approval steps skipped; Employee receives outcome notices; no journal entry' },
  484 |   ];
  485 |   const emailsBefore = await listMailpitMessages();
  486 |   const mailIdsBefore = new Set(emailsBefore.map((message) => message.ID));
  487 |   const employee = await actorPage(browser, employeeUser.actor);
  488 |   const departmentHead = await actorPage(browser, departmentHeadUser.actor);
  489 |   const observer = await actorPage(browser, observerUser.actor);
  490 | 
  491 |   try {
  492 |     await openEmployeeLoans(employee);
  493 |     await openLoansObserver(observer);
  494 | 
  495 |     await runProcessStep({
  496 |       ...plannedSteps[0],
  497 |       page: employee.page,
  498 |       plannedSteps,
  499 |       context: journey,
  500 |       testInfo,
  501 |       action: async () => {
  502 |         const applyButtons = employee.page.getByRole('button', { name: 'Apply for a loan', exact: true });
  503 |         await expect(applyButtons).toHaveCount(2);
  504 |         await applyButtons.first().click();
  505 |         const dialog = employee.page.getByRole('dialog', { name: 'Apply for a Loan' });
  506 |         await expect(dialog).toBeVisible();
  507 |         await dialog.getByRole('combobox', { name: /^Type/ }).selectOption('cash_advance');
  508 |         await dialog.getByLabel(/^Amount/).fill(amount);
  509 |         await dialog.getByLabel(/^Periods \(months\)/).fill(String(periods));
  510 |         await dialog.getByLabel('Reason (optional)', { exact: true }).fill(purpose);
  511 |         await expect(dialog.getByText('Estimated monthly deduction', { exact: true })).toBeVisible();
  512 |         await expect.poll(async () => {
  513 |           const content = await dialog.locator('span.font-mono.tabular-nums.font-medium.text-primary').last().textContent();
  514 |           return content?.trim() ?? '';
  515 |         }, { timeout: 15_000, message: 'amortization preview should render a calculated amount' }).not.toBe('…');
  516 |         const responsePromise = employee.page.waitForResponse((response) =>
  517 |           response.url().includes('/api/v1/hr/self-service/loans') && response.request().method() === 'POST',
  518 |         );
  519 |         await dialog.getByRole('button', { name: 'Submit request', exact: true }).click();
  520 |         const response = await responsePromise;
  521 |         expect(response.status(), 'Employee submits the cash advance in self-service').toBe(201);
  522 |         const responseBody = await response.json() as { data?: { id?: unknown } };
  523 |         const loanHashId = responseBody.data?.id;
  524 |         if (typeof loanHashId !== 'string') throw new Error('The successful self-service response did not include the public loan ID.');
  525 |         journey.values.loanHashId = loanHashId;
  526 |         await expect(dialog).toBeHidden();
  527 |       },
  528 |       verify: async () => {
  529 |         const loan = await waitForLoan(purpose, 'pending');
  530 |         expect(loan.loan_type).toBe('cash_advance');
  531 |         expect(loan.principal).toBe(amount);
  532 |         expect(loan.pay_periods_total).toBe(periods);
  533 |         expect(loan.disbursement_journal_entry_id).toBeNull();
  534 |         expect(loan.requester_email).toBe(employeeUser.email);
  535 |         const approvals = await approvalRows(loan.loan_id);
  536 |         expect(approvals.map(({ step_order, role_slug, action, approver_email }) => [step_order, role_slug, action, approver_email])).toEqual([
  537 |           [1, 'department_head', 'pending', null],
  538 |           [2, 'finance_officer', 'pending', null],
  539 |           [3, 'vice_president', 'pending', null],
  540 |         ]);
  541 |         journey.values.loanNo = loan.loan_no;
  542 |         journey.values.loanPurpose = purpose;
  543 |         journey.values.principal = loan.principal;
  544 |         const observerRow = observer.page.getByRole('row').filter({ hasText: loan.loan_no });
  545 |         await expect(observerRow, 'HR loan list should receive the new record without reload').toHaveCount(1);
  546 |         await observerRow.click();
  547 |         const hashId = String(journey.values.loanHashId);
  548 |         await expect(observer.page).toHaveURL(new RegExp(`/hr/loans/${hashId}(?:\\?|$)`));
  549 |         await expect(observer.page.getByRole('heading', { name: new RegExp(loan.loan_no) })).toContainText('Pending');
  550 |         const employeeRow = employee.page.getByRole('region', { name: 'Active loans' }).getByRole('row').filter({ has: employee.page.getByText('Cash Advance', { exact: true }) });
  551 |         await expect(employeeRow, 'Employee self-service should show the pending Cash Advance').toHaveCount(1);
  552 |       },
  553 |       assertInvariants: async () => {
  554 |         const loan = (await loanSnapshot(purpose))[0];
  555 |         expect(loan).toBeTruthy();
  556 |         expect(await journalRows(loan.loan_id)).toHaveLength(0);
  557 |         const hashId = String(journey.values.loanHashId);
  558 |         await expect.poll(async () =>
  559 |           (await noticesForLoan(hashId)).filter((notice) => notice.type === 'loans.submitted').length,
  560 |         { timeout: 15_000, message: 'queued loan-submission FYI should reach configured Finance recipients' }).toBeGreaterThanOrEqual(financeRoleRecipients.length);
  561 |         const notices = await noticesForLoan(hashId);
  562 |         assertRecipientsExactly(notices.filter((notice) => notice.type === 'loans.submitted').map((notice) => notice.recipient_email), financeRoleRecipients);
  563 |         assertRecipientsExactly(notices.filter((notice) => notice.type === 'approval.action_required').map((notice) => notice.recipient_email), []);
  564 |       },
  565 |     });
  566 | 
  567 |     await runProcessStep({
  568 |       ...plannedSteps[1],
  569 |       page: departmentHead.page,
  570 |       plannedSteps,
  571 |       context: journey,
  572 |       testInfo,
  573 |       action: async () => {
  574 |         const before = await waitForLoan(purpose, 'pending');
  575 |         await openApprovalQueue(departmentHead, before, String(journey.values.loanHashId));
  576 |         const rejectButton = departmentHead.page.getByRole('button', { name: 'Reject', exact: true });
  577 |         await expect(rejectButton).toBeEnabled();
  578 |         await rejectButton.click();
  579 |         const dialog = departmentHead.page.getByRole('dialog', { name: 'Reject loan request' });
  580 |         await expect(dialog).toBeVisible();
  581 |         const reasonField = dialog.getByLabel('Reason for rejection', { exact: true });
> 582 |         await expect(reasonField).toHaveAttribute('required', '');
      |                                   ^ Error: expect(locator).toHaveAttribute(expected) failed
  583 |         const confirmButton = dialog.getByRole('button', { name: 'Confirm reject', exact: true });
  584 |         await expect(confirmButton, 'a rejection reason is required before the decision can be submitted').toBeDisabled();
  585 |         await reasonField.fill(reason);
  586 |         await expect(confirmButton).toBeEnabled();
  587 |         const responsePromise = departmentHead.page.waitForResponse((response) =>
  588 |           response.request().method() === 'PATCH'
  589 |             && response.url().includes(`/api/v1/loans/${String(journey.values.loanHashId)}/reject`),
  590 |         );
  591 |         await confirmButton.click();
  592 |         const response = await responsePromise;
  593 |         expect(response.status(), 'Department Head submits the rejection through the loan UI').toBe(200);
  594 |         await expect(dialog).toBeHidden();
  595 |       },
  596 |       verify: async () => {
  597 |         const loan = await waitForLoan(purpose, 'rejected');
  598 |         expect(loan.disbursement_journal_entry_id).toBeNull();
  599 |         expect(loan.payment_count).toBe(0);
  600 |         const approvals = await approvalRows(loan.loan_id);
  601 |         expect(approvals).toHaveLength(3);
  602 |         expect(approvals.map(({ step_order, role_slug, action, approver_email, remarks }) => [step_order, role_slug, action, approver_email, remarks])).toEqual([
  603 |           [1, 'department_head', 'rejected', departmentHeadUser.email, reason],
  604 |           [2, 'finance_officer', 'skipped', null, null],
  605 |           [3, 'vice_president', 'skipped', null, null],
  606 |         ]);
  607 |         const hrStatus = observer.page.getByRole('heading', { name: new RegExp(loan.loan_no) }).getByText('Rejected', { exact: true });
  608 |         await assertSecondViewerRealtime(hrStatus, 'Rejected');
  609 |         const history = employee.page.getByRole('region', { name: 'Loan history' });
  610 |         const historyRow = history.getByRole('row').filter({ has: employee.page.getByText('Cash Advance', { exact: true }) });
  611 |         await expect(historyRow, 'Employee self-service should move the rejected request into history without reload').toHaveCount(1);
  612 |         await assertSecondViewerRealtime(historyRow.getByText('Rejected', { exact: true }), 'Rejected');
  613 |         await expect(employee.page.getByRole('button', { name: /^Withdraw Cash Advance$/ })).toHaveCount(0);
  614 |       },
  615 |       assertInvariants: async () => {
  616 |         const loan = (await loanSnapshot(purpose))[0];
  617 |         const approvals = await approvalRows(loan.loan_id);
  618 |         const audits = await auditRows(loan.loan_id);
  619 |         expect(approvals[0].action).toBe('rejected');
  620 |         expect(approvals[0].approver_email).toBe(departmentHeadUser.email);
  621 |         const actorAudit = audits.find((entry) => entry.actor_email === departmentHeadUser.email && entry.action === 'updated');
  622 |         expect(actorAudit, 'the rejecting role should be the loan audit actor').toBeTruthy();
  623 |         assertAuditActor({ actor_email: actorAudit!.actor_email ?? undefined }, departmentHeadUser.email);
  624 |         const hashId = String(journey.values.loanHashId);
  625 |         for (const type of ['approval.rejected', 'loans.rejected']) {
  626 |           await expect.poll(async () => (await noticesForLoan(hashId)).filter((notice) => notice.type === type).length,
  627 |             { timeout: 15_000, message: `${type} outcome notification should reach the Employee` }).toBeGreaterThanOrEqual(1);
  628 |         }
  629 |         const notices = await noticesForLoan(hashId);
  630 |         for (const type of ['approval.rejected', 'loans.rejected']) {
  631 |           const outcome = notices.filter((notice) => notice.type === type);
  632 |           assertRecipientsExactly(outcome.map((notice) => notice.recipient_email), [employeeUser.email]);
  633 |         }
  634 |         expect(notices.filter((notice) => notice.type === 'approval.action_required')).toHaveLength(0);
  635 |         expect(notices.filter((notice) => ['approval.completed', 'loans.approved'].includes(notice.type))).toHaveLength(0);
  636 |         expect(await journalRows(loan.loan_id)).toHaveLength(0);
  637 |         const emailsAfter = await listMailpitMessages();
  638 |         const newParticipantEmails = emailsAfter.filter((message) =>
  639 |           !mailIdsBefore.has(message.ID)
  640 |           && message.To.some((recipient) => participants.includes(recipient.Address.toLowerCase())),
  641 |         );
  642 |         expect(newParticipantEmails, 'rejection and approval hand-offs use in-app notices, with no participant email').toHaveLength(0);
  643 |       },
  644 |     });
  645 | 
  646 |     await Promise.all([employee.diagnostics.assertClean(), departmentHead.diagnostics.assertClean(), observer.diagnostics.assertClean()]);
  647 |   } finally {
  648 |     await Promise.all([employee.context.close(), departmentHead.context.close(), observer.context.close()]);
  649 |   }
  650 | });
  651 | 
```