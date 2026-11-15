# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: production-stock.floor.spec.ts >> PROD01-HAPPY — stock work order, Warehouse handover, startup QC, production, and outgoing release
- Location: e2e-process/production-stock.floor.spec.ts:284:1

# Error details

```
Error: expect(locator).toHaveCount(expected) failed

Locator:  getByRole('dialog', { name: 'Review and confirm work order' }).getByRole('listbox', { name: 'Add worker' }).getByRole('option').filter({ hasText: 'E2E-2FDC0177057D3A5' })
Expected: 1
Received: 0
Timeout:  8000ms

Call log:
  - Expect "toHaveCount" getByRole('dialog', { name: 'Review and confirm work order' }).getByRole('listbox', { name: 'Add worker' }).getByRole('option').filter({ hasText: 'E2E-2FDC0177057D3A5' }) with timeout 8000ms
  - waiting for getByRole('dialog', { name: 'Review and confirm work order' }).getByRole('listbox', { name: 'Add worker' }).getByRole('option').filter({ hasText: 'E2E-2FDC0177057D3A5' })
    20 × locator resolved to 0 elements
       - unexpected value "0"

```

# Test source

```ts
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
  535 |       await expect(ppc.page.getByRole('heading', { name: 'Work orders', exact: true })).toBeVisible();
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
  561 |                (SELECT COUNT(*)::int FROM purchase_requests request WHERE request.stock_production_work_order_id = ${row.id}) AS shortage_prs
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
> 603 |       await expect(workerOption).toHaveCount(1);
      |                                  ^ Error: expect(locator).toHaveCount(expected) failed
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
  636 |         JOIN users recorder ON recorder.employee_id = recorder_employee.id
  637 |         JOIN work_order_crew_members crew_member ON crew_member.work_order_crew_id = crew.id
  638 |         JOIN employees member_employee ON member_employee.id = crew_member.employee_id
  639 |         JOIN users member ON member.employee_id = member_employee.id
  640 |         WHERE wo.wo_number = ${sqlText(contextValue<string>(context, 'woNumber'))}
  641 |           AND crew.ended_at IS NULL AND crew.rework_ncr_id IS NULL
  642 |         GROUP BY assigner.email, recorder.email, member.email
  643 |       `);
  644 |       expect(assignedCrew).toEqual([{
  645 |         assigned_by: managerIdentity.email,
  646 |         recorder_email: workerIdentity.email,
  647 |         member_email: workerIdentity.email,
  648 |         crew_size: 1,
  649 |       }]);
  650 |       await assertNotificationRecipients(context, 'production.crew.assigned', [workerIdentity.email.toLowerCase()]);
  651 |     });
  652 |     await ppc.context.close();
  653 | 
  654 |     const startStep = confirmStep + 1;
  655 |     await step(startStep, manager, async () => {
  656 |       await manager.page.getByRole('button', { name: 'Start', exact: true }).click();
  657 |       const dialog = manager.page.getByRole('dialog', { name: new RegExp(`Start ${contextValue<string>(context, 'woNumber')}`) });
  658 |       await expect(dialog).toBeVisible();
  659 |       await expect(dialog.getByText('Checking schedule, workers, materials, equipment and quality…')).toHaveCount(0);
  660 |       await expect(dialog.getByText('Production checks passed. Starting records actual time and issues reserved materials.', { exact: false })).toBeVisible();
  661 |       const presence = dialog.getByRole('checkbox', { name: /Confirm physically present:/ });
  662 |       if (await presence.count()) await presence.check();
  663 |       await dialog.getByRole('button', { name: 'Start production', exact: true }).click();
  664 |       await expect(manager.page.getByRole('heading', { level: 1 })).toContainText('In progress');
  665 |     }, async () => {
  666 |       const row = await assertWorkOrderState(context, { status: 'in_progress' });
  667 |       expect(row.machine_code).toBeTruthy();
  668 |       expect(row.mold_code).toBeTruthy();
  669 |       const inspection = await readOnlyDatabaseJson<Array<{ count: number; status: string; startup_trial_required: boolean }>>(`
  670 |         SELECT COUNT(*)::int AS count, MIN(inspection.status) AS status,
  671 |                bool_and(inspection.startup_trial_required) AS startup_trial_required
  672 |         FROM inspections inspection
  673 |         WHERE inspection.entity_type = 'work_order' AND inspection.entity_id = ${row.id}
  674 |           AND inspection.stage = 'in_process' AND inspection.wo_operation_id IS NULL
  675 |           AND inspection.work_order_output_id IS NULL
  676 |       `);
  677 |       expect(inspection).toEqual([{ count: 1, status: 'draft', startup_trial_required: true }]);
  678 |       const issue = await readOnlyDatabaseJson<Array<{ count: number; actor_email: string }>>(`
  679 |         SELECT COUNT(*)::int AS count, MIN(actor.email) AS actor_email
  680 |         FROM stock_movements movement
  681 |         JOIN users actor ON actor.id = movement.created_by
  682 |         WHERE movement.reference_type = 'work_order' AND movement.reference_id = ${row.id}
  683 |           AND movement.movement_type = 'material_issue'
  684 |       `);
  685 |       expect(issue).toEqual([{ count: materials.length, actor_email: managerIdentity.email }]);
  686 |     }, async () => {
  687 |       expect((await readWorkOrder(context)).status).toBe('in_progress');
  688 |       await expect(manager.page.getByText(/Material handover pending:/)).toBeVisible();
  689 |     });
  690 | 
  691 |     const handoverStep = startStep + 1;
  692 |     await step(handoverStep, warehouse, async () => {
  693 |       await openMenuItem(warehouse.page, 'Warehouse', 'Material Handover', '/inventory/picking');
  694 |       await expect(warehouse.page.getByRole('heading', { name: 'Material Handover', exact: true })).toBeVisible();
  695 |       const row = warehouse.page.getByRole('row').filter({ hasText: contextValue<string>(context, 'woNumber') });
  696 |       await expect(row).toHaveCount(1);
  697 |       await row.click();
  698 |       const details = warehouse.page.getByRole('region', { name: `Handover details for ${contextValue<string>(context, 'woNumber')}` });
  699 |       await expect(details).toBeVisible();
  700 |       const confirm = details.getByRole('button', { name: /Confirm handover \(/ });
  701 |       await expect(confirm).toBeEnabled();
  702 |       await confirm.click();
  703 |       await expect(details.getByText('Handed over', { exact: true })).toHaveCount(materials.length);
```