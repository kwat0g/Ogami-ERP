# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: production-stock.floor.spec.ts >> PROD01-HAPPY — stock work order, Warehouse handover, startup QC, production, and outgoing release
- Location: e2e-process/production-stock.floor.spec.ts:284:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('heading', { name: 'Production Work Orders', exact: true })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByRole('heading', { name: 'Production Work Orders', exact: true }) with timeout 8000ms
  - waiting for getByRole('heading', { name: 'Production Work Orders', exact: true })

```

```yaml
- link "Skip to content":
  - /url: "#main-content"
- banner:
  - button "Toggle sidebar":
    - img
  - link "Ogami ERP":
    - /url: /dashboard
    - img "Ogami ERP"
  - navigation "Breadcrumb":
    - link "Production":
      - /url: /production
    - img
    - text: Work Orders
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Notifications, 8 unread":
    - img
  - 'status "Real-time updates: Live. Last updated 6:20 PM"': Live
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
          - 'button "View all Notifications signals: 8 Unread notifications"': "8"
    - button "Overview"
    - button "Production Planning (MRP)"
    - button "Production" [disabled] [expanded]
    - list:
      - listitem:
        - link "Production Work Orders":
          - /url: /production/work-orders
          - img
          - text: Production Work Orders
      - listitem:
        - link "Production Schedule (Gantt)":
          - /url: /production/schedule
          - img
          - text: Production Schedule (Gantt)
      - listitem:
        - link "Production Routings":
          - /url: /production/routings
          - img
          - text: Production Routings
    - button "Sales & CRM"
    - button "Maintenance"
    - button "Assets"
  - group "Module controls":
    - button "Expand all"
- main:
  - heading "Work orders" [level=1]
  - text: 0 WOs
  - button "Produce for stock"
  - search:
    - textbox "Search WO number or product…"
  - button "Saved views": Views
  - combobox "Signal": "Signal: All"
  - button "Show Signal"
  - combobox "Purpose": "Purpose: All"
  - button "Show Purpose"
  - combobox "Status": "Status: All"
  - button "Show Status"
  - img
  - heading "No work orders yet" [level=3]
  - paragraph: Plan production for inventory, or confirm a Sales Order to produce its shortage.
```

# Test source

```ts
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
  487 |         context.values[`location:${material.code}`] = (await mainWarehouse.innerText()).trim();
  488 |         await mainWarehouse.click();
  489 |         await warehouse.page.getByRole('spinbutton', { name: /Quantity/ }).fill(String(material.opening_quantity));
  490 |         await warehouse.page.getByRole('spinbutton', { name: /Unit cost/ }).fill(String(material.standard_cost));
  491 |         await warehouse.page.getByRole('combobox', { name: /Reason code/ }).selectOption('found_stock');
  492 |         await warehouse.page.getByRole('textbox', { name: /Reason \(recorded for audit\)/ }).fill(reason);
  493 |         await expect(warehouse.page.getByText('Finance approval required', { exact: true })).toHaveCount(0);
  494 |         await warehouse.page.getByRole('button', { name: 'Review adjustment', exact: true }).click();
  495 |         const confirmation = warehouse.page.getByRole('dialog');
  496 |         await expect(confirmation.getByRole('heading', { name: 'Submit stock adjustment?' })).toBeVisible();
  497 |         await confirmation.getByRole('button', { name: 'Submit adjustment', exact: true }).click();
  498 |         await expect(warehouse.page).toHaveURL('/inventory/stock-levels?view=movements');
  499 |         await openMenuItem(warehouse.page, 'Warehouse', 'Stock Adjustments', '/inventory/stock-adjustments');
  500 |         await expect(warehouse.page.getByRole('row').filter({ hasText: reason })).toContainText('Approved by');
  501 |       }, async () => {
  502 |         const rows = await readOnlyDatabaseJson<Array<{
  503 |           status: string; direction: string; quantity: string; requester: string; approver: string;
  504 |           movement_type: string; movement_count: number; on_hand: string;
  505 |         }>>(`
  506 |           SELECT adjustment.status, adjustment.direction, adjustment.quantity::text,
  507 |                  requester.email AS requester, approver.email AS approver,
  508 |                  movement.movement_type,
  509 |                  (SELECT COUNT(*)::int FROM stock_movements duplicate
  510 |                    WHERE duplicate.reference_type = 'stock_adjustment' AND duplicate.reference_id = adjustment.id) AS movement_count,
  511 |                  COALESCE((SELECT SUM(level.quantity) FROM stock_levels level WHERE level.item_id = item.id), 0)::numeric(18, 3)::text AS on_hand
  512 |           FROM stock_adjustments adjustment
  513 |           JOIN items item ON item.id = adjustment.item_id
  514 |           JOIN users requester ON requester.id = adjustment.requested_by
  515 |           LEFT JOIN users approver ON approver.id = adjustment.approved_by
  516 |           LEFT JOIN stock_movements movement ON movement.id = adjustment.stock_movement_id
  517 |           WHERE adjustment.reason = ${sqlText(reason)} AND item.id = ${material.item_id}
  518 |         `);
  519 |         expect(rows).toEqual([{
  520 |           status: 'approved', direction: 'in', quantity: material.opening_quantity.toFixed(3),
  521 |           requester: warehouseIdentity.email, approver: warehouseIdentity.email,
  522 |           movement_type: 'adjustment_in', movement_count: 1, on_hand: material.opening_quantity.toFixed(3),
  523 |         }]);
  524 |       }, async () => {
  525 |         expect(await readOnlyDatabaseJson<Array<{ count: number }>>(`
  526 |           SELECT COUNT(*)::int AS count FROM stock_adjustments WHERE reason = ${sqlText(reason)}
  527 |         `)).toEqual([{ count: 1 }]);
  528 |       });
  529 |     }
  530 | 
  531 |     const planStep = 1 + materials.length;
  532 |     ppc = await openActor(ppcIdentity.actor);
  533 |     await step(planStep, ppc, async () => {
  534 |       await openMenuItem(ppc.page, 'Production', 'Production Work Orders', '/production/work-orders');
> 535 |       await expect(ppc.page.getByRole('heading', { name: 'Production Work Orders', exact: true })).toBeVisible();
      |                                                                                                    ^ Error: expect(locator).toBeVisible() failed
  536 |       await ppc.page.getByRole('button', { name: 'Produce for stock', exact: true }).click();
  537 |       await expect(ppc.page.getByRole('heading', { name: 'Produce for stock', exact: true })).toBeVisible();
  538 |       const productSelect = ppc.page.getByRole('combobox', { name: 'Product', exact: true });
  539 |       const productOption = productSelect.locator('option').filter({ hasText: 'WB-001' });
  540 |       await expect(productOption).toHaveCount(1);
  541 |       const productLabel = (await productOption.innerText()).trim();
  542 |       await productSelect.selectOption({ label: productLabel });
  543 |       await ppc.page.getByRole('spinbutton', { name: 'Quantity', exact: true }).fill(String(target));
  544 |       await ppc.page.getByRole('button', { name: 'Plan production', exact: true }).click();
  545 |       await expect(ppc.page.getByRole('heading', { level: 1 })).toContainText(/WO-/);
  546 |       const heading = await ppc.page.getByRole('heading', { level: 1 }).innerText();
  547 |       const number = heading.match(/WO-[A-Z0-9-]+/i)?.[0];
  548 |       expect(number, 'generated work-order number should appear in the detail heading').toBeTruthy();
  549 |       context.values.woNumber = number;
  550 |       context.values.woRoute = new URL(ppc.page.url()).pathname;
  551 |       await expect(ppc.page.getByRole('heading', { level: 1 })).toContainText('Planned');
  552 |     }, async () => {
  553 |       const row = await assertWorkOrderState(context, {
  554 |         status: 'planned', quantity_target: target, quantity_produced: 0, quantity_good: 0,
  555 |         quantity_rejected: 0, purpose: 'stock', part_number: 'WB-001', creator_email: ppcIdentity.email,
  556 |       });
  557 |       expect(row.machine_code).toBeNull();
  558 |       expect(row.mold_code).toBeNull();
  559 |       const materialPlan = await readOnlyDatabaseJson<Array<{ lines: number; shortage_prs: number }>>(`
  560 |         SELECT (SELECT COUNT(*)::int FROM work_order_materials material WHERE material.work_order_id = ${row.id}) AS lines,
  561 |                (SELECT COUNT(*)::int FROM purchase_requests request WHERE request.work_order_id = ${row.id}) AS shortage_prs
  562 |       `);
  563 |       expect(materialPlan).toEqual([{ lines: materials.length, shortage_prs: 0 }]);
  564 |       expect(await readOnlyDatabaseJson<Array<{ count: number }>>(`
  565 |         SELECT COUNT(*)::int AS count FROM work_orders WHERE wo_number = ${sqlText(contextValue<string>(context, 'woNumber'))}
  566 |       `)).toEqual([{ count: 1 }]);
  567 |     }, async () => {
  568 |       expect(context.values.woRoute).toMatch(/^\/production\/work-orders\//);
  569 |       expect(context.values.woNumber).toBeTruthy();
  570 |     });
  571 | 
  572 |     const confirmStep = planStep + 1;
  573 |     manager = await openActor(managerIdentity.actor, true);
  574 |     await step(confirmStep, manager, async () => {
  575 |       await openMenuItem(manager.page, 'Production', 'Production Work Orders', '/production/work-orders');
  576 |       const signalButton = manager.page.getByRole('button', { name: /View all Production Work Orders signals/ });
  577 |       await expect(signalButton).toBeVisible();
  578 |       await signalButton.click();
  579 |       const signal = manager.page.getByRole('link', { name: /Planned production work orders awaiting confirmation/ });
  580 |       await expect(signal).toBeVisible();
  581 |       await signal.click();
  582 |       await expect(manager.page).toHaveURL(/\/production\/work-orders\?.*signal=release/);
  583 |       const row = manager.page.getByRole('row').filter({ hasText: contextValue<string>(context, 'woNumber') });
  584 |       await expect(row).toHaveCount(1);
  585 |       await row.click();
  586 |       await expect(manager.page.getByRole('heading', { level: 1 })).toContainText(contextValue<string>(context, 'woNumber'));
  587 |       await expect(manager.page.getByRole('button', { name: 'Confirm', exact: true })).toBeVisible();
  588 |       await manager.page.getByRole('button', { name: 'Confirm', exact: true }).click();
  589 |       const review = manager.page.getByRole('dialog', { name: 'Review and confirm work order' });
  590 |       await expect(review).toBeVisible();
  591 |       await review.getByRole('combobox', { name: 'Number of workers', exact: true }).selectOption('1');
  592 |       await expect(review.getByText('Scheduled time', { exact: true })).toBeVisible();
  593 |       const assignManually = review.getByRole('button', { name: 'Manual assign', exact: true });
  594 |       await expect(assignManually).toBeVisible();
  595 |       await assignManually.click();
  596 |       const proposedRemovers = review.getByRole('button', { name: /^Remove / });
  597 |       const proposedCount = await proposedRemovers.count();
  598 |       expect(proposedCount).toBeLessThanOrEqual(1);
  599 |       if (proposedCount === 1) await proposedRemovers.first().click();
  600 |       const workerSearch = review.getByRole('combobox', { name: 'Add worker', exact: true });
  601 |       await workerSearch.fill(workerProfile.employee_no);
  602 |       const workerOption = review.getByRole('listbox', { name: 'Add worker' }).getByRole('option').filter({ hasText: workerProfile.employee_no });
  603 |       await expect(workerOption).toHaveCount(1);
  604 |       await expect(workerOption).toBeEnabled();
  605 |       await workerOption.click();
  606 |       await review.getByRole('combobox', { name: 'Output recorder', exact: true }).selectOption({ label: workerProfile.full_name });
  607 |       const confirm = review.getByRole('button', { name: 'Confirm work order', exact: true });
  608 |       await expect(confirm).toBeEnabled();
  609 |       await confirm.click();
  610 |       await expect(manager.page.getByRole('heading', { level: 1 })).toContainText('Confirmed');
  611 |     }, async () => {
  612 |       const row = await assertWorkOrderState(context, { status: 'confirmed', quantity_target: target });
  613 |       expect(row.machine_code).toBeTruthy();
  614 |       expect(row.mold_code).toBeTruthy();
  615 |       await expect(ppc.page.getByRole('heading', { level: 1 })).toContainText('Confirmed');
  616 |       await expect(manager.page.getByRole('button', { name: 'Confirm', exact: true })).toHaveCount(0);
  617 |     }, async () => {
  618 |       expect((await readWorkOrder(context)).creator_email).toBe(ppcIdentity.email);
  619 |       const scheduled = await readOnlyDatabaseJson<Array<{ status: string; confirmed_at: string }>>(`
  620 |         SELECT schedule.status, schedule.confirmed_at::text AS confirmed_at
  621 |         FROM production_schedules schedule
  622 |         JOIN work_orders wo ON wo.id = schedule.work_order_id
  623 |         WHERE wo.wo_number = ${sqlText(contextValue<string>(context, 'woNumber'))}
  624 |           AND schedule.status = 'confirmed'
  625 |       `);
  626 |       expect(scheduled).toEqual([{ status: 'confirmed', confirmed_at: expect.any(String) }]);
  627 |       const assignedCrew = await readOnlyDatabaseJson<Array<{
  628 |         assigned_by: string; recorder_email: string; member_email: string; crew_size: number;
  629 |       }>>(`
  630 |         SELECT assigner.email AS assigned_by, recorder.email AS recorder_email,
  631 |                member.email AS member_email, COUNT(crew_member.id)::int AS crew_size
  632 |         FROM work_order_crews crew
  633 |         JOIN work_orders wo ON wo.id = crew.work_order_id
  634 |         JOIN users assigner ON assigner.id = crew.assigned_by
  635 |         JOIN employees recorder_employee ON recorder_employee.id = crew.recorder_employee_id
```