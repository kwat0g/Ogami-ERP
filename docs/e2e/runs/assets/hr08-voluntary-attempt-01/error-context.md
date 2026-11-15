# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-separation.process.spec.ts >> HR08-VOLUNTARY — voluntary separation through departmental clearance and final pay
- Location: e2e-process/hr-separation.process.spec.ts:406:1

# Error details

```
Error: Finance should receive the configured in-app separation handoff

expect(locator).toBeVisible() failed

Locator: getByRole('button', { name: /^Notifications(?:, \d+ unread)?$/ })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Finance should receive the configured in-app separation handoff getByRole('button', { name: /^Notifications(?:, \d+ unread)?$/ }) with timeout 8000ms
  - waiting for getByRole('button', { name: /^Notifications(?:, \d+ unread)?$/ })

```

# Test source

```ts
  425 |     ...checklist.map((item, index) => {
  426 |       const owner = itemActor(item.department);
  427 |       return {
  428 |         name: `${owner.fallback ? 'HR fallback signs' : owner.actor + ' signs'} ${item.department}: ${item.label}`,
  429 |         actor: owner.fallback ? 'department_head__it → hr_officer fallback' : owner.actor,
  430 |         uiPath: owner.fallback
  431 |           ? 'IT Department Head scope check → HR Separation Clearance badge → clearance → Sign'
  432 |           : 'Sidebar → Separation Clearance badge → pending-clearance signal → employee row → Sign',
  433 |         expectedStatus: index === checklist.length - 1
  434 |           ? 'last item cleared; clearance completed and account deactivation queued'
  435 |           : 'item cleared by the authorized department actor; clearance remains in_progress',
  436 |       };
  437 |     }),
  438 |     { name: 'HR computes final pay after clearance completion', actor: 'hr_officer', uiPath: 'Separation Clearance → Compute final pay', expectedStatus: 'clearance completed; final pay computed; no journal until finalization' },
  439 |     { name: 'HR finalizes and posts final pay', actor: 'hr_officer', uiPath: 'Separation Clearance → Finalize → confirm', expectedStatus: 'clearance finalized; employee resigned; final-pay journal posted and balanced; employee account deactivated' },
  440 |   ];
  441 | 
  442 |   let frozenClock = false;
  443 |   try {
  444 |     const initialClock = await readClock(request);
  445 |     await freezeClock(request, initialClock);
  446 |     frozenClock = true;
  447 |     const effectiveDate = manilaDate(initialClock);
  448 |     const targetRows = await employeeBaseline(employeeActor.email);
  449 |     expect(targetRows, 'the seeded employee actor should resolve to one reference Employee').toHaveLength(1);
  450 |     const target = targetRows[0];
  451 |     expect(target.status, 'the isolated E2E employee should start active').toBe('active');
  452 |     expect(target.user_active, 'the isolated E2E employee account should start enabled').toBe(true);
  453 |     expect(checklist.length, 'a separation cannot complete with an empty clearance checklist').toBeGreaterThan(0);
  454 |     journey.values.employeeNo = target.employee_no;
  455 |     journey.values.effectiveDate = effectiveDate;
  456 |     journey.values.checklist = checklist;
  457 | 
  458 |     for (const actorName of actorNames) contexts.set(actorName, await actorPage(browser, actorName));
  459 |     const hr = contexts.get('hr_officer')!;
  460 |     const finance = contexts.get('finance_officer')!;
  461 |     const adminObserver = contexts.get('system_admin')!;
  462 |     const itDepartmentHead = contexts.get('department_head__it')!;
  463 | 
  464 |     await runProcessStep({
  465 |       ...plannedSteps[0], page: hr.page, plannedSteps, context: journey, testInfo,
  466 |       action: async (page) => {
  467 |         await openDashboard(hr);
  468 |         await openSidebarItem(hr, 'Human Resources', '/hr/employees', 'Employees');
  469 |         await expect(page.getByRole('heading', { name: 'Employees', exact: true })).toBeVisible();
  470 |         await page.getByPlaceholder(/Search name or employee no/).fill(target.employee_no);
  471 |         const employeeRow = page.getByRole('row').filter({ hasText: target.employee_no });
  472 |         await expect(employeeRow).toHaveCount(1);
  473 |         await employeeRow.click();
  474 |         await expect(page.getByRole('heading', { name: /E2E employee/i })).toBeVisible();
  475 |         await expect(page.getByRole('button', { name: 'Separate', exact: true })).toBeEnabled();
  476 |         await page.getByRole('button', { name: 'Separate', exact: true }).click();
  477 |         const modal = page.getByRole('dialog', { name: 'Separate employee' });
  478 |         await expect(modal).toBeVisible();
  479 |         await modal.getByLabel('Reason').selectOption('resigned');
  480 |         await modal.getByLabel('Effective date').fill(effectiveDate);
  481 |         await modal.getByLabel('Remarks').fill(remarks);
  482 |         const responseWait = page.waitForResponse((response) => response.request().method() === 'POST'
  483 |           && /\/employees\/[^/]+\/separation(?:\?|$)/.test(new URL(response.url()).pathname));
  484 |         await modal.getByRole('button', { name: 'Start separation', exact: true }).click();
  485 |         const response = await responseWait;
  486 |         expect(response.status(), 'HR initiation should create the clearance through the UI').toBe(201);
  487 |         await expect(page.getByText(/Separation initiated\. Complete clearance and final pay/)).toBeVisible();
  488 |       },
  489 |       verify: async () => {
  490 |         const clearance = await waitForClearance(target.employee_no, remarks, 'in_progress');
  491 |         expect(clearance.separation_reason).toBe('resigned');
  492 |         expect(clearance.separation_date).toBe(effectiveDate);
  493 |         expect(clearance.employee_status, 'initiation must place the employee on leave until finalization').toBe('on_leave');
  494 |         expect(clearance.employee_user_active, 'account access must remain enabled until all clearance items are complete').toBe(true);
  495 |         expect(clearance.items).toHaveLength(checklist.length);
  496 |         expect(clearance.items.every((item) => item.status === 'pending')).toBe(true);
  497 |         journey.values.clearanceId = clearance.clearance_id;
  498 |         journey.values.clearanceNo = clearance.clearance_no;
  499 |         const audits = await clearanceAudits(clearance.clearance_id);
  500 |         expect(audits[0]?.action).toBe('created');
  501 |         assertClearanceAuditActor(audits[0], hrActor.email);
  502 |         const expected = await expectedNotificationRecipients(clearance.clearance_id);
  503 |         let notices: NotificationSnapshot[] = [];
  504 |         await expect.poll(async () => {
  505 |           notices = await separationNotifications(clearance.clearance_id);
  506 |           return notices.length;
  507 |         }, { timeout: 20_000, message: 'the queued separation notification should reach exactly its configured audience' })
  508 |           .toBe(expected.length);
  509 |         assertRecipientsExactly(notices.map((notice) => notice.recipient_email), expected);
  510 |         expect(notices.every((notice) => notice.type === 'chain.separation_initiated')).toBe(true);
  511 |         await assertInitiationEmails(clearance.clearance_id, initialClock);
  512 |         const emailExpected = await expectedEmailRecipients(clearance.clearance_id);
  513 |         expect(emailExpected.every((email) => expected.includes(email))).toBe(true);
  514 |       },
  515 |       assertInvariants: async () => {
  516 |         hr.diagnostics.assertClean('HR initiates voluntary employee separation');
  517 |       },
  518 |     });
  519 | 
  520 |     const initialClearance = (await clearanceSnapshot(target.employee_no, remarks))[0];
  521 |     await runProcessStep({
  522 |       ...plannedSteps[1], page: finance.page, plannedSteps, context: journey, testInfo,
  523 |       action: async () => {
  524 |         const bell = finance.page.getByRole('button', { name: /^Notifications(?:, \d+ unread)?$/ });
> 525 |         await expect(bell, 'Finance should receive the configured in-app separation handoff').toBeVisible();
      |                                                                                               ^ Error: Finance should receive the configured in-app separation handoff
  526 |         await bell.click();
  527 |         const dialog = finance.page.getByRole('dialog', { name: 'Notifications' });
  528 |         await expect(dialog).toBeVisible();
  529 |         const notice = dialog.getByRole('link').filter({ hasText: 'Separation Initiated' });
  530 |         await expect(notice).toHaveCount(1);
  531 |         await expect(notice).toHaveAttribute('href', /\/hr\/separations\//);
  532 |         journey.values.clearanceHref = await notice.getAttribute('href');
  533 |         await notice.click();
  534 |         await expect(finance.page).toHaveURL(/\/hr\/separations\/[A-Za-z0-9]+(?:\?|$)/);
  535 |         await expect(finance.page.getByRole('heading', { name: initialClearance.clearance_no, exact: true })).toBeVisible();
  536 |       },
  537 |       verify: async () => {
  538 |         const notices = await separationNotifications(initialClearance.clearance_id);
  539 |         const expected = await expectedNotificationRecipients(initialClearance.clearance_id);
  540 |         assertRecipientsExactly(notices.map((notice) => notice.recipient_email), expected);
  541 |         expect(expected).toContain(financeActorEmail(actors));
  542 |         expect(notices.every((notice) => notice.link_to === journey.values.clearanceHref)).toBe(true);
  543 |       },
  544 |       assertInvariants: async () => finance.diagnostics.assertClean('Finance follows the in-app separation notice'),
  545 |     });
  546 | 
  547 |     await runProcessStep({
  548 |       ...plannedSteps[2], page: adminObserver.page, plannedSteps, context: journey, testInfo,
  549 |       action: async () => {
  550 |         await openClearanceFromList(adminObserver, target.employee_no);
  551 |         await expect(adminObserver.page.getByRole('heading', { name: initialClearance.clearance_no, exact: true })).toBeVisible();
  552 |       },
  553 |       verify: async () => {
  554 |         await expect(adminObserver.page.getByText('In progress', { exact: true })).toBeVisible();
  555 |         expect((await clearanceSnapshot(target.employee_no, remarks))[0]?.status).toBe('in_progress');
  556 |       },
  557 |       assertInvariants: async () => adminObserver.diagnostics.assertClean('System Administrator observes without acting'),
  558 |     });
  559 | 
  560 |     for (let index = 0; index < checklist.length; index += 1) {
  561 |       const item = checklist[index];
  562 |       const owner = itemActor(item.department);
  563 |       const acting = contexts.get(owner.actor)!;
  564 |       await runProcessStep({
  565 |         ...plannedSteps[3 + index], page: acting.page, plannedSteps, context: journey, testInfo,
  566 |         action: async () => {
  567 |           if (owner.fallback) {
  568 |             await openDashboard(itDepartmentHead);
  569 |             await openSidebarItem(itDepartmentHead, 'Human Resources', '/hr/separations', 'Separation Clearance');
  570 |             await expect(itDepartmentHead.page.getByRole('heading', { name: 'Separations & clearances', exact: true })).toBeVisible();
  571 |             await expect(itDepartmentHead.page.getByRole('row').filter({ hasText: target.employee_no }),
  572 |               'IT Department Head must not see another department employee clearance').toHaveCount(0);
  573 |             itDepartmentHead.diagnostics.assertClean('IT Department Head is excluded by the employee department scope');
  574 |           }
  575 |           await openPendingClearanceFromSidebar(acting, target.employee_no);
  576 |           const detail = acting.page;
  577 |           await expect(detail.getByRole('heading', { name: initialClearance.clearance_no, exact: true })).toBeVisible();
  578 |           const label = detail.getByText(item.label, { exact: true });
  579 |           await expect(label).toBeVisible();
  580 |           const itemRow = label.locator('..').locator('..');
  581 |           await expect(itemRow.getByRole('button', { name: 'Sign', exact: true })).toBeEnabled();
  582 |           await itemRow.getByRole('button', { name: 'Sign', exact: true }).click();
  583 |           await expect(itemRow.getByText(/Cleared/)).toBeVisible();
  584 |         },
  585 |         verify: async () => {
  586 |           const rows = await clearanceItems(initialClearance.clearance_id);
  587 |           const signedItem = rows.find((row) => row.item_key === item.item_key);
  588 |           expect(signedItem?.status).toBe('cleared');
  589 |           expect(signedItem?.signed_by_email, 'the clearance decision must record the role that owned the UI action').toBe(actorByName(actors, owner.actor).email);
  590 |           expect(signedItem?.signed_at).toBeTruthy();
  591 |           const snapshot = (await clearanceSnapshot(target.employee_no, remarks))[0];
  592 |           expect(snapshot.employee_status).toBe('on_leave');
  593 |           const allClear = rows.every((row) => row.status === 'cleared');
  594 |           expect(snapshot.status).toBe(allClear ? 'completed' : 'in_progress');
  595 |           const audits = await clearanceAudits(initialClearance.clearance_id);
  596 |           const latest = audits.at(-1);
  597 |           expect(latest?.action).toBe('updated');
  598 |           assertClearanceAuditActor(latest, actorByName(actors, owner.actor).email);
  599 |           if (allClear) {
  600 |             await expect.poll(async () => {
  601 |               const current = (await clearanceSnapshot(target.employee_no, remarks))[0];
  602 |               return current?.employee_user_active ?? true;
  603 |             }, { timeout: 20_000, message: 'the completed clearance should queue employee-account deactivation' }).toBe(false);
  604 |           }
  605 |         },
  606 |         assertInvariants: async () => {
  607 |           await assertRealtimeItem(adminObserver, item.label);
  608 |           const current = (await clearanceSnapshot(target.employee_no, remarks))[0];
  609 |           if (current.status === 'completed') {
  610 |             await expect(adminObserver.page.getByText('Completed', { exact: true }),
  611 |               'the second viewer should receive the completed status without refresh').toBeVisible();
  612 |           }
  613 |           acting.diagnostics.assertClean(`${owner.actor} signs ${item.item_key}`);
  614 |           adminObserver.diagnostics.assertClean(`live observer receives ${item.item_key} clearance update`);
  615 |         },
  616 |       });
  617 |     }
  618 | 
  619 |     const completed = (await clearanceSnapshot(target.employee_no, remarks))[0];
  620 |     const finalPayPlanIndex = plannedSteps.length - 2;
  621 |     await runProcessStep({
  622 |       ...plannedSteps[finalPayPlanIndex], page: hr.page, plannedSteps, context: journey, testInfo,
  623 |       action: async () => {
  624 |         await openClearanceFromList(hr, target.employee_no);
  625 |         await expect(hr.page.getByRole('heading', { name: completed.clearance_no, exact: true })).toBeVisible();
```