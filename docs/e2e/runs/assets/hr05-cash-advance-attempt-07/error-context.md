# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-loans.process.spec.ts >> HR05-CASH-ADVANCE — Employee request advances through Department Head, Finance, and VP before disbursement
- Location: e2e-process/hr-loans.process.spec.ts:222:1

# Error details

```
Error: the acting role should be the loan audit actor for its approval

expect(received).toBeTruthy()

Received: undefined
```

# Test source

```ts
  297 |         const approvals = await approvalRows(loan.loan_id);
  298 |         expect(approvals.map(({ step_order, role_slug, action, approver_email }) => [step_order, role_slug, action, approver_email])).toEqual([
  299 |           [1, 'department_head', 'pending', null],
  300 |           [2, 'finance_officer', 'pending', null],
  301 |           [3, 'vice_president', 'pending', null],
  302 |         ]);
  303 |         journey.values.loanNo = loan.loan_no;
  304 |         journey.values.loanPurpose = purpose;
  305 |         journey.values.principal = loan.principal;
  306 |         journey.values.employeeId = loan.employee_id;
  307 |         const observerRow = observer.page.getByRole('row').filter({ hasText: loan.loan_no });
  308 |         await expect(observerRow, 'HR loan list should receive the new record without reload').toHaveCount(1);
  309 |         await observerRow.click();
  310 |         const hashId = String(journey.values.loanHashId);
  311 |         await expect(observer.page).toHaveURL(new RegExp(`/hr/loans/${hashId}(?:\\?|$)`));
  312 |         await expect(observer.page.getByRole('heading', { name: new RegExp(loan.loan_no) })).toContainText('Pending');
  313 |       },
  314 |       assertInvariants: async () => {
  315 |         const loan = (await loanSnapshot(purpose))[0];
  316 |         expect(loan).toBeTruthy();
  317 |         expect(await journalRows(loan.loan_id)).toHaveLength(0);
  318 |         const hashId = String(journey.values.loanHashId);
  319 |         await expect.poll(async () =>
  320 |           (await noticesForLoan(hashId)).filter((notice) => notice.type === 'loans.submitted').length,
  321 |         {
  322 |           timeout: 15_000,
  323 |           message: 'queued loan-submission FYI should reach the configured Finance audience',
  324 |         }).toBeGreaterThanOrEqual(financeRoleRecipients.length);
  325 |         const notices = await noticesForLoan(hashId);
  326 |         const submitted = notices.filter((notice) => notice.type === 'loans.submitted').map((notice) => notice.recipient_email);
  327 |         assertRecipientsExactly(submitted, financeRoleRecipients);
  328 |         const actionRequired = notices.filter((notice) => notice.type === 'approval.action_required').map((notice) => notice.recipient_email);
  329 |         expect(actionRequired.every((recipient) => recipient === departmentHeadUser.email)).toBe(true);
  330 |         journey.values.initialApprovalNotificationRecipients = actionRequired;
  331 |         expect(notices.filter((notice) => notice.type === 'approval.action_required').every((notice) => notice.link_to === `/hr/loans/${hashId}`)).toBe(true);
  332 |       },
  333 |     });
  334 | 
  335 |     const approvalActors: Array<{ actor: ActorPage; user: E2EActor; index: number }> = [
  336 |       { actor: departmentHead, user: departmentHeadUser, index: 1 },
  337 |       { actor: finance, user: financeUser, index: 2 },
  338 |       { actor: vp, user: vpUser, index: 3 },
  339 |     ];
  340 | 
  341 |     for (const item of approvalActors) {
  342 |       await runProcessStep({
  343 |         ...plannedSteps[item.index],
  344 |         page: item.actor.page,
  345 |         plannedSteps,
  346 |         context: journey,
  347 |         testInfo,
  348 |         action: async () => {
  349 |           const before = await waitForLoan(purpose, 'pending');
  350 |           if (item.index === 1) {
  351 |             await openApprovalQueue(item.actor, before, String(journey.values.loanHashId));
  352 |           } else {
  353 |             await openDashboard(item.actor);
  354 |             await waitForNotification(item.actor, before, String(journey.values.loanHashId));
  355 |           }
  356 |           await expect(item.actor.page.getByRole('button', { name: 'Reject', exact: true })).toBeEnabled();
  357 |           await approveThroughUi(item.actor, String(journey.values.loanHashId));
  358 |         },
  359 |         verify: async () => {
  360 |           const isFinal = item.index === 3;
  361 |           const expectedStatus = isFinal ? 'active' : 'pending';
  362 |           const loan = await waitForLoan(purpose, expectedStatus);
  363 |           const approvals = await approvalRows(loan.loan_id);
  364 |           expect(approvals).toHaveLength(3);
  365 |           for (let index = 0; index < approvals.length; index += 1) {
  366 |             const approval = approvals[index];
  367 |             if (index < item.index) {
  368 |               expect(approval.action).toBe('approved');
  369 |               expect(approval.approver_email).toBe(expectedApprovers[index]);
  370 |             } else {
  371 |               expect(approval.action).toBe('pending');
  372 |               expect(approval.approver_email).toBeNull();
  373 |             }
  374 |           }
  375 |           if (isFinal) {
  376 |             expect(loan.disbursement_journal_entry_id).not.toBeNull();
  377 |             expect(loan.payment_count).toBe(0);
  378 |             const observerStatus = observer.page.getByRole('heading', { name: new RegExp(loan.loan_no) }).getByText('Active', { exact: true });
  379 |             const employeeRow = employee.page.getByRole('row').filter({ hasText: loan.loan_no });
  380 |             await assertSecondViewerRealtime(observerStatus, 'Active');
  381 |             await assertSecondViewerRealtime(employeeRow.getByText('Active', { exact: true }), 'Active');
  382 |             await expect(observer.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  383 |             await expect(employee.page.getByRole('button', { name: /^Withdraw Cash Advance$/ })).toHaveCount(0);
  384 |           } else {
  385 |             expect(loan.disbursement_journal_entry_id).toBeNull();
  386 |             await expect(observer.page.getByRole('heading', { name: new RegExp(loan.loan_no) })).toContainText('Pending');
  387 |           }
  388 |         },
  389 |         assertInvariants: async () => {
  390 |           const loan = (await loanSnapshot(purpose))[0];
  391 |           const approvals = await approvalRows(loan.loan_id);
  392 |           const audits = await auditRows(loan.loan_id);
  393 |           const currentApproval = approvals[item.index - 1];
  394 |           expect(currentApproval.action).toBe('approved');
  395 |           expect(currentApproval.approver_email).toBe(item.user.email);
  396 |           const actorAudit = audits.find((entry) => entry.actor_email === item.user.email && entry.action === 'updated');
> 397 |           expect(actorAudit, 'the acting role should be the loan audit actor for its approval').toBeTruthy();
      |                                                                                                 ^ Error: the acting role should be the loan audit actor for its approval
  398 |           assertAuditActor({ actor_email: actorAudit!.actor_email ?? undefined }, item.user.email);
  399 |           const hashId = String(journey.values.loanHashId);
  400 |           const notices = await noticesForLoan(hashId);
  401 |           const actionRequired = notices.filter((notice) => notice.type === 'approval.action_required');
  402 |           const expectedActionRecipients = [
  403 |             ...(item.index >= 1 ? financeRoleRecipients : []),
  404 |             ...(item.index >= 2 ? [vpUser.email] : []),
  405 |           ];
  406 |           assertRecipientsExactly(actionRequired.map((notice) => notice.recipient_email), expectedActionRecipients);
  407 |           expect(actionRequired.every((notice) => notice.link_to === `/hr/loans/${hashId}`)).toBe(true);
  408 |           if (item.index === 3) {
  409 |             const completed = notices.filter((notice) => notice.type === 'approval.completed').map((notice) => notice.recipient_email);
  410 |             assertRecipientsExactly(completed, [employeeUser.email]);
  411 |             const journals = await journalRows(loan.loan_id);
  412 |             expect(journals).toHaveLength(1);
  413 |             expect(journals[0].status).toBe('posted');
  414 |             expect(journals[0].created_by_email).toBe(vpUser.email);
  415 |             expect(Number(journals[0].total_debit)).toBe(Number(journals[0].total_credit));
  416 |             expect(Number(journals[0].line_debit)).toBe(Number(journals[0].line_credit));
  417 |           } else {
  418 |             expect(await journalRows(loan.loan_id)).toHaveLength(0);
  419 |           }
  420 |         },
  421 |       });
  422 |     }
  423 | 
  424 |     const finalLoan = await waitForLoan(purpose, 'active');
  425 |     const emailsAfter = await listMailpitMessages();
  426 |     const newParticipantEmails = emailsAfter.filter((message) =>
  427 |       !mailIdsBefore.has(message.ID)
  428 |       && message.To.some((recipient) => participants.includes(recipient.Address.toLowerCase())),
  429 |     );
  430 |     expect(newParticipantEmails, 'loan request and approval hand-offs are in-app notifications, with no email template configured').toHaveLength(0);
  431 |     expect(finalLoan.pay_periods_remaining).toBe(periods);
  432 |     await Promise.all([employee.diagnostics.assertClean(), departmentHead.diagnostics.assertClean(), finance.diagnostics.assertClean(), vp.diagnostics.assertClean(), observer.diagnostics.assertClean()]);
  433 |   } finally {
  434 |     await Promise.all([employee.context.close(), departmentHead.context.close(), finance.context.close(), vp.context.close(), observer.context.close()]);
  435 |   }
  436 | });
  437 | 
```