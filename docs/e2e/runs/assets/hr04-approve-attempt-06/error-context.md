# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-leave.process.spec.ts >> HR04-APPROVE — Employee leave request flows through Department Head and HR to attendance
- Location: e2e-process/hr-leave.process.spec.ts:333:1

# Error details

```
Error: Department Head should receive the actionable Leaves badge

Department Head should receive the actionable Leaves badge

expect(received).toBe(expected) // Object.is equality

Expected: 1
Received: 0

Call Log:
- Timeout 20000ms exceeded while waiting on the predicate
```

# Test source

```ts
  372 |         FROM leave_requests request
  373 |        WHERE request.employee_id = ${balances[0].employee_id}
  374 |     `);
  375 |     expect(cleanRequests[0]?.request_count).toBe(0);
  376 | 
  377 |     await runProcessStep({
  378 |       name: planned[0].name,
  379 |       actor: planned[0].actor,
  380 |       uiPath: planned[0].uiPath,
  381 |       expectedStatus: planned[0].expectedStatus,
  382 |       page: employee.page,
  383 |       plannedSteps: planned,
  384 |       context: journey,
  385 |       testInfo,
  386 |       action: async () => {
  387 |         await employee.page.getByRole('button', { name: /New request/ }).click();
  388 |         const requestDialog = employee.page.getByRole('dialog', { name: 'File Leave Request' });
  389 |         await expect(requestDialog).toBeVisible();
  390 |         await requestDialog.getByRole('combobox', { name: /^Leave type/ })
  391 |           .selectOption({ label: 'Vacation Leave' });
  392 |         await requestDialog.getByLabel(/^Start date/).fill(startDate);
  393 |         await requestDialog.getByLabel(/^End date/).fill(endDate);
  394 |         await employee.page.getByLabel('Reason (optional)', { exact: true }).fill(reason);
  395 |         await expect(employee.page.getByText('Estimated days to be deducted')).toBeVisible();
  396 |         const responsePromise = employee.page.waitForResponse((response) =>
  397 |           response.url().includes('/api/v1/leaves/requests') && response.request().method() === 'POST',
  398 |         );
  399 |         await employee.page.getByRole('button', { name: 'Submit request', exact: true }).click();
  400 |         const response = await responsePromise;
  401 |         expect(response.status(), 'Employee UI submission should create the leave request').toBe(201);
  402 |         const requestLink = employee.page.getByRole('link', { name: /^View leave request / });
  403 |         await expect(requestLink).toHaveCount(1);
  404 |         const linkLabel = await requestLink.getAttribute('aria-label');
  405 |         const number = linkLabel?.replace(/^View leave request /, '');
  406 |         expect(number, 'UI should display the generated leave request number').toBeTruthy();
  407 |         const href = await requestLink.getAttribute('href');
  408 |         expect(href, 'Employee should receive an in-app detail link from the leave list').toMatch(/^\/self-service\/leaves\//);
  409 |         const publicId = href?.split('/').at(-1) ?? '';
  410 |         expect(publicId).not.toBe('');
  411 |         journey.values.employeeNo = balances[0].employee_no;
  412 |         journey.values.leaveRequestNo = number;
  413 |         journey.values.leaveRequestPublicId = publicId;
  414 |         journey.values.startDate = startDate;
  415 |         journey.values.endDate = endDate;
  416 |         journey.values.leaveType = 'VL';
  417 |         journey.values.reason = reason;
  418 |         await requestLink.click();
  419 |         await expect(employee.page.getByRole('heading', { name: new RegExp(number!) })).toBeVisible();
  420 |       },
  421 |       verify: async () => {
  422 |         const snapshot = await expectLeaveStatus(reason, 'pending_dept');
  423 |         expect(snapshot.leave_request_no).toBe(journey.values.leaveRequestNo);
  424 |         expect(snapshot.days).toBe('1.0');
  425 |         expect(snapshot.leave_type_code).toBe('VL');
  426 |         expect(snapshot.requester_email).toBe(employeeUser.email);
  427 |         expect(snapshot.dept_approver_email).toBeNull();
  428 |         expect(snapshot.hr_approver_email).toBeNull();
  429 |         expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used));
  430 |         expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining));
  431 |         expect(snapshot.on_leave_attendance_count).toBe(0);
  432 |         const approvals = await approvalRows(reason);
  433 |         expect(approvals.map((row) => [row.step_order, row.role_slug, row.action, row.approver_email])).toEqual([
  434 |           [1, 'department_head', 'pending', null],
  435 |           [2, 'hr_officer', 'pending', null],
  436 |         ]);
  437 |         await expect(employee.page.getByRole('heading').filter({ hasText: String(journey.values.leaveRequestNo) }))
  438 |           .toContainText('Pending dept');
  439 |         await expect(employee.page.getByRole('button', { name: 'Cancel', exact: true })).toBeVisible();
  440 |         await expect(employee.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  441 |       },
  442 |       assertInvariants: async () => {
  443 |         await expect.poll(async () => {
  444 |           const rows = await readOnlyDatabaseJson<Array<{ type: string; recipient_email: string }>>(`
  445 |             SELECT notification.type, recipient.email AS recipient_email
  446 |               FROM notifications notification
  447 |               JOIN users recipient ON recipient.id = notification.notifiable_id
  448 |                AND notification.notifiable_type = 'App\\Modules\\Auth\\Models\\User'
  449 |              WHERE notification.data->>'entity_id' = ${sqlText(String(journey.values.leaveRequestPublicId))}
  450 |                AND notification.type = 'leave.submitted'
  451 |           `);
  452 |           return rows.map((row) => row.recipient_email).sort();
  453 |         }, { timeout: 20_000, message: 'only eligible Department Heads should receive the initial leave hand-off' })
  454 |           .toEqual([...deptRecipients].sort());
  455 |         employee.diagnostics.assertClean('Employee submits a leave request through self-service');
  456 |       },
  457 |     });
  458 | 
  459 |     await runProcessStep({
  460 |       name: planned[1].name,
  461 |       actor: planned[1].actor,
  462 |       uiPath: planned[1].uiPath,
  463 |       expectedStatus: planned[1].expectedStatus,
  464 |       page: departmentHead.page,
  465 |       plannedSteps: planned,
  466 |       context: journey,
  467 |       testInfo,
  468 |       action: async () => {
  469 |         await expect.poll(async () => {
  470 |           const badge = departmentHead.page.getByRole('button', { name: 'View all Leave Management signals: Leaves: 1', exact: true });
  471 |           return badge.count();
> 472 |         }, { timeout: 20_000, message: 'Department Head should receive the actionable Leaves badge' }).toBe(1);
      |                                                                                                        ^ Error: Department Head should receive the actionable Leaves badge
  473 |         await openLeaveApprovalFromBadge(departmentHead, 1);
  474 |         const row = departmentHead.page.getByRole('row').filter({ hasText: String(journey.values.leaveRequestNo) });
  475 |         await expect(row).toHaveCount(1);
  476 |         await expect(row).toContainText('Pending department head');
  477 |         await expect(row.getByRole('button', { name: 'Approve', exact: true })).toBeEnabled();
  478 |         await expect(row.getByRole('button', { name: 'Reject', exact: true })).toBeEnabled();
  479 |         await row.getByRole('button', { name: 'Approve', exact: true }).click();
  480 |         const confirmation = departmentHead.page.getByRole('dialog', { name: 'Approve leave request?' });
  481 |         await expect(confirmation).toBeVisible();
  482 |         await confirmation.getByRole('button', { name: 'Approve', exact: true }).click();
  483 |         await expect(row).toContainText('Pending HR approval');
  484 |       },
  485 |       verify: async () => {
  486 |         const snapshot = await expectLeaveStatus(reason, 'pending_hr');
  487 |         expect(snapshot.dept_approver_email).toBe(departmentHeadUser.email);
  488 |         expect(snapshot.hr_approver_email).toBeNull();
  489 |         expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used));
  490 |         expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining));
  491 |         expect(snapshot.on_leave_attendance_count).toBe(0);
  492 |         expect(await approvalRows(reason)).toEqual([
  493 |           expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'approved', approver_email: departmentHeadUser.email }),
  494 |           expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'pending', approver_email: null }),
  495 |         ]);
  496 |         await expect(employee.page.getByRole('heading').filter({ hasText: String(journey.values.leaveRequestNo) }))
  497 |           .toContainText('Pending hr');
  498 |         await expect(employee.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  499 |       },
  500 |       assertInvariants: async () => {
  501 |         await expect.poll(async () => {
  502 |           const rows = await readOnlyDatabaseJson<Array<{ recipient_email: string }>>(`
  503 |             SELECT recipient.email AS recipient_email
  504 |               FROM notifications notification
  505 |               JOIN users recipient ON recipient.id = notification.notifiable_id
  506 |                AND notification.notifiable_type = 'App\\Modules\\Auth\\Models\\User'
  507 |              WHERE notification.data->>'entity_id' = ${sqlText(String(journey.values.leaveRequestPublicId))}
  508 |                AND notification.type = 'leave.pending_hr'
  509 |           `);
  510 |           return rows.map((row) => row.recipient_email).sort();
  511 |         }, { timeout: 20_000, message: 'only eligible HR approvers should receive the next leave hand-off' })
  512 |           .toEqual([...hrRecipients].sort());
  513 |         await expect.poll(async () => departmentHead.page.getByRole('button', { name: /^View all Leave Management signals/ }).count())
  514 |           .toBe(0);
  515 |         employee.diagnostics.assertClean('Employee observes Department Head approval live');
  516 |         departmentHead.diagnostics.assertClean('Department Head advances leave to HR');
  517 |       },
  518 |     });
  519 | 
  520 |     await runProcessStep({
  521 |       name: planned[2].name,
  522 |       actor: planned[2].actor,
  523 |       uiPath: planned[2].uiPath,
  524 |       expectedStatus: planned[2].expectedStatus,
  525 |       page: hr.page,
  526 |       plannedSteps: planned,
  527 |       context: journey,
  528 |       testInfo,
  529 |       action: async () => {
  530 |         await expect.poll(async () => hr.page.getByRole('button', { name: 'View all Leave Management signals: Leaves: 1', exact: true }).count(), {
  531 |           timeout: 20_000,
  532 |           message: 'HR should receive the actionable Leaves badge after Department Head approval',
  533 |         }).toBe(1);
  534 |         await openLeaveApprovalFromBadge(hr, 1);
  535 |         const row = hr.page.getByRole('row').filter({ hasText: String(journey.values.leaveRequestNo) });
  536 |         await expect(row).toHaveCount(1);
  537 |         await expect(row).toContainText('Pending HR approval');
  538 |         await expect(row.getByRole('button', { name: 'Approve', exact: true })).toBeEnabled();
  539 |         await expect(row.getByRole('button', { name: 'Reject', exact: true })).toBeEnabled();
  540 |         await row.getByRole('button', { name: 'Approve', exact: true }).click();
  541 |         const confirmation = hr.page.getByRole('dialog', { name: 'Approve leave request?' });
  542 |         await expect(confirmation).toBeVisible();
  543 |         await confirmation.getByRole('button', { name: 'Approve', exact: true }).click();
  544 |         await expect(row).toHaveCount(0);
  545 |       },
  546 |       verify: async () => {
  547 |         const snapshot = await expectLeaveStatus(reason, 'approved');
  548 |         expect(snapshot.dept_approver_email).toBe(departmentHeadUser.email);
  549 |         expect(snapshot.hr_approver_email).toBe(hrUser.email);
  550 |         expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used) + expectedDays);
  551 |         expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining) - expectedDays);
  552 |         expect(snapshot.on_leave_attendance_count).toBe(1);
  553 |         expect(snapshot.request_audit_count).toBeGreaterThanOrEqual(3);
  554 |         expect(await approvalRows(reason)).toEqual([
  555 |           expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'approved', approver_email: departmentHeadUser.email }),
  556 |           expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'approved', approver_email: hrUser.email }),
  557 |         ]);
  558 |         await expect(employee.page.getByRole('heading').filter({ hasText: String(journey.values.leaveRequestNo) }))
  559 |           .toContainText('Approved');
  560 |         await expect(employee.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  561 |         await expect(hr.page.getByRole('button', { name: /^View all Leave Management signals/ })).toHaveCount(0);
  562 |       },
  563 |       assertInvariants: async () => {
  564 |         const publicId = String(journey.values.leaveRequestPublicId);
  565 |         await expect.poll(async () => (await notificationRows(publicId)).map((row) => row.type).sort(), {
  566 |           timeout: 20_000,
  567 |           message: 'leave notifications should reflect submission, HR hand-off and final approval',
  568 |         }).toEqual(['leave.approved', 'leave.pending_hr', 'leave.submitted']);
  569 |         const notifications = await notificationRows(publicId);
  570 |         const initial = notifications.filter((row) => row.type === 'leave.submitted').map((row) => row.recipient_email);
  571 |         const hrHandoff = notifications.filter((row) => row.type === 'leave.pending_hr').map((row) => row.recipient_email);
  572 |         const approved = notifications.filter((row) => row.type === 'leave.approved').map((row) => row.recipient_email);
```