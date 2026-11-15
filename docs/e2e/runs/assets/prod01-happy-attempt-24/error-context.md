# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: production-stock.floor.spec.ts >> PROD01-HAPPY — stock work order, Warehouse handover, startup QC, production, and outgoing release
- Location: e2e-process/production-stock.floor.spec.ts:284:1

# Error details

```
Error: expect(page).toHaveURL(expected) failed

Expected: "https://nginx/inventory/stock-levels?view=movements"
Received: "https://nginx/inventory/stock-adjustments/create"
Timeout:  8000ms

Call log:
  - Expect "toHaveURL" with timeout 8000ms
    19 × locator resolved to <html lang="en" data-theme="light">…</html>
       - unexpected value "https://nginx/inventory/stock-adjustments/create"

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
    - link "Warehouse":
      - /url: /inventory
    - img
    - link "Stock Adjustments":
      - /url: /inventory/stock-adjustments
    - img
    - text: Create
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Notifications":
    - img
  - 'status "Real-time updates: Live. Last updated 10:20 PM"': Live
  - button "Profile menu for E2E warehouse staff":
    - img "E2E warehouse staff": EW
    - text: E2E warehouse staff
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
    - 'button "Warehouse: Active items with no usable stock: 20" [disabled] [expanded]': Warehouse
    - list:
      - listitem:
        - link "Inventory Items":
          - /url: /inventory/items
          - img
          - text: Inventory Items
        - 'button "View all Inventory Items signals: 20 Active items with no usable stock, 12 Items at or below reorder point"': "20"
      - listitem:
        - link "Units of Measure":
          - /url: /inventory/uoms
          - img
          - text: Units of Measure
      - listitem:
        - link "Goods Receipts (GRN)":
          - /url: /inventory/grn
          - img
          - text: Goods Receipts (GRN)
      - listitem:
        - link "MRB / Quarantine":
          - /url: /inventory/mrb
          - img
          - text: MRB / Quarantine
      - listitem:
        - link "Material Issues":
          - /url: /inventory/material-issues
          - img
          - text: Material Issues
      - listitem:
        - link "Inventory Stock Levels":
          - /url: /inventory/stock-levels
          - img
          - text: Inventory Stock Levels
      - listitem:
        - link "Stock Adjustments":
          - /url: /inventory/stock-adjustments
          - img
          - text: Stock Adjustments
      - listitem:
        - link "Warehouse Map":
          - /url: /inventory/warehouse-map
          - img
          - text: Warehouse Map
      - listitem:
        - link "Inventory Transfers":
          - /url: /inventory/transfer-orders
          - img
          - text: Inventory Transfers
      - listitem:
        - link "Material Handover":
          - /url: /inventory/picking
          - img
          - text: Material Handover
    - button "Supply Chain"
    - button "Sales & CRM"
    - button "Human Resources"
  - group "Module controls":
    - button "Expand all"
- main:
  - link "Stock adjustments":
    - /url: /inventory/stock-adjustments
    - img
    - text: Stock adjustments
  - heading "Stock adjustment" [level=1]
  - text: Correct stock at a specific location with a recorded reason.
  - group:
    - heading "Stock and location" [level=3]
    - text: Item
    - combobox "Item": RM-001 — Plastic Resin Type A (ABS)
    - button "Show Item"
    - text: Location
    - combobox "Location": MW-A-A-01 · Main Warehouse
    - button "Show Location"
    - text: No stock found. Choose In and select a receiving location.
    - heading "Adjustment details" [level=3]
    - text: Direction
    - combobox "Direction":
      - option "Select direction…"
      - option "In" [selected]
      - option "Out"
    - text: Quantity
    - spinbutton "Quantity": "200.095"
    - text: kg Up to 3 decimal places. Unit cost
    - spinbutton "Unit cost": "120.0000"
    - text: Filled from this location’s cost. Enter a cost when no history exists. Reason code
    - combobox "Reason code":
      - option "Select reason…"
      - option "Cycle Count Variance"
      - option "Damage"
      - option "Expiry"
      - option "Theft"
      - option "Found Stock" [selected]
      - option "System Correction"
    - text: Reason (recorded for audit)
    - textbox "Reason (recorded for audit)":
      - /placeholder: Explain why the stock needs correction.
      - text: E2E-220ad69b-6d1 opening stock RM-001
    - text: At least 10 characters. 37 / 500
    - heading "Adjustment preview" [level=3]
    - paragraph: RM-001 · MW-A-A-01
    - term: On hand
    - definition: 0 kg
    - term: Reserved
    - definition: 0 kg
    - term: Quality held
    - definition: 0 kg
    - term: Can remove
    - definition: 0 kg
    - 'meter "On hand vs reorder point: 0 of 500, below target"': On hand vs reorder point 0 / 500
    - text: Stock after posting 200.095 kg Estimated value ₱24,011.40
    - paragraph: Adjustments above ₱25,000.00 require Finance approval.
  - button "Cancel"
  - button "Review adjustment"
  - dialog "Submit stock adjustment?":
    - heading "Submit stock adjustment?" [level=2]
    - paragraph: RM-001 — Plastic Resin Type A (ABS)
    - paragraph: MW-A-A-01
    - paragraph: Increase by 200.095 kg · ₱24,011.40
    - paragraph: Stock changes when the adjustment is posted. Higher-value adjustments may require Finance approval.
    - button "Cancel"
    - button "Submit adjustment"
```

# Test source

```ts
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
  489 |         await expect(mainWarehouse).toBeInViewport();
  490 |         const optionBounds = await mainWarehouse.evaluate((option) => {
  491 |           const bounds = option.getBoundingClientRect();
  492 |           return {
  493 |             x: bounds.x,
  494 |             y: bounds.y,
  495 |             width: bounds.width,
  496 |             height: bounds.height,
  497 |             viewportWidth: window.innerWidth,
  498 |             viewportHeight: window.innerHeight,
  499 |           };
  500 |         });
  501 |         expect(optionBounds.width, 'visible location option should have a touch target').toBeGreaterThan(0);
  502 |         expect(optionBounds.height, 'visible location option should have a touch target').toBeGreaterThan(0);
  503 |         const touchX = optionBounds.x + optionBounds.width / 2;
  504 |         const touchY = optionBounds.y + optionBounds.height / 2;
  505 |         expect(touchX).toBeGreaterThanOrEqual(0);
  506 |         expect(touchX).toBeLessThan(optionBounds.viewportWidth);
  507 |         expect(touchY).toBeGreaterThanOrEqual(0);
  508 |         expect(touchY).toBeLessThan(optionBounds.viewportHeight);
  509 |         await warehouse.page.touchscreen.tap(
  510 |           touchX,
  511 |           touchY,
  512 |         );
  513 |         await expect(location).toHaveValue(locationLabel);
  514 |         await warehouse.page.getByRole('spinbutton', { name: /Quantity/ }).fill(String(material.opening_quantity));
  515 |         await warehouse.page.getByRole('spinbutton', { name: /Unit cost/ }).fill(String(material.standard_cost));
  516 |         await warehouse.page.getByRole('combobox', { name: /Reason code/ }).selectOption('found_stock');
  517 |         await warehouse.page.getByRole('textbox', { name: /Reason \(recorded for audit\)/ }).fill(reason);
  518 |         await expect(warehouse.page.getByText('Finance approval required', { exact: true })).toHaveCount(0);
  519 |         await warehouse.page.getByRole('button', { name: 'Review adjustment', exact: true }).click();
  520 |         const confirmation = warehouse.page.getByRole('dialog');
  521 |         await expect(confirmation.getByRole('heading', { name: 'Submit stock adjustment?' })).toBeVisible();
  522 |         await confirmation.getByRole('button', { name: 'Submit adjustment', exact: true }).click();
> 523 |         await expect(warehouse.page).toHaveURL('/inventory/stock-levels?view=movements');
      |                                      ^ Error: expect(page).toHaveURL(expected) failed
  524 |         await openMenuItem(warehouse.page, 'Warehouse', 'Stock Adjustments', '/inventory/stock-adjustments');
  525 |         await expect(warehouse.page.getByRole('row').filter({ hasText: reason })).toContainText('Approved by');
  526 |       }, async () => {
  527 |         const rows = await readOnlyDatabaseJson<Array<{
  528 |           status: string; direction: string; quantity: string; requester: string; approver: string;
  529 |           movement_type: string; movement_count: number; on_hand: string;
  530 |         }>>(`
  531 |           SELECT adjustment.status, adjustment.direction, adjustment.quantity::text,
  532 |                  requester.email AS requester, approver.email AS approver,
  533 |                  movement.movement_type,
  534 |                  (SELECT COUNT(*)::int FROM stock_movements duplicate
  535 |                    WHERE duplicate.reference_type = 'stock_adjustment' AND duplicate.reference_id = adjustment.id) AS movement_count,
  536 |                  COALESCE((SELECT SUM(level.quantity) FROM stock_levels level WHERE level.item_id = item.id), 0)::numeric(18, 3)::text AS on_hand
  537 |           FROM stock_adjustments adjustment
  538 |           JOIN items item ON item.id = adjustment.item_id
  539 |           JOIN users requester ON requester.id = adjustment.requested_by
  540 |           LEFT JOIN users approver ON approver.id = adjustment.approved_by
  541 |           LEFT JOIN stock_movements movement ON movement.id = adjustment.stock_movement_id
  542 |           WHERE adjustment.reason = ${sqlText(reason)} AND item.id = ${material.item_id}
  543 |         `);
  544 |         expect(rows).toEqual([{
  545 |           status: 'approved', direction: 'in', quantity: material.opening_quantity.toFixed(3),
  546 |           requester: warehouseIdentity.email, approver: warehouseIdentity.email,
  547 |           movement_type: 'adjustment_in', movement_count: 1, on_hand: material.opening_quantity.toFixed(3),
  548 |         }]);
  549 |       }, async () => {
  550 |         expect(await readOnlyDatabaseJson<Array<{ count: number }>>(`
  551 |           SELECT COUNT(*)::int AS count FROM stock_adjustments WHERE reason = ${sqlText(reason)}
  552 |         `)).toEqual([{ count: 1 }]);
  553 |       });
  554 |     }
  555 | 
  556 |     const planStep = 1 + materials.length;
  557 |     ppc = await openActor(ppcIdentity.actor);
  558 |     await step(planStep, ppc, async () => {
  559 |       await openMenuItem(ppc.page, 'Production', 'Production Work Orders', '/production/work-orders');
  560 |       await expect(ppc.page.getByRole('heading', { name: 'Work orders', exact: true })).toBeVisible();
  561 |       await ppc.page.getByRole('button', { name: 'Produce for stock', exact: true }).click();
  562 |       await expect(ppc.page.getByRole('heading', { name: 'Produce for stock', exact: true })).toBeVisible();
  563 |       const productSelect = ppc.page.getByRole('combobox', { name: 'Product', exact: true });
  564 |       const productOption = productSelect.locator('option').filter({ hasText: 'WB-001' });
  565 |       await expect(productOption).toHaveCount(1);
  566 |       const productLabel = (await productOption.innerText()).trim();
  567 |       await productSelect.selectOption({ label: productLabel });
  568 |       await ppc.page.getByRole('spinbutton', { name: 'Quantity', exact: true }).fill(String(target));
  569 |       await ppc.page.getByRole('button', { name: 'Plan production', exact: true }).click();
  570 |       await expect(ppc.page.getByRole('heading', { level: 1 })).toContainText(/WO-/);
  571 |       const heading = await ppc.page.getByRole('heading', { level: 1 }).innerText();
  572 |       const number = heading.match(/WO-[A-Z0-9-]+/i)?.[0];
  573 |       expect(number, 'generated work-order number should appear in the detail heading').toBeTruthy();
  574 |       context.values.woNumber = number;
  575 |       context.values.woRoute = new URL(ppc.page.url()).pathname;
  576 |       await expect(ppc.page.getByRole('heading', { level: 1 })).toContainText('Planned');
  577 |     }, async () => {
  578 |       const row = await assertWorkOrderState(context, {
  579 |         status: 'planned', quantity_target: target, quantity_produced: 0, quantity_good: 0,
  580 |         quantity_rejected: 0, purpose: 'stock', part_number: 'WB-001', creator_email: ppcIdentity.email,
  581 |       });
  582 |       expect(row.machine_code).toBeNull();
  583 |       expect(row.mold_code).toBeNull();
  584 |       const materialPlan = await readOnlyDatabaseJson<Array<{ lines: number; shortage_prs: number }>>(`
  585 |         SELECT (SELECT COUNT(*)::int FROM work_order_materials material WHERE material.work_order_id = ${row.id}) AS lines,
  586 |                (SELECT COUNT(*)::int FROM purchase_requests request WHERE request.stock_production_work_order_id = ${row.id}) AS shortage_prs
  587 |       `);
  588 |       expect(materialPlan).toEqual([{ lines: materials.length, shortage_prs: 0 }]);
  589 |       expect(await readOnlyDatabaseJson<Array<{ count: number }>>(`
  590 |         SELECT COUNT(*)::int AS count FROM work_orders WHERE wo_number = ${sqlText(contextValue<string>(context, 'woNumber'))}
  591 |       `)).toEqual([{ count: 1 }]);
  592 |     }, async () => {
  593 |       expect(context.values.woRoute).toMatch(/^\/production\/work-orders\//);
  594 |       expect(context.values.woNumber).toBeTruthy();
  595 |     });
  596 | 
  597 |     const confirmStep = planStep + 1;
  598 |     manager = await openActor(managerIdentity.actor, true);
  599 |     await step(confirmStep, manager, async () => {
  600 |       await openMenuItem(manager.page, 'Production', 'Production Work Orders', '/production/work-orders');
  601 |       const signalButton = manager.page.getByRole('button', { name: /View all Production Work Orders signals/ });
  602 |       await expect(signalButton).toBeVisible();
  603 |       await signalButton.click();
  604 |       const signal = manager.page.getByRole('link', { name: /Planned production work orders awaiting confirmation/ });
  605 |       await expect(signal).toBeVisible();
  606 |       await signal.click();
  607 |       await expect(manager.page).toHaveURL(/\/production\/work-orders\?.*signal=release/);
  608 |       const row = manager.page.getByRole('row').filter({ hasText: contextValue<string>(context, 'woNumber') });
  609 |       await expect(row).toHaveCount(1);
  610 |       await row.click();
  611 |       await expect(manager.page.getByRole('heading', { level: 1 })).toContainText(contextValue<string>(context, 'woNumber'));
  612 |       await expect(manager.page.getByRole('button', { name: 'Confirm', exact: true })).toBeVisible();
  613 |       await manager.page.getByRole('button', { name: 'Confirm', exact: true }).click();
  614 |       const review = manager.page.getByRole('dialog', { name: 'Review and confirm work order' });
  615 |       await expect(review).toBeVisible();
  616 |       await review.getByRole('combobox', { name: 'Number of workers', exact: true }).selectOption('1');
  617 |       await expect(review.getByText('Scheduled time', { exact: true })).toBeVisible();
  618 |       const assignManually = review.getByRole('button', { name: 'Manual assign', exact: true });
  619 |       await expect(assignManually).toBeVisible();
  620 |       await assignManually.click();
  621 |       const proposedRemovers = review.getByRole('button', { name: /^Remove / });
  622 |       const proposedCount = await proposedRemovers.count();
  623 |       expect(proposedCount).toBeLessThanOrEqual(1);
```