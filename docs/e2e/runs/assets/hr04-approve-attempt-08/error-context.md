# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-leave.process.spec.ts >> HR04-APPROVE — Employee leave request flows through Department Head and HR to attendance
- Location: e2e-process/hr-leave.process.spec.ts:336:1

# Error details

```
Error: expect(locator).toContainText(expected) failed

Locator: getByRole('row').filter({ hasText: 'LR-202611-0001' })
Expected substring: "Pending department head"
Received string:    "LR-202611-0001E2E employeeE2E-2FDC0177057D3A5VLNov 6, 2026 → Nov 6, 20261.0Pending deptApproveReject"
Timeout: 8000ms

Call log:
  - Expect "toContainText" getByRole('row').filter({ hasText: 'LR-202611-0001' }) with timeout 8000ms
  - waiting for getByRole('row').filter({ hasText: 'LR-202611-0001' })
    20 × locator resolved to <tr tabindex="0" aria-keyshortcuts="j k ArrowDown ArrowUp Enter Space Control+A Meta+A" class="border-b border-subtle transition-colors duration-fast motion-reduce:transition-none relative group cursor-pointer active:scale-[0.998] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-inset hover:bg-surface hover:z-10 h-row-compact">…</tr>
       - unexpected value "LR-202611-0001E2E employeeE2E-2FDC0177057D3A5VLNov 6, 2026 → Nov 6, 20261.0Pending deptApproveReject"

```

```yaml
- row "Select row LR-202611-0001 E2E employee E2E-2FDC0177057D3A5 VL Nov 6, 2026 → Nov 6, 2026 1.0 Pending dept Approve Reject":
  - cell "Select row":
    - checkbox "Select row"
  - cell "LR-202611-0001"
  - cell "E2E employee E2E-2FDC0177057D3A5"
  - cell "VL"
  - cell "Nov 6, 2026 → Nov 6, 2026"
  - cell "1.0"
  - cell "Pending dept"
  - cell "Approve Reject":
    - button "Approve"
    - button "Reject"
```

# Test source

```ts
  380 |     await runProcessStep({
  381 |       name: planned[0].name,
  382 |       actor: planned[0].actor,
  383 |       uiPath: planned[0].uiPath,
  384 |       expectedStatus: planned[0].expectedStatus,
  385 |       page: employee.page,
  386 |       plannedSteps: planned,
  387 |       context: journey,
  388 |       testInfo,
  389 |       action: async () => {
  390 |         await employee.page.getByRole('button', { name: /New request/ }).click();
  391 |         const requestDialog = employee.page.getByRole('dialog', { name: 'File Leave Request' });
  392 |         await expect(requestDialog).toBeVisible();
  393 |         await requestDialog.getByRole('combobox', { name: /^Leave type/ })
  394 |           .selectOption({ label: 'Vacation Leave' });
  395 |         await requestDialog.getByLabel(/^Start date/).fill(startDate);
  396 |         await requestDialog.getByLabel(/^End date/).fill(endDate);
  397 |         await employee.page.getByLabel('Reason (optional)', { exact: true }).fill(reason);
  398 |         await expect(employee.page.getByText('Estimated days to be deducted')).toBeVisible();
  399 |         const responsePromise = employee.page.waitForResponse((response) =>
  400 |           response.url().includes('/api/v1/leaves/requests') && response.request().method() === 'POST',
  401 |         );
  402 |         await employee.page.getByRole('button', { name: 'Submit request', exact: true }).click();
  403 |         const response = await responsePromise;
  404 |         expect(response.status(), 'Employee UI submission should create the leave request').toBe(201);
  405 |         const requestLink = employee.page.getByRole('link', { name: /^View leave request / });
  406 |         await expect(requestLink).toHaveCount(1);
  407 |         const linkLabel = await requestLink.getAttribute('aria-label');
  408 |         const number = linkLabel?.replace(/^View leave request /, '');
  409 |         expect(number, 'UI should display the generated leave request number').toBeTruthy();
  410 |         const href = await requestLink.getAttribute('href');
  411 |         expect(href, 'Employee should receive an in-app detail link from the leave list').toMatch(/^\/self-service\/leaves\//);
  412 |         const publicId = href?.split('/').at(-1) ?? '';
  413 |         expect(publicId).not.toBe('');
  414 |         journey.values.employeeNo = balances[0].employee_no;
  415 |         journey.values.leaveRequestNo = number;
  416 |         journey.values.leaveRequestPublicId = publicId;
  417 |         journey.values.startDate = startDate;
  418 |         journey.values.endDate = endDate;
  419 |         journey.values.leaveType = 'VL';
  420 |         journey.values.reason = reason;
  421 |         await requestLink.click();
  422 |         await expect(employee.page.getByRole('heading', { name: new RegExp(number!) })).toBeVisible();
  423 |       },
  424 |       verify: async () => {
  425 |         const snapshot = await expectLeaveStatus(reason, 'pending_dept');
  426 |         expect(snapshot.leave_request_no).toBe(journey.values.leaveRequestNo);
  427 |         expect(snapshot.days).toBe('1.0');
  428 |         expect(snapshot.leave_type_code).toBe('VL');
  429 |         expect(snapshot.requester_email).toBe(employeeUser.email);
  430 |         expect(snapshot.dept_approver_email).toBeNull();
  431 |         expect(snapshot.hr_approver_email).toBeNull();
  432 |         expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used));
  433 |         expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining));
  434 |         expect(snapshot.on_leave_attendance_count).toBe(0);
  435 |         const approvals = await approvalRows(reason);
  436 |         expect(approvals.map((row) => [row.step_order, row.role_slug, row.action, row.approver_email])).toEqual([
  437 |           [1, 'department_head', 'pending', null],
  438 |           [2, 'hr_officer', 'pending', null],
  439 |         ]);
  440 |         await expect(employee.page.getByRole('heading').filter({ hasText: String(journey.values.leaveRequestNo) }))
  441 |           .toContainText('Pending dept');
  442 |         await expect(employee.page.getByRole('button', { name: 'Cancel', exact: true })).toBeVisible();
  443 |         await expect(employee.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  444 |       },
  445 |       assertInvariants: async () => {
  446 |         await expect.poll(async () => {
  447 |           const rows = await readOnlyDatabaseJson<Array<{ type: string; recipient_email: string }>>(`
  448 |             SELECT notification.type, recipient.email AS recipient_email
  449 |               FROM notifications notification
  450 |               JOIN users recipient ON recipient.id = notification.notifiable_id
  451 |                AND notification.notifiable_type = 'App\\Modules\\Auth\\Models\\User'
  452 |              WHERE notification.data->>'entity_id' = ${sqlText(String(journey.values.leaveRequestPublicId))}
  453 |                AND notification.type = 'leave.submitted'
  454 |           `);
  455 |           return rows.map((row) => row.recipient_email).sort();
  456 |         }, { timeout: 20_000, message: 'only eligible Department Heads should receive the initial leave hand-off' })
  457 |           .toEqual([...deptRecipients].sort());
  458 |         employee.diagnostics.assertClean('Employee submits a leave request through self-service');
  459 |       },
  460 |     });
  461 | 
  462 |     await runProcessStep({
  463 |       name: planned[1].name,
  464 |       actor: planned[1].actor,
  465 |       uiPath: planned[1].uiPath,
  466 |       expectedStatus: planned[1].expectedStatus,
  467 |       page: departmentHead.page,
  468 |       plannedSteps: planned,
  469 |       context: journey,
  470 |       testInfo,
  471 |       action: async () => {
  472 |         await openHumanResourcesMenu(departmentHead);
  473 |         await expect.poll(async () => {
  474 |           const badge = departmentHead.page.getByRole('button', { name: 'View all Leave Management signals: 1 Leave requests awaiting your approval', exact: true });
  475 |           return badge.count();
  476 |         }, { timeout: 20_000, message: 'Department Head should receive the actionable Leaves badge' }).toBe(1);
  477 |         await openLeaveApprovalFromBadge(departmentHead, 1);
  478 |         const row = departmentHead.page.getByRole('row').filter({ hasText: String(journey.values.leaveRequestNo) });
  479 |         await expect(row).toHaveCount(1);
> 480 |         await expect(row).toContainText('Pending department head');
      |                           ^ Error: expect(locator).toContainText(expected) failed
  481 |         await expect(row.getByRole('button', { name: 'Approve', exact: true })).toBeEnabled();
  482 |         await expect(row.getByRole('button', { name: 'Reject', exact: true })).toBeEnabled();
  483 |         await row.getByRole('button', { name: 'Approve', exact: true }).click();
  484 |         const confirmation = departmentHead.page.getByRole('dialog', { name: 'Approve leave request?' });
  485 |         await expect(confirmation).toBeVisible();
  486 |         await confirmation.getByRole('button', { name: 'Approve', exact: true }).click();
  487 |         await expect(row).toContainText('Pending HR approval');
  488 |       },
  489 |       verify: async () => {
  490 |         const snapshot = await expectLeaveStatus(reason, 'pending_hr');
  491 |         expect(snapshot.dept_approver_email).toBe(departmentHeadUser.email);
  492 |         expect(snapshot.hr_approver_email).toBeNull();
  493 |         expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used));
  494 |         expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining));
  495 |         expect(snapshot.on_leave_attendance_count).toBe(0);
  496 |         expect(await approvalRows(reason)).toEqual([
  497 |           expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'approved', approver_email: departmentHeadUser.email }),
  498 |           expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'pending', approver_email: null }),
  499 |         ]);
  500 |         await expect(employee.page.getByRole('heading').filter({ hasText: String(journey.values.leaveRequestNo) }))
  501 |           .toContainText('Pending hr');
  502 |         await expect(employee.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  503 |       },
  504 |       assertInvariants: async () => {
  505 |         await expect.poll(async () => {
  506 |           const rows = await readOnlyDatabaseJson<Array<{ recipient_email: string }>>(`
  507 |             SELECT recipient.email AS recipient_email
  508 |               FROM notifications notification
  509 |               JOIN users recipient ON recipient.id = notification.notifiable_id
  510 |                AND notification.notifiable_type = 'App\\Modules\\Auth\\Models\\User'
  511 |              WHERE notification.data->>'entity_id' = ${sqlText(String(journey.values.leaveRequestPublicId))}
  512 |                AND notification.type = 'leave.pending_hr'
  513 |           `);
  514 |           return rows.map((row) => row.recipient_email).sort();
  515 |         }, { timeout: 20_000, message: 'only eligible HR approvers should receive the next leave hand-off' })
  516 |           .toEqual([...hrRecipients].sort());
  517 |         await expect.poll(async () => departmentHead.page.getByRole('button', { name: /^View all Leave Management signals/ }).count())
  518 |           .toBe(0);
  519 |         employee.diagnostics.assertClean('Employee observes Department Head approval live');
  520 |         departmentHead.diagnostics.assertClean('Department Head advances leave to HR');
  521 |       },
  522 |     });
  523 | 
  524 |     await runProcessStep({
  525 |       name: planned[2].name,
  526 |       actor: planned[2].actor,
  527 |       uiPath: planned[2].uiPath,
  528 |       expectedStatus: planned[2].expectedStatus,
  529 |       page: hr.page,
  530 |       plannedSteps: planned,
  531 |       context: journey,
  532 |       testInfo,
  533 |       action: async () => {
  534 |         await openHumanResourcesMenu(hr);
  535 |         await expect.poll(async () => hr.page.getByRole('button', { name: 'View all Leave Management signals: 1 Leave requests awaiting your approval', exact: true }).count(), {
  536 |           timeout: 20_000,
  537 |           message: 'HR should receive the actionable Leaves badge after Department Head approval',
  538 |         }).toBe(1);
  539 |         await openLeaveApprovalFromBadge(hr, 1);
  540 |         const row = hr.page.getByRole('row').filter({ hasText: String(journey.values.leaveRequestNo) });
  541 |         await expect(row).toHaveCount(1);
  542 |         await expect(row).toContainText('Pending HR approval');
  543 |         await expect(row.getByRole('button', { name: 'Approve', exact: true })).toBeEnabled();
  544 |         await expect(row.getByRole('button', { name: 'Reject', exact: true })).toBeEnabled();
  545 |         await row.getByRole('button', { name: 'Approve', exact: true }).click();
  546 |         const confirmation = hr.page.getByRole('dialog', { name: 'Approve leave request?' });
  547 |         await expect(confirmation).toBeVisible();
  548 |         await confirmation.getByRole('button', { name: 'Approve', exact: true }).click();
  549 |         await expect(row).toHaveCount(0);
  550 |       },
  551 |       verify: async () => {
  552 |         const snapshot = await expectLeaveStatus(reason, 'approved');
  553 |         expect(snapshot.dept_approver_email).toBe(departmentHeadUser.email);
  554 |         expect(snapshot.hr_approver_email).toBe(hrUser.email);
  555 |         expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used) + expectedDays);
  556 |         expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining) - expectedDays);
  557 |         expect(snapshot.on_leave_attendance_count).toBe(1);
  558 |         expect(snapshot.request_audit_count).toBeGreaterThanOrEqual(3);
  559 |         expect(await approvalRows(reason)).toEqual([
  560 |           expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'approved', approver_email: departmentHeadUser.email }),
  561 |           expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'approved', approver_email: hrUser.email }),
  562 |         ]);
  563 |         await expect(employee.page.getByRole('heading').filter({ hasText: String(journey.values.leaveRequestNo) }))
  564 |           .toContainText('Approved');
  565 |         await expect(employee.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  566 |         await expect(hr.page.getByRole('button', { name: /^View all Leave Management signals/ })).toHaveCount(0);
  567 |       },
  568 |       assertInvariants: async () => {
  569 |         const publicId = String(journey.values.leaveRequestPublicId);
  570 |         await expect.poll(async () => (await notificationRows(publicId)).map((row) => row.type).sort(), {
  571 |           timeout: 20_000,
  572 |           message: 'leave notifications should reflect submission, HR hand-off and final approval',
  573 |         }).toEqual(['leave.approved', 'leave.pending_hr', 'leave.submitted']);
  574 |         const notifications = await notificationRows(publicId);
  575 |         const initial = notifications.filter((row) => row.type === 'leave.submitted').map((row) => row.recipient_email);
  576 |         const hrHandoff = notifications.filter((row) => row.type === 'leave.pending_hr').map((row) => row.recipient_email);
  577 |         const approved = notifications.filter((row) => row.type === 'leave.approved').map((row) => row.recipient_email);
  578 |         assertRecipientsExactly(initial, deptRecipients);
  579 |         assertRecipientsExactly(hrHandoff, hrRecipients);
  580 |         assertRecipientsExactly(approved, [employeeUser.email]);
```