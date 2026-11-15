# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: production-stock.floor.spec.ts >> PROD01-HAPPY — stock work order, Warehouse handover, startup QC, production, and outgoing release
- Location: e2e-process/production-stock.floor.spec.ts:284:1

# Error details

```
Error: expect(received).toBeTruthy()

Received: null
```

# Test source

```ts
  250 |   }, { message: `${type} should reach exactly its authorized recipients`, timeout: 15_000 }).toEqual(expectedEmails);
  251 | }
  252 | 
  253 | async function setPassingEvidence(session: ActorPage, sampleSize: number): Promise<void> {
  254 |   const page = session.page;
  255 |   const checklist = page.getByRole('region', { name: 'Inspection checklist' });
  256 |   await expect(checklist).toBeVisible();
  257 |   const coverage = checklist.getByRole('spinbutton', { name: 'Inspected pieces', exact: true });
  258 |   if (await coverage.count()) {
  259 |     await coverage.fill(String(sampleSize));
  260 |     await checklist.getByRole('spinbutton', { name: 'Defective pieces found', exact: true }).fill('0');
  261 |     const confirmation = checklist.getByRole('checkbox', { name: /I confirm all required checks on the planned sample were completed/ });
  262 |     if (await confirmation.count()) await confirmation.check();
  263 |   }
  264 |   const pass = checklist.getByRole('button', { name: 'PASS Visual appearance', exact: true });
  265 |   await expect(pass, 'the current WB-001 specification should present its visual criterion').toBeVisible();
  266 |   await pass.click();
  267 |   await checklist.getByRole('button', { name: 'Submit inspection', exact: true }).click();
  268 | }
  269 | 
  270 | async function currentStockByItem(itemIds: number[]): Promise<Array<{ item_id: number; on_hand: string; ledger_on_hand: string }>> {
  271 |   if (itemIds.length === 0) return [];
  272 |   const ids = itemIds.join(',');
  273 |   return readOnlyDatabaseJson<Array<{ item_id: number; on_hand: string; ledger_on_hand: string }>>(`
  274 |     SELECT item.id AS item_id,
  275 |            COALESCE((SELECT SUM(level.quantity) FROM stock_levels level WHERE level.item_id = item.id), 0)::numeric(18, 3)::text AS on_hand,
  276 |            (COALESCE((SELECT SUM(movement.quantity) FROM stock_movements movement
  277 |              WHERE movement.item_id = item.id AND movement.to_location_id IS NOT NULL), 0)
  278 |            - COALESCE((SELECT SUM(movement.quantity) FROM stock_movements movement
  279 |              WHERE movement.item_id = item.id AND movement.from_location_id IS NOT NULL), 0))::numeric(18, 3)::text AS ledger_on_hand
  280 |     FROM items item WHERE item.id IN (${ids}) ORDER BY item.id
  281 |   `);
  282 | }
  283 | 
  284 | test('PROD01-HAPPY — stock work order, Warehouse handover, startup QC, production, and outgoing release', async ({ browser, request }, testInfo) => {
  285 |   test.setTimeout(12 * 60 * 1000);
  286 |   const actors = await loadActors();
  287 |   for (const actor of runActors) actorByName(actors, actor);
  288 |   const hrIdentity = actorByName(actors, 'hr_officer');
  289 |   const warehouseIdentity = actorByName(actors, 'warehouse_staff');
  290 |   const ppcIdentity = actorByName(actors, 'ppc_head');
  291 |   const managerIdentity = actorByName(actors, 'production_manager');
  292 |   const qcIdentity = actorByName(actors, 'qc_inspector');
  293 |   const workerIdentity = actorByName(actors, 'employee');
  294 |   const workerProfileRows = await readOnlyDatabaseJson<Array<{
  295 |     employee_no: string; first_name: string; middle_name: string | null; last_name: string; suffix: string | null;
  296 |   }>>(`
  297 |     SELECT employee.employee_no, employee.first_name, employee.middle_name, employee.last_name, employee.suffix
  298 |     FROM employees employee JOIN users account ON account.employee_id = employee.id
  299 |     WHERE account.email = ${sqlText(workerIdentity.email)} AND employee.status = 'active'
  300 |   `);
  301 |   expect(workerProfileRows).toHaveLength(1);
  302 |   const workerProfileRow = workerProfileRows[0];
  303 |   const workerProfile = {
  304 |     employee_no: workerProfileRow.employee_no,
  305 |     full_name: [
  306 |       workerProfileRow.first_name,
  307 |       workerProfileRow.middle_name ? `${workerProfileRow.middle_name.slice(0, 1)}.` : null,
  308 |       workerProfileRow.last_name,
  309 |       workerProfileRow.suffix,
  310 |     ].filter(Boolean).join(' '),
  311 |   };
  312 |   const context = createJourneyContext(journeyId);
  313 |   const instant = currentInstant();
  314 |   const plannedShift = shiftPlan(instant);
  315 |   const baseline = await readOnlyDatabaseJson<{
  316 |     materials: Material[];
  317 |     sample_size: number;
  318 |     finished_item_id: number;
  319 |     setup_minutes: number;
  320 |   }[]>(`
  321 |     SELECT json_agg(json_build_object(
  322 |       'item_id', item.id, 'code', item.code, 'name', item.name,
  323 |       'unit_of_measure', item.unit_of_measure, 'standard_cost', item.standard_cost::text,
  324 |       'safety_stock', item.safety_stock::text,
  325 |       'quantity_per_unit', bom_item.quantity_per_unit::text,
  326 |       'waste_factor', bom_item.waste_factor::text
  327 |     ) ORDER BY bom_item.sort_order) AS materials,
  328 |     (SELECT COALESCE((setting.value #>> '{}')::int, 5) FROM settings setting
  329 |       WHERE setting.key = 'quality.in_process.sample_size') AS sample_size,
  330 |     (SELECT finished.id FROM items finished
  331 |       JOIN products product ON product.part_number = finished.code
  332 |       WHERE product.part_number = 'WB-001' AND finished.item_type = 'finished_good'
  333 |       ORDER BY finished.id LIMIT 1) AS finished_item_id,
  334 |     (SELECT mold.setup_time_minutes FROM product_routings routing
  335 |       JOIN routing_operations operation ON operation.routing_id = routing.id
  336 |       JOIN molds mold ON mold.id = operation.mold_id
  337 |       JOIN products product ON product.id = routing.product_id
  338 |       WHERE product.part_number = 'WB-001' AND routing.is_active = true
  339 |       ORDER BY operation.sequence LIMIT 1) AS setup_minutes
  340 |     FROM products product
  341 |     JOIN bill_of_materials bom ON bom.product_id = product.id AND bom.is_active = true
  342 |     JOIN bom_items bom_item ON bom_item.bom_id = bom.id
  343 |     JOIN items item ON item.id = bom_item.item_id
  344 |     WHERE product.part_number = 'WB-001'
  345 |   `);
  346 |   expect(baseline).toHaveLength(1);
  347 |   const fixture = baseline[0];
  348 |   expect(fixture.materials.length, 'active WB-001 BOM should have component rows').toBeGreaterThan(0);
  349 |   expect(fixture.sample_size).toBeGreaterThan(0);
> 350 |   expect(fixture.finished_item_id).toBeTruthy();
      |                                    ^ Error: expect(received).toBeTruthy()
  351 |   expect(fixture.setup_minutes).toBeGreaterThan(0);
  352 |   const target = fixture.sample_size + 1;
  353 |   context.values.product = 'WB-001';
  354 |   context.values.target = target;
  355 |   context.values.sampleSize = fixture.sample_size;
  356 |   context.values.shiftName = plannedShift.name;
  357 |   context.values.shiftEffectiveDate = plannedShift.effectiveFrom;
  358 |   context.values.instant = instant;
  359 | 
  360 |   const materials = fixture.materials.map((material) => {
  361 |     const gross = target * Number(material.quantity_per_unit) * (1 + Number(material.waste_factor) / 100)
  362 |       + Number(material.safety_stock);
  363 |     const quantity = material.unit_of_measure.toLowerCase() === 'pcs'
  364 |       ? Math.ceil(gross)
  365 |       : Math.ceil(gross * 1000) / 1000;
  366 |     return { ...material, opening_quantity: quantity };
  367 |   });
  368 |   context.values.materials = materials;
  369 |   const baselineBalances = await currentStockByItem(materials.map((material) => material.item_id));
  370 |   expect(baselineBalances.every((row) => Number(row.on_hand) === 0), 'the fresh E2E baseline should have no opening transactional stock').toBe(true);
  371 | 
  372 |   const plannedSteps = [
  373 |     { name: 'HR assigns Production shift coverage to the named E2E operator', actor: hrIdentity.actor, uiPath: 'Human Resources → Attendance & DTR → Shifts → Bulk assign', expectedStatus: 'shift active for the assigned Production employee' },
  374 |     ...materials.map((material) => ({ name: `Warehouse posts baseline ${material.code} stock through Stock Adjustments`, actor: warehouseIdentity.actor, uiPath: 'Warehouse → Stock Adjustments → New adjustment → Submit', expectedStatus: 'approved; stock movement posted' })),
  375 |     { name: 'PPC creates a stock production work order from the eligible WB-001 product', actor: ppcIdentity.actor, uiPath: 'Production → Production Work Orders → Produce for stock → Plan production', expectedStatus: 'planned' },
  376 |     { name: 'Production Manager finds the planned WO through the awaiting-confirmation signal, assigns the operator, and confirms its schedule', actor: managerIdentity.actor, uiPath: 'Production → Production Work Orders signals → Planned work orders → Confirm → crew and schedule', expectedStatus: 'confirmed; named operator assigned' },
  377 |     { name: 'Production Manager starts the confirmed work order after readiness and physical-presence checks', actor: managerIdentity.actor, uiPath: 'Work order detail → Start → Start production', expectedStatus: 'in_progress; startup QC required' },
  378 |     { name: 'Warehouse finds the issued materials in Material Handover and confirms receipt', actor: warehouseIdentity.actor, uiPath: 'Warehouse → Material Handover → work-order row → Confirm handover', expectedStatus: 'all issued material lines handed over' },
  379 |     { name: 'Assigned Production employee follows the crew notice and records the startup trial in My Work Orders', actor: workerIdentity.actor, uiPath: 'Notifications → assigned WO → My Work Orders → Samples ready for QC', expectedStatus: 'trial captured; awaiting QC' },
  380 |     { name: 'QC follows the in-process notification and passes the startup sample', actor: qcIdentity.actor, uiPath: 'Notifications → In-process QC required → inspection → PASS → Submit inspection', expectedStatus: 'passed; trial disposition and FG receipt settled' },
  381 |     { name: 'Production Manager records the seeded routing setup', actor: managerIdentity.actor, uiPath: 'Production Work Order → Operations → Setup → End setup', expectedStatus: 'setup recorded' },
  382 |     { name: 'Production Manager starts the routing operation after QC release', actor: managerIdentity.actor, uiPath: 'Production Work Order → Operations → Start', expectedStatus: 'in_progress' },
  383 |     { name: 'Production Manager records the remaining operation quantity', actor: managerIdentity.actor, uiPath: 'Production Work Order → Operations → Output → Record output', expectedStatus: 'routing quantity reconciles to normal output' },
  384 |     { name: 'Production Manager completes the routing operation', actor: managerIdentity.actor, uiPath: 'Production Work Order → Operations → Complete', expectedStatus: 'completed' },
  385 |     { name: 'Assigned Production employee records the remaining good output in My Work Orders', actor: workerIdentity.actor, uiPath: 'My Work Orders → assigned WO → Record output', expectedStatus: 'target good quantity and finished-goods receipt settled' },
  386 |     { name: 'Production Manager completes the WO after its production and ledger prerequisites settle', actor: managerIdentity.actor, uiPath: 'Production Work Order → Complete → Confirm', expectedStatus: 'completed; outgoing QC queued for each good batch' },
  387 |     { name: 'QC inspects the startup trial output batch and submits its passing result', actor: qcIdentity.actor, uiPath: 'Notifications → Outgoing QC required → Inspections → trial batch → PASS → Submit', expectedStatus: 'awaiting_review' },
  388 |     { name: 'Production Manager follows the checker notice and approves the trial-batch result', actor: managerIdentity.actor, uiPath: 'Notifications → inspection-awaiting-review → Approve pass', expectedStatus: 'passed; next batch still held' },
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
```