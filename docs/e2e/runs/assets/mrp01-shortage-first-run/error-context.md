# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: mrp.process.spec.ts >> MRP01-SHORTAGE — confirmed order automatically creates a shortage plan, draft PR, and planned work order
- Location: e2e-process/mrp.process.spec.ts:193:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('link', { name: /MRP planning completed/ })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByRole('link', { name: /MRP planning completed/ }) with timeout 8000ms
  - waiting for getByRole('link', { name: /MRP planning completed/ })

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
  - navigation "Breadcrumb": Notifications
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications, 10 unread":
    - img
  - 'status "Real-time updates: Live. Last updated 6:04 AM"': Live
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E ppc head":
    - img "E2E ppc head": EP
    - text: E2E ppc head
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
          - 'button "View all Action Center signals: 2 Open items needing attention"': "2"
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
          - 'button "View all Notifications signals: 10 Unread notifications"': "10"
    - button "Overview"
    - 'button "Production Planning (MRP): Active MRP plans with shortages: 1"': Production Planning (MRP)
    - 'button "Production: Planned production work orders awaiting confirmation: 1"': Production
    - button "Sales & CRM"
    - button "Maintenance"
    - button "Assets"
  - group "Module controls":
    - button "Expand all"
- main:
  - heading "Notifications" [level=1]
  - text: 10 unread of 10 total
  - button "Clear read" [disabled]:
    - img
    - text: Clear read
  - button "Mark all read":
    - img
    - text: Mark all read
  - radiogroup "Notification filter":
    - radio "All" [checked]
    - radio "Unread 10"
    - radio "Approvals"
    - radio "Alerts"
    - radio "System"
  - text: Today · 10
  - list:
    - listitem:
      - 'link "Sales order SO-202611-0001 confirmed: MRP planning has been queued. Review the order and schedule after it completes."':
        - /url: /crm/sales-orders/j16KyzV0Xl
        - img
        - text: Sales order SO-202611-0001 confirmedSales MRP planning has been queued. Review the order and schedule after it completes. just now
      - button "Mark as read":
        - img
      - button "Dismiss notification":
        - img
    - listitem:
      - 'link "Email delivery failed: Customer sales order confirmation: Sales order SO-202611-0001 was confirmed, but the customer has no usable email address. Review the order and contact the customer through an approved channel."':
        - /url: /crm/sales-orders/j16KyzV0Xl
        - img
        - text: "Email delivery failedEmail Customer sales order confirmation: Sales order SO-202611-0001 was confirmed, but the customer has no usable email address. Review the order and contact the customer through an approved channel. just now"
      - button "Mark as read":
        - img
      - button "Dismiss notification":
        - img
    - listitem:
      - 'link "BOM refreshed: RC-002: Current unit estimate: 24.44. Review cost warnings and selling prices."':
        - /url: /mrp/boms/Q05KjnKXwn
        - img
        - text: "BOM refreshed: RC-002BOM costing Current unit estimate: 24.44. Review cost warnings and selling prices. just now"
      - button "Mark as read":
        - img
      - button "Dismiss notification":
        - img
    - listitem:
      - 'link "BOM refreshed: WB-002: Current unit estimate: 3.79. Review cost warnings and selling prices."':
        - /url: /mrp/boms/l5aKLMVdy3
        - img
        - text: "BOM refreshed: WB-002BOM costing Current unit estimate: 3.79. Review cost warnings and selling prices. just now"
      - button "Mark as read":
        - img
      - button "Dismiss notification":
        - img
    - listitem:
      - 'link "BOM refreshed: PC-001: Current unit estimate: 3.85. Review cost warnings and selling prices."':
        - /url: /mrp/boms/bJyPr1NYe4
        - img
        - text: "BOM refreshed: PC-001BOM costing Current unit estimate: 3.85. Review cost warnings and selling prices. just now"
      - button "Mark as read":
        - img
      - button "Dismiss notification":
        - img
    - listitem:
      - 'link "BOM refreshed: BU-001: Current unit estimate: 3.34. Review cost warnings and selling prices."':
        - /url: /mrp/boms/Jr0VdeN743
        - img
        - text: "BOM refreshed: BU-001BOM costing Current unit estimate: 3.34. Review cost warnings and selling prices. just now"
      - button "Mark as read":
        - img
      - button "Dismiss notification":
        - img
    - listitem:
      - 'link "BOM refreshed: RC-001: Current unit estimate: 11.27. Review cost warnings and selling prices."':
        - /url: /mrp/boms/kAmPX1KB3w
        - img
        - text: "BOM refreshed: RC-001BOM costing Current unit estimate: 11.27. Review cost warnings and selling prices. just now"
      - button "Mark as read":
        - img
      - button "Dismiss notification":
        - img
    - listitem:
      - 'link "BOM refreshed: BB-001: Current unit estimate: 14.83. Review cost warnings and selling prices."':
        - /url: /mrp/boms/W2ePgXKXz8
        - img
        - text: "BOM refreshed: BB-001BOM costing Current unit estimate: 14.83. Review cost warnings and selling prices. just now"
      - button "Mark as read":
        - img
      - button "Dismiss notification":
        - img
    - listitem:
      - 'link "BOM refreshed: WB-001: Current unit estimate: 2.90. Review cost warnings and selling prices."':
        - /url: /mrp/boms/j16KyzV0Xl
        - img
        - text: "BOM refreshed: WB-001BOM costing Current unit estimate: 2.90. Review cost warnings and selling prices. just now"
      - button "Mark as read":
        - img
      - button "Dismiss notification":
        - img
    - listitem:
      - 'link "BOM refreshed: PC-002: Current unit estimate: 3.79. Review cost warnings and selling prices."':
        - /url: /mrp/boms/k0vVWAP1Be
        - img
        - text: "BOM refreshed: PC-002BOM costing Current unit estimate: 3.79. Review cost warnings and selling prices. just now"
      - button "Mark as read":
        - img
      - button "Dismiss notification":
        - img
```

# Test source

```ts
  286 |         expect(rows[0].pr_id).toBeNull();
  287 |         expect(rows[0].work_order_count).toBe(0);
  288 |         expect(Number(rows[0].so_total)).toBeGreaterThan(0);
  289 |       },
  290 |       assertInvariants: async () => {
  291 |         sales.diagnostics.assertClean('sales order draft creation');
  292 |         expect(journey.values.soNumber).toBeTruthy();
  293 |       },
  294 |     });
  295 | 
  296 |     await runProcessStep({
  297 |       name: plannedSteps[1].name,
  298 |       actor: plannedSteps[1].actor,
  299 |       uiPath: plannedSteps[1].uiPath,
  300 |       expectedStatus: plannedSteps[1].expectedStatus,
  301 |       page: ppc.page,
  302 |       plannedSteps,
  303 |       context: journey,
  304 |       testInfo,
  305 |       action: async (page, context) => {
  306 |         await openInternalMenuItem(page, 'Sales & CRM', 'Sales Orders', '/crm/sales-orders');
  307 |         await expect(page.getByRole('heading', { name: 'Sales orders', exact: true })).toBeVisible();
  308 |         await page.getByPlaceholder(/Search SO number or customer/).fill(contextValue<string>(context, 'soNumber'));
  309 |         const row = page.getByRole('row').filter({ hasText: contextValue<string>(context, 'soNumber') });
  310 |         await expect(row).toHaveCount(1);
  311 |         await expect(row).toContainText('Draft');
  312 |         await row.click();
  313 |         await expect(page.getByRole('heading', { name: new RegExp(contextValue<string>(context, 'soNumber')) })).toBeVisible();
  314 |         await expect(page.getByText('Draft', { exact: true }).first()).toBeVisible();
  315 |         await expect(page.getByRole('button', { name: 'Confirm order', exact: true })).toHaveCount(0);
  316 |         await expect(page.getByRole('status', { name: /Real-time updates: Live/ })).toBeVisible();
  317 |       },
  318 |       verify: async (_page, context) => {
  319 |         const rows = await processSnapshot(contextValue<string>(context, 'soNumber'));
  320 |         expect(rows).toHaveLength(1);
  321 |         expect(rows[0].so_status).toBe('draft');
  322 |         expect(rows[0].plan_id).toBeNull();
  323 |       },
  324 |       assertInvariants: async () => {
  325 |         ppc.diagnostics.assertClean('PPC opens the draft as observer');
  326 |         await expect(ppc.page.getByRole('button', { name: 'Confirm order', exact: true })).toHaveCount(0);
  327 |       },
  328 |     });
  329 | 
  330 |     await runProcessStep({
  331 |       name: plannedSteps[2].name,
  332 |       actor: plannedSteps[2].actor,
  333 |       uiPath: plannedSteps[2].uiPath,
  334 |       expectedStatus: plannedSteps[2].expectedStatus,
  335 |       page: sales.page,
  336 |       plannedSteps,
  337 |       context: journey,
  338 |       testInfo,
  339 |       action: async (page, context) => {
  340 |         await expect(page.getByRole('heading', { name: new RegExp(contextValue<string>(context, 'soNumber')) })).toBeVisible();
  341 |         await page.getByRole('button', { name: 'Confirm order', exact: true }).click();
  342 |         const dialog = page.getByRole('dialog');
  343 |         await expect(dialog.getByRole('heading', { name: new RegExp(`Confirm ${contextValue<string>(context, 'soNumber')}`) })).toBeVisible();
  344 |         await expect(dialog).toContainText('Run MRP and check material availability');
  345 |         await dialog.getByRole('button', { name: 'Confirm & Start Chain', exact: true }).click();
  346 |         await expect(page.getByText('Confirmed', { exact: true }).first()).toBeVisible();
  347 |         await expect(page.getByRole('button', { name: 'Confirm order', exact: true })).toHaveCount(0);
  348 |       },
  349 |       verify: async (_page, context) => {
  350 |         await expect.poll(async () => (await processSnapshot(contextValue<string>(context, 'soNumber')))[0]?.so_status)
  351 |           .toBe('confirmed');
  352 |         await expect(ppc.page.getByText('Confirmed', { exact: true }).first(), 'PPC observer should update without refreshing').toBeVisible();
  353 |         const rows = await processSnapshot(contextValue<string>(context, 'soNumber'));
  354 |         expect(rows[0].confirm_actor_email).toBe(salesUser.email);
  355 |         expect(rows[0].confirm_audit_count).toBe(1);
  356 |       },
  357 |       assertInvariants: async () => {
  358 |         sales.diagnostics.assertClean('sales order confirmation');
  359 |         ppc.diagnostics.assertClean('PPC live confirmation observer');
  360 |         await expect(ppc.page.getByRole('button', { name: 'Confirm order', exact: true })).toHaveCount(0);
  361 |       },
  362 |     });
  363 | 
  364 |     await runProcessStep({
  365 |       name: plannedSteps[3].name,
  366 |       actor: plannedSteps[3].actor,
  367 |       uiPath: plannedSteps[3].uiPath,
  368 |       expectedStatus: plannedSteps[3].expectedStatus,
  369 |       page: ppc.page,
  370 |       plannedSteps,
  371 |       context: journey,
  372 |       testInfo,
  373 |       action: async (page, context) => {
  374 |         const soNumber = contextValue<string>(context, 'soNumber');
  375 |         const snapshot = await expectMrpCompletion(soNumber);
  376 |         expect(snapshot.plan_status).toBe('active');
  377 |         expect(snapshot.plan_shortages).toBeGreaterThan(0);
  378 |         expect(snapshot.auto_pr_count).toBeGreaterThan(0);
  379 |         expect(snapshot.draft_wo_count).toBeGreaterThan(0);
  380 |         journey.values.mrpPlanNo = snapshot.mrp_plan_no;
  381 |         journey.values.purchaseRequestNo = snapshot.pr_number;
  382 | 
  383 |         await openInternalMenuItem(page, 'Work', 'Notifications', '/notifications');
  384 |         await expect(page.getByRole('heading', { name: 'Notifications', exact: true })).toBeVisible();
  385 |         const notice = page.getByRole('link', { name: /MRP planning completed/ });
> 386 |         await expect(notice).toBeVisible();
      |                              ^ Error: expect(locator).toBeVisible() failed
  387 |         await expect(notice).toContainText('PPC: open MRP Plans');
  388 |         await notice.click();
  389 |         await expect(page).toHaveURL(/\/mrp\/plans\?run=[A-Za-z0-9_-]+/);
  390 | 
  391 |         const planRow = page.getByRole('row').filter({ hasText: soNumber });
  392 |         await expect(planRow).toHaveCount(1);
  393 |         await expect(planRow).toContainText(String(snapshot.plan_shortages));
  394 |         await expect(planRow).toContainText(String(snapshot.auto_pr_count));
  395 |         await expect(planRow).toContainText(String(snapshot.draft_wo_count));
  396 |         await planRow.click();
  397 | 
  398 |         await expect(page.getByRole('heading', { name: new RegExp(snapshot.mrp_plan_no ?? 'MRP-PLAN') })).toBeVisible();
  399 |         await expect(page.getByText(soNumber, { exact: true })).toBeVisible();
  400 |         await expect(page.getByText('Production shortages', { exact: true })).toBeVisible();
  401 |         await expect(page.getByText(String(snapshot.plan_shortages), { exact: true }).first()).toBeVisible();
  402 |         await expect(page.getByText('Auto PRs', { exact: true })).toBeVisible();
  403 |         await expect(page.getByRole('link', { name: new RegExp(snapshot.pr_number ?? '') })).toBeVisible();
  404 |         await expect(page.getByText(/WO\d|WO-/)).toBeVisible();
  405 |         await expect(page.getByRole('button', { name: 'Re-run', exact: true })).toBeEnabled();
  406 |       },
  407 |       verify: async (_page, context) => {
  408 |         const rows = await processSnapshot(contextValue<string>(context, 'soNumber'));
  409 |         expect(rows).toHaveLength(1);
  410 |         const record = rows[0];
  411 |         expect(record.so_status).toBe('confirmed');
  412 |         expect(record.run_status).toBe('completed');
  413 |         expect(record.run_trigger).toBe('automatic');
  414 |         expect(record.run_user_id).toBeNull();
  415 |         expect(record.run_plans_generated).toBe(1);
  416 |         expect(record.run_prs_created).toBe(1);
  417 |         expect(record.plan_status).toBe('active');
  418 |         expect(record.plan_shortages).toBeGreaterThan(0);
  419 |         expect(record.auto_pr_count).toBe(1);
  420 |         expect(record.draft_wo_count).toBeGreaterThan(0);
  421 |         expect(record.pr_status).toBe('draft');
  422 |         expect(record.is_auto_generated).toBe(true);
  423 |         expect(record.pr_department_code).toBe('PPC');
  424 |         expect(record.pr_line_count).toBeGreaterThan(0);
  425 |         expect(record.pr_item_codes).toBeTruthy();
  426 |         expect(record.work_order_count).toBeGreaterThan(0);
  427 |         expect(record.work_order_statuses).toContain('planned');
  428 |         expect(record.shortage_alert_count).toBe(1);
  429 |         expect(record.confirm_actor_email).toBe(salesUser.email);
  430 |       },
  431 |       assertInvariants: async () => {
  432 |         ppc.diagnostics.assertClean('PPC follows MRP completion handoff');
  433 |         const recipients = await notificationRecipients(contextValue<string>(journey, 'soNumber'));
  434 |         journey.values.notificationRecipientTypes = [...new Set(recipients.map((entry) => entry.type))].sort();
  435 |         expect(recipients.some((entry) => entry.type === 'mrp_run_completed' && entry.email === ppcUser.email)).toBe(true);
  436 |         expect(recipients.some((entry) => entry.type === 'chain.so_confirmed' && entry.email === ppcUser.email)).toBe(true);
  437 |         if (!chosen.customer_email) {
  438 |           expect(recipients.some((entry) => entry.type === 'email.delivery_failed')).toBe(true);
  439 |         }
  440 |         assertRecipientsExactly(
  441 |           recipients.filter((entry) => entry.type === 'mrp_run_completed').map((entry) => entry.email),
  442 |           // MrpAutomationService limits the audience to PPC and Sales; RolePermissionSeeder
  443 |           // grants the required mrp.plans.view + mrp.runs.view pair only to PPC in this baseline.
  444 |           [ppcUser.email],
  445 |         );
  446 |         const messagesAfter = await listMailpitMessages();
  447 |         const newMessages = messagesAfter.filter((message) => !mailBefore.some((before) => before.ID === message.ID));
  448 |         if (chosen.customer_email) {
  449 |           expect(newMessages.flatMap((message) => message.To).some((to) => to.Address.toLowerCase() === chosen.customer_email!.toLowerCase())).toBe(true);
  450 |         }
  451 |         journey.values.mailpitMessagesBefore = mailBefore.length;
  452 |         journey.values.mailpitMessagesAfter = messagesAfter.length;
  453 |       },
  454 |     });
  455 | 
  456 |     await runProcessStep({
  457 |       name: plannedSteps[4].name,
  458 |       actor: plannedSteps[4].actor,
  459 |       uiPath: plannedSteps[4].uiPath,
  460 |       expectedStatus: plannedSteps[4].expectedStatus,
  461 |       page: purchasing.page,
  462 |       plannedSteps,
  463 |       context: journey,
  464 |       testInfo,
  465 |       action: async (page, context) => {
  466 |         const prNumber = contextValue<string>(context, 'purchaseRequestNo');
  467 |         expect(prNumber).toBeTruthy();
  468 |         await openInternalMenuItem(page, 'Procurement', 'Purchase Requests', '/purchasing/purchase-requests');
  469 |         await expect(page.getByRole('heading', { name: 'Purchase requests', exact: true })).toBeVisible();
  470 |         await page.getByPlaceholder(/Search PR number/).fill(prNumber);
  471 |         const row = page.getByRole('row').filter({ hasText: prNumber });
  472 |         await expect(row).toHaveCount(1);
  473 |         await expect(row).toContainText('AUTO');
  474 |         await expect(row).toContainText('Draft');
  475 |         await row.click();
  476 |         await expect(page.getByRole('heading', { name: prNumber, exact: true })).toBeVisible();
  477 |         await expect(page.getByText('Draft', { exact: true }).first()).toBeVisible();
  478 |         await expect(page.getByRole('button', { name: 'Submit', exact: true })).toBeEnabled();
  479 |       },
  480 |       verify: async (_page, context) => {
  481 |         const rows = await processSnapshot(contextValue<string>(context, 'soNumber'));
  482 |         expect(rows[0].pr_number).toBe(contextValue<string>(context, 'purchaseRequestNo'));
  483 |         expect(rows[0].pr_status).toBe('draft');
  484 |         expect(rows[0].is_auto_generated).toBe(true);
  485 |         expect(rows[0].pr_department_code).toBe('PPC');
  486 |       },
```