# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-leave.process.spec.ts >> HR04-APPROVE — Employee leave request flows through Department Head and HR to attendance
- Location: e2e-process/hr-leave.process.spec.ts:322:1

# Error details

```
TimeoutError: locator.fill: Timeout 15000ms exceeded.
Call log:
  - waiting for getByLabel('Start date', { exact: true })

```

# Test source

```ts
  280 |             AND preference.channel = 'in_app'
  281 |             AND preference.enabled = false
  282 |        )
  283 |        ${permissionSlug === 'leave.approve_hr' ? `AND (
  284 |          EXISTS (
  285 |            SELECT 1 FROM role_permissions role_permission
  286 |            JOIN permissions permission ON permission.id = role_permission.permission_id
  287 |             WHERE role_permission.role_id = recipient.role_id
  288 |               AND permission.slug = 'leave.view'
  289 |          )
  290 |          OR EXISTS (
  291 |            SELECT 1 FROM user_permission_overrides permission_override
  292 |            JOIN permissions permission ON permission.id = permission_override.permission_id
  293 |             WHERE permission_override.user_id = recipient.id
  294 |               AND permission.slug = 'leave.view'
  295 |               AND permission_override.deleted_at IS NULL
  296 |               AND (permission_override.expires_at IS NULL OR permission_override.expires_at > NOW())
  297 |          )
  298 |        )` : ''}
  299 |      ORDER BY recipient.email
  300 |   `);
  301 |   return rows.map((row) => row.email);
  302 | }
  303 | 
  304 | async function mailpitIdsFor(recipients: string[]): Promise<string[]> {
  305 |   const normalized = new Set(recipients.map((email) => email.toLowerCase()));
  306 |   return (await listMailpitMessages())
  307 |     .filter((message) => message.To.some((target) => normalized.has(target.Address.toLowerCase())))
  308 |     .map((message) => message.ID)
  309 |     .sort();
  310 | }
  311 | 
  312 | async function expectLeaveStatus(reason: string, expected: string): Promise<LeaveSnapshot> {
  313 |   let rows: LeaveSnapshot[] = [];
  314 |   await expect.poll(async () => {
  315 |     rows = await leaveSnapshot(reason);
  316 |     return rows[0]?.status ?? 'missing';
  317 |   }, { timeout: 20_000, message: `Leave request should reach ${expected}` }).toBe(expected);
  318 |   expect(rows, `one UI-created leave request should be present for ${reason}`).toHaveLength(1);
  319 |   return rows[0];
  320 | }
  321 | 
  322 | test('HR04-APPROVE — Employee leave request flows through Department Head and HR to attendance', async ({ browser }, testInfo) => {
  323 |   test.setTimeout(120_000);
  324 |   const actors = await loadActors();
  325 |   const employeeUser = actorByName(actors, 'employee');
  326 |   const departmentHeadUser = actorByName(actors, 'department_head');
  327 |   const hrUser = actorByName(actors, 'hr_officer');
  328 |   const balances = await employeeBalanceBaseline(employeeUser.email);
  329 |   expect(balances, 'the seeded Employee should resolve to one Vacation Leave balance row').toHaveLength(1);
  330 |   expect(balances[0].balance_id, 'E2E baseline should include opening Vacation Leave entitlement').not.toBeNull();
  331 |   expect(Number(balances[0].remaining), 'opening leave balance must cover one day').toBeGreaterThanOrEqual(1);
  332 |   expect(balances[0].leave_request_count, 'fresh baseline should not contain leave transactions').toBe(0);
  333 | 
  334 |   const employee = await actorPage(browser, employeeUser.actor);
  335 |   const departmentHead = await actorPage(browser, departmentHeadUser.actor);
  336 |   const hr = await actorPage(browser, hrUser.actor);
  337 |   const journey = createJourneyContext('HR04-APPROVE');
  338 |   const startDate = await nextBusinessDay(employeeUser.email);
  339 |   const endDate = startDate;
  340 |   const reason = `${String(journey.values.runPrefix)} leave approval journey`;
  341 |   const expectedDays = 1;
  342 |   const deptRecipients = await eligibleRecipients(
  343 |     'leave.submitted', 'leave.approve_dept', 'leave.submitted.notification_roles', balances[0].department_id,
  344 |   );
  345 |   const hrRecipients = await eligibleRecipients(
  346 |     'leave.pending_hr', 'leave.approve_hr', 'leave.pending_hr.notification_roles',
  347 |   );
  348 |   expect(deptRecipients).toContain(departmentHeadUser.email);
  349 |   expect(hrRecipients).toContain(hrUser.email);
  350 |   const intendedRecipients = [...new Set([...deptRecipients, ...hrRecipients, employeeUser.email])];
  351 |   const mailBefore = await mailpitIdsFor(intendedRecipients);
  352 |   const planned = plannedSteps;
  353 | 
  354 |   try {
  355 |     await Promise.all([openSelfServiceLeaves(employee), openDashboard(departmentHead), openDashboard(hr)]);
  356 |     await expect(departmentHead.page.getByRole('button', { name: /View all Leaves signals: 0/ })).toHaveCount(0);
  357 |     await expect(hr.page.getByRole('button', { name: /View all Leaves signals: 0/ })).toHaveCount(0);
  358 |     const cleanRequests = await readOnlyDatabaseJson<Array<{ request_count: number }>>(`
  359 |       SELECT COUNT(*)::int AS request_count
  360 |         FROM leave_requests request
  361 |        WHERE request.employee_id = ${balances[0].employee_id}
  362 |     `);
  363 |     expect(cleanRequests[0]?.request_count).toBe(0);
  364 | 
  365 |     await runProcessStep({
  366 |       name: planned[0].name,
  367 |       actor: planned[0].actor,
  368 |       uiPath: planned[0].uiPath,
  369 |       expectedStatus: planned[0].expectedStatus,
  370 |       page: employee.page,
  371 |       plannedSteps: planned,
  372 |       context: journey,
  373 |       testInfo,
  374 |       action: async () => {
  375 |         await employee.page.getByRole('button', { name: /New request/ }).click();
  376 |         const requestDialog = employee.page.getByRole('dialog', { name: 'File Leave Request' });
  377 |         await expect(requestDialog).toBeVisible();
  378 |         await requestDialog.getByRole('combobox', { name: /^Leave type/ })
  379 |           .selectOption({ label: 'Vacation Leave' });
> 380 |         await employee.page.getByLabel('Start date', { exact: true }).fill(startDate);
      |                                                                       ^ TimeoutError: locator.fill: Timeout 15000ms exceeded.
  381 |         await employee.page.getByLabel('End date', { exact: true }).fill(endDate);
  382 |         await employee.page.getByLabel('Reason (optional)', { exact: true }).fill(reason);
  383 |         await expect(employee.page.getByText('Estimated days to be deducted')).toBeVisible();
  384 |         const responsePromise = employee.page.waitForResponse((response) =>
  385 |           response.url().includes('/api/v1/leaves/requests') && response.request().method() === 'POST',
  386 |         );
  387 |         await employee.page.getByRole('button', { name: 'Submit request', exact: true }).click();
  388 |         const response = await responsePromise;
  389 |         expect(response.status(), 'Employee UI submission should create the leave request').toBe(201);
  390 |         const requestLink = employee.page.getByRole('link', { name: /^View leave request / });
  391 |         await expect(requestLink).toHaveCount(1);
  392 |         const linkLabel = await requestLink.getAttribute('aria-label');
  393 |         const number = linkLabel?.replace(/^View leave request /, '');
  394 |         expect(number, 'UI should display the generated leave request number').toBeTruthy();
  395 |         const href = await requestLink.getAttribute('href');
  396 |         expect(href, 'Employee should receive an in-app detail link from the leave list').toMatch(/^\/self-service\/leaves\//);
  397 |         const publicId = href?.split('/').at(-1) ?? '';
  398 |         expect(publicId).not.toBe('');
  399 |         journey.values.employeeNo = balances[0].employee_no;
  400 |         journey.values.leaveRequestNo = number;
  401 |         journey.values.leaveRequestPublicId = publicId;
  402 |         journey.values.startDate = startDate;
  403 |         journey.values.endDate = endDate;
  404 |         journey.values.leaveType = 'VL';
  405 |         journey.values.reason = reason;
  406 |         await requestLink.click();
  407 |         await expect(employee.page.getByRole('heading', { name: new RegExp(number!) })).toBeVisible();
  408 |       },
  409 |       verify: async () => {
  410 |         const snapshot = await expectLeaveStatus(reason, 'pending_dept');
  411 |         expect(snapshot.leave_request_no).toBe(journey.values.leaveRequestNo);
  412 |         expect(snapshot.days).toBe('1.0');
  413 |         expect(snapshot.leave_type_code).toBe('VL');
  414 |         expect(snapshot.requester_email).toBe(employeeUser.email);
  415 |         expect(snapshot.dept_approver_email).toBeNull();
  416 |         expect(snapshot.hr_approver_email).toBeNull();
  417 |         expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used));
  418 |         expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining));
  419 |         expect(snapshot.on_leave_attendance_count).toBe(0);
  420 |         const approvals = await approvalRows(reason);
  421 |         expect(approvals.map((row) => [row.step_order, row.role_slug, row.action, row.approver_email])).toEqual([
  422 |           [1, 'department_head', 'pending', null],
  423 |           [2, 'hr_officer', 'pending', null],
  424 |         ]);
  425 |         await expect(employee.page.getByRole('heading').filter({ hasText: String(journey.values.leaveRequestNo) }))
  426 |           .toContainText('Pending department head');
  427 |         await expect(employee.page.getByRole('button', { name: 'Cancel', exact: true })).toBeVisible();
  428 |         await expect(employee.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  429 |       },
  430 |       assertInvariants: async () => {
  431 |         await expect.poll(async () => {
  432 |           const rows = await readOnlyDatabaseJson<Array<{ type: string; recipient_email: string }>>(`
  433 |             SELECT notification.type, recipient.email AS recipient_email
  434 |               FROM notifications notification
  435 |               JOIN users recipient ON recipient.id = notification.notifiable_id
  436 |                AND notification.notifiable_type = 'App\\Modules\\Auth\\Models\\User'
  437 |              WHERE notification.data->>'entity_id' = ${sqlText(String(journey.values.leaveRequestPublicId))}
  438 |                AND notification.type = 'leave.submitted'
  439 |           `);
  440 |           return rows.map((row) => row.recipient_email).sort();
  441 |         }, { timeout: 20_000, message: 'only eligible Department Heads should receive the initial leave hand-off' })
  442 |           .toEqual([...deptRecipients].sort());
  443 |         employee.diagnostics.assertClean('Employee submits a leave request through self-service');
  444 |       },
  445 |     });
  446 | 
  447 |     await runProcessStep({
  448 |       name: planned[1].name,
  449 |       actor: planned[1].actor,
  450 |       uiPath: planned[1].uiPath,
  451 |       expectedStatus: planned[1].expectedStatus,
  452 |       page: departmentHead.page,
  453 |       plannedSteps: planned,
  454 |       context: journey,
  455 |       testInfo,
  456 |       action: async () => {
  457 |         await expect.poll(async () => {
  458 |           const badge = departmentHead.page.getByRole('button', { name: /View all Leaves signals: 1/ });
  459 |           return badge.count();
  460 |         }, { timeout: 20_000, message: 'Department Head should receive the actionable Leaves badge' }).toBe(1);
  461 |         await openLeaveApprovalFromBadge(departmentHead, 1);
  462 |         const row = departmentHead.page.getByRole('row').filter({ hasText: String(journey.values.leaveRequestNo) });
  463 |         await expect(row).toHaveCount(1);
  464 |         await expect(row).toContainText('Pending department head');
  465 |         await expect(row.getByRole('button', { name: 'Approve', exact: true })).toBeEnabled();
  466 |         await expect(row.getByRole('button', { name: 'Reject', exact: true })).toBeEnabled();
  467 |         await row.getByRole('button', { name: 'Approve', exact: true }).click();
  468 |         const confirmation = departmentHead.page.getByRole('dialog', { name: 'Approve leave request?' });
  469 |         await expect(confirmation).toBeVisible();
  470 |         await confirmation.getByRole('button', { name: 'Approve', exact: true }).click();
  471 |         await expect(row).toContainText('Pending HR approval');
  472 |       },
  473 |       verify: async () => {
  474 |         const snapshot = await expectLeaveStatus(reason, 'pending_hr');
  475 |         expect(snapshot.dept_approver_email).toBe(departmentHeadUser.email);
  476 |         expect(snapshot.hr_approver_email).toBeNull();
  477 |         expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used));
  478 |         expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining));
  479 |         expect(snapshot.on_leave_attendance_count).toBe(0);
  480 |         expect(await approvalRows(reason)).toEqual([
```