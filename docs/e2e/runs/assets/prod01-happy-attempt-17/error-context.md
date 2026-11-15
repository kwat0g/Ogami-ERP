# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: production-stock.floor.spec.ts >> PROD01-HAPPY — stock work order, Warehouse handover, startup QC, production, and outgoing release
- Location: e2e-process/production-stock.floor.spec.ts:284:1

# Error details

```
TimeoutError: locator.tap: Timeout 15000ms exceeded.
Call log:
  - waiting for getByRole('option').filter({ hasText: 'Main Warehouse' }).first()
    - locator resolved to <button type="button" role="option" tabindex="-1" id="r2c-option-1" aria-selected="false" class="block min-h-11 w-full px-3 py-2 text-left text-sm break-words hover:bg-elevated disabled:opacity-50 disabled:cursor-not-allowed">MW-A-A-01 · Main Warehouse</button>
  - attempting tap action
    - waiting for element to be visible, enabled and stable
    - element is visible, enabled and stable
    - scrolling into view if needed
    - done scrolling
    - element is not visible
  - retrying tap action
    - waiting for element to be visible, enabled and stable
  - element was detached from the DOM, retrying

```

# Test source

```ts
  389 |     { name: 'QC inspects the remaining output batch and submits its passing result', actor: qcIdentity.actor, uiPath: 'Quality → Inspections → remaining outgoing batch → PASS → Submit', expectedStatus: 'awaiting_review' },
  390 |     { name: 'Production Manager follows the second checker notice and releases the final batch', actor: managerIdentity.actor, uiPath: 'Notifications → inspection-awaiting-review → Approve pass', expectedStatus: 'passed; all output batches released' },
  391 |     { name: 'Production Manager verifies automatic work-order closure and the final cross-module ledger', actor: managerIdentity.actor, uiPath: 'Open Production Work Order detail', expectedStatus: 'closed; all production and quality records reconciled' },
  392 |   ];
  393 | 
  394 |   const sessions: ActorPage[] = [];
  395 |   const openActor = async (actor: string, tablet = false): Promise<ActorPage> => {
  396 |     const options = tablet
  397 |       ? { viewport: { width: 810, height: 1080 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2, timezoneId: 'Asia/Manila' }
  398 |       : { timezoneId: 'Asia/Manila' };
  399 |     const session = await actorPage(browser, actor, options);
  400 |     sessions.push(session);
  401 |     await openSession(session);
  402 |     await session.page.clock.setFixedTime(new Date(instant));
  403 |     return session;
  404 |   };
  405 |   let hr: ActorPage;
  406 |   let warehouse: ActorPage;
  407 |   let ppc: ActorPage;
  408 |   let manager: ActorPage;
  409 |   let qc: ActorPage;
  410 |   let worker: ActorPage;
  411 |   const checkDiagnostics = () => sessions.forEach((session) => session.diagnostics.assertClean(journeyId));
  412 |   const step = (
  413 |     index: number,
  414 |     session: ActorPage,
  415 |     action: () => Promise<void>,
  416 |     verify: () => Promise<void>,
  417 |     invariants: () => Promise<void>,
  418 |   ) => runProcessStep({
  419 |     ...plannedSteps[index], page: session.page, plannedSteps, context, testInfo,
  420 |     action, verify,
  421 |     assertInvariants: async () => { await invariants(); checkDiagnostics(); },
  422 |   });
  423 | 
  424 |   let clockFrozen = false;
  425 |   try {
  426 |     await freezeClock(request, instant);
  427 |     clockFrozen = true;
  428 |     expect(Date.parse(await readClock(request))).toBe(Date.parse(instant));
  429 | 
  430 |     hr = await openActor(hrIdentity.actor);
  431 |     await step(0, hr, async () => {
  432 |       await openMenuItem(hr.page, 'Human Resources', 'Attendance & DTR', '/hr/attendance');
  433 |       await expect(hr.page.getByRole('heading', { name: 'Daily Time Records', exact: true })).toBeVisible();
  434 |       await hr.page.getByRole('button', { name: 'Shifts', exact: true }).click();
  435 |       await expect(hr.page.getByRole('heading', { name: 'Shifts', exact: true })).toBeVisible();
  436 |       await hr.page.getByRole('button', { name: 'Bulk assign', exact: true }).click();
  437 |       await expect(hr.page.getByRole('heading', { name: 'Bulk assign shift', exact: true })).toBeVisible();
  438 |       await hr.page.getByRole('combobox', { name: 'Department', exact: true }).selectOption({ label: 'Production' });
  439 |       await hr.page.getByRole('combobox', { name: 'Shift', exact: true }).selectOption({ label: plannedShift.name });
  440 |       await hr.page.getByRole('textbox', { name: 'Effective from', exact: true }).fill(plannedShift.effectiveFrom);
  441 |       for (const day of ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']) {
  442 |         await hr.page.getByRole('checkbox', { name: day, exact: true }).check();
  443 |       }
  444 |       await hr.page.getByRole('button', { name: 'Assign shift', exact: true }).click();
  445 |       await expect(hr.page.getByText(/Assigned shift to \d+ employees\./)).toBeVisible();
  446 |     }, async () => {
  447 |       const assignments = await readOnlyDatabaseJson<Array<{ count: number; employee_email: string }>>(`
  448 |         SELECT COUNT(*)::int AS count, employee.email AS employee_email
  449 |         FROM employee_shift_assignments assignment
  450 |         JOIN employees employee ON employee.id = assignment.employee_id
  451 |         JOIN shifts shift ON shift.id = assignment.shift_id
  452 |         WHERE employee.email = ${sqlText(workerIdentity.email)} AND shift.name = ${sqlText(plannedShift.name)}
  453 |           AND assignment.effective_date = ${sqlText(plannedShift.effectiveFrom)}::date
  454 |           AND assignment.effective_date <= (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date
  455 |           AND (assignment.end_date IS NULL OR assignment.end_date >= (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date)
  456 |         GROUP BY employee.email
  457 |       `);
  458 |       expect(assignments).toEqual([{ count: 1, employee_email: workerIdentity.email }]);
  459 |       context.values.shiftAssignmentEmployee = assignments[0].employee_email;
  460 |     }, async () => {
  461 |       expect(context.values.shiftAssignmentEmployee).toBe(workerIdentity.email);
  462 |     });
  463 |     await hr.context.close();
  464 | 
  465 |     warehouse = await openActor(warehouseIdentity.actor);
  466 |     for (let materialIndex = 0; materialIndex < materials.length; materialIndex += 1) {
  467 |       const material = materials[materialIndex];
  468 |       const stepIndex = 1 + materialIndex;
  469 |       const reason = `${context.values.runPrefix} opening stock ${material.code}`;
  470 |       context.values[`stockReason:${material.code}`] = reason;
  471 |       await step(stepIndex, warehouse, async () => {
  472 |         await openMenuItem(warehouse.page, 'Warehouse', 'Stock Adjustments', '/inventory/stock-adjustments');
  473 |         await expect(warehouse.page.getByRole('heading', { name: 'Stock Adjustments', exact: true })).toBeVisible();
  474 |         await warehouse.page.getByRole('link', { name: 'New adjustment', exact: true }).click();
  475 |         await expect(warehouse.page.getByRole('heading', { name: 'Stock adjustment', exact: true })).toBeVisible();
  476 |         await warehouse.page.getByRole('combobox', { name: /Direction/ }).selectOption('in');
  477 |         const itemInput = warehouse.page.getByRole('combobox', { name: 'Item', exact: true });
  478 |         await itemInput.fill(material.code);
  479 |         const itemOption = warehouse.page.getByRole('option').filter({ hasText: material.code }).first();
  480 |         await expect(itemOption).toBeVisible();
  481 |         await itemOption.click();
  482 |         const location = warehouse.page.getByRole('combobox', { name: /Location/ });
  483 |         await expect(location).toBeEnabled();
  484 |         await location.click();
  485 |         const mainWarehouse = warehouse.page.getByRole('option').filter({ hasText: 'Main Warehouse' }).first();
  486 |         await expect(mainWarehouse).toBeVisible();
  487 |         const locationLabel = (await mainWarehouse.innerText()).trim();
  488 |         context.values[`location:${material.code}`] = locationLabel;
> 489 |         await mainWarehouse.tap();
      |                             ^ TimeoutError: locator.tap: Timeout 15000ms exceeded.
  490 |         await expect(location).toHaveValue(locationLabel);
  491 |         await warehouse.page.getByRole('spinbutton', { name: /Quantity/ }).fill(String(material.opening_quantity));
  492 |         await warehouse.page.getByRole('spinbutton', { name: /Unit cost/ }).fill(String(material.standard_cost));
  493 |         await warehouse.page.getByRole('combobox', { name: /Reason code/ }).selectOption('found_stock');
  494 |         await warehouse.page.getByRole('textbox', { name: /Reason \(recorded for audit\)/ }).fill(reason);
  495 |         await expect(warehouse.page.getByText('Finance approval required', { exact: true })).toHaveCount(0);
  496 |         await warehouse.page.getByRole('button', { name: 'Review adjustment', exact: true }).click();
  497 |         const confirmation = warehouse.page.getByRole('dialog');
  498 |         await expect(confirmation.getByRole('heading', { name: 'Submit stock adjustment?' })).toBeVisible();
  499 |         await confirmation.getByRole('button', { name: 'Submit adjustment', exact: true }).click();
  500 |         await expect(warehouse.page).toHaveURL('/inventory/stock-levels?view=movements');
  501 |         await openMenuItem(warehouse.page, 'Warehouse', 'Stock Adjustments', '/inventory/stock-adjustments');
  502 |         await expect(warehouse.page.getByRole('row').filter({ hasText: reason })).toContainText('Approved by');
  503 |       }, async () => {
  504 |         const rows = await readOnlyDatabaseJson<Array<{
  505 |           status: string; direction: string; quantity: string; requester: string; approver: string;
  506 |           movement_type: string; movement_count: number; on_hand: string;
  507 |         }>>(`
  508 |           SELECT adjustment.status, adjustment.direction, adjustment.quantity::text,
  509 |                  requester.email AS requester, approver.email AS approver,
  510 |                  movement.movement_type,
  511 |                  (SELECT COUNT(*)::int FROM stock_movements duplicate
  512 |                    WHERE duplicate.reference_type = 'stock_adjustment' AND duplicate.reference_id = adjustment.id) AS movement_count,
  513 |                  COALESCE((SELECT SUM(level.quantity) FROM stock_levels level WHERE level.item_id = item.id), 0)::numeric(18, 3)::text AS on_hand
  514 |           FROM stock_adjustments adjustment
  515 |           JOIN items item ON item.id = adjustment.item_id
  516 |           JOIN users requester ON requester.id = adjustment.requested_by
  517 |           LEFT JOIN users approver ON approver.id = adjustment.approved_by
  518 |           LEFT JOIN stock_movements movement ON movement.id = adjustment.stock_movement_id
  519 |           WHERE adjustment.reason = ${sqlText(reason)} AND item.id = ${material.item_id}
  520 |         `);
  521 |         expect(rows).toEqual([{
  522 |           status: 'approved', direction: 'in', quantity: material.opening_quantity.toFixed(3),
  523 |           requester: warehouseIdentity.email, approver: warehouseIdentity.email,
  524 |           movement_type: 'adjustment_in', movement_count: 1, on_hand: material.opening_quantity.toFixed(3),
  525 |         }]);
  526 |       }, async () => {
  527 |         expect(await readOnlyDatabaseJson<Array<{ count: number }>>(`
  528 |           SELECT COUNT(*)::int AS count FROM stock_adjustments WHERE reason = ${sqlText(reason)}
  529 |         `)).toEqual([{ count: 1 }]);
  530 |       });
  531 |     }
  532 | 
  533 |     const planStep = 1 + materials.length;
  534 |     ppc = await openActor(ppcIdentity.actor);
  535 |     await step(planStep, ppc, async () => {
  536 |       await openMenuItem(ppc.page, 'Production', 'Production Work Orders', '/production/work-orders');
  537 |       await expect(ppc.page.getByRole('heading', { name: 'Work orders', exact: true })).toBeVisible();
  538 |       await ppc.page.getByRole('button', { name: 'Produce for stock', exact: true }).click();
  539 |       await expect(ppc.page.getByRole('heading', { name: 'Produce for stock', exact: true })).toBeVisible();
  540 |       const productSelect = ppc.page.getByRole('combobox', { name: 'Product', exact: true });
  541 |       const productOption = productSelect.locator('option').filter({ hasText: 'WB-001' });
  542 |       await expect(productOption).toHaveCount(1);
  543 |       const productLabel = (await productOption.innerText()).trim();
  544 |       await productSelect.selectOption({ label: productLabel });
  545 |       await ppc.page.getByRole('spinbutton', { name: 'Quantity', exact: true }).fill(String(target));
  546 |       await ppc.page.getByRole('button', { name: 'Plan production', exact: true }).click();
  547 |       await expect(ppc.page.getByRole('heading', { level: 1 })).toContainText(/WO-/);
  548 |       const heading = await ppc.page.getByRole('heading', { level: 1 }).innerText();
  549 |       const number = heading.match(/WO-[A-Z0-9-]+/i)?.[0];
  550 |       expect(number, 'generated work-order number should appear in the detail heading').toBeTruthy();
  551 |       context.values.woNumber = number;
  552 |       context.values.woRoute = new URL(ppc.page.url()).pathname;
  553 |       await expect(ppc.page.getByRole('heading', { level: 1 })).toContainText('Planned');
  554 |     }, async () => {
  555 |       const row = await assertWorkOrderState(context, {
  556 |         status: 'planned', quantity_target: target, quantity_produced: 0, quantity_good: 0,
  557 |         quantity_rejected: 0, purpose: 'stock', part_number: 'WB-001', creator_email: ppcIdentity.email,
  558 |       });
  559 |       expect(row.machine_code).toBeNull();
  560 |       expect(row.mold_code).toBeNull();
  561 |       const materialPlan = await readOnlyDatabaseJson<Array<{ lines: number; shortage_prs: number }>>(`
  562 |         SELECT (SELECT COUNT(*)::int FROM work_order_materials material WHERE material.work_order_id = ${row.id}) AS lines,
  563 |                (SELECT COUNT(*)::int FROM purchase_requests request WHERE request.stock_production_work_order_id = ${row.id}) AS shortage_prs
  564 |       `);
  565 |       expect(materialPlan).toEqual([{ lines: materials.length, shortage_prs: 0 }]);
  566 |       expect(await readOnlyDatabaseJson<Array<{ count: number }>>(`
  567 |         SELECT COUNT(*)::int AS count FROM work_orders WHERE wo_number = ${sqlText(contextValue<string>(context, 'woNumber'))}
  568 |       `)).toEqual([{ count: 1 }]);
  569 |     }, async () => {
  570 |       expect(context.values.woRoute).toMatch(/^\/production\/work-orders\//);
  571 |       expect(context.values.woNumber).toBeTruthy();
  572 |     });
  573 | 
  574 |     const confirmStep = planStep + 1;
  575 |     manager = await openActor(managerIdentity.actor, true);
  576 |     await step(confirmStep, manager, async () => {
  577 |       await openMenuItem(manager.page, 'Production', 'Production Work Orders', '/production/work-orders');
  578 |       const signalButton = manager.page.getByRole('button', { name: /View all Production Work Orders signals/ });
  579 |       await expect(signalButton).toBeVisible();
  580 |       await signalButton.click();
  581 |       const signal = manager.page.getByRole('link', { name: /Planned production work orders awaiting confirmation/ });
  582 |       await expect(signal).toBeVisible();
  583 |       await signal.click();
  584 |       await expect(manager.page).toHaveURL(/\/production\/work-orders\?.*signal=release/);
  585 |       const row = manager.page.getByRole('row').filter({ hasText: contextValue<string>(context, 'woNumber') });
  586 |       await expect(row).toHaveCount(1);
  587 |       await row.click();
  588 |       await expect(manager.page.getByRole('heading', { level: 1 })).toContainText(contextValue<string>(context, 'woNumber'));
  589 |       await expect(manager.page.getByRole('button', { name: 'Confirm', exact: true })).toBeVisible();
```