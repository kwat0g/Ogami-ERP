# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: maintenance-breakdown.process.spec.ts >> MNT01-BREAKDOWN — corrective maintenance through assignment, repair log, and completion
- Location: e2e-process/maintenance-breakdown.process.spec.ts:209:1

# Error details

```
Error: expect(locator).toHaveCount(expected) failed

Locator:  getByRole('radio')
Expected: 1
Received: 0
Timeout:  8000ms

Call log:
  - Expect "toHaveCount" getByRole('radio') with timeout 8000ms
  - waiting for getByRole('radio')
    20 × locator resolved to 0 elements
       - unexpected value "0"

```

# Test source

```ts
  243 |     action,
  244 |     verify,
  245 |     assertInvariants: async () => { await invariants(); checkDiagnostics(); },
  246 |   });
  247 | 
  248 |   try {
  249 |     await step(
  250 |       technicianIdentity.actor,
  251 |       'Create a corrective maintenance work order for a free machine',
  252 |       'Maintenance → Maintenance Work Orders → New work order → Create work order',
  253 |       'open',
  254 |       technician,
  255 |       async () => {
  256 |         await openMaintenanceMenu(technician);
  257 |         await technician.page.getByRole('button', { name: 'New work order', exact: true }).click();
  258 |         await expect(technician.page.getByRole('heading', { name: 'New maintenance work order' })).toBeVisible();
  259 | 
  260 |         const typeControls = technician.page.getByRole('combobox', { name: 'Type', exact: true });
  261 |         await expect(typeControls).toHaveCount(2);
  262 |         await expect(technician.page.getByRole('option', { name: 'Machine', exact: true })).toHaveCount(1);
  263 |         await typeControls.nth(0).selectOption('machine');
  264 |         const target = technician.page.getByRole('combobox', { name: 'Target', exact: true });
  265 |         await target.fill('IMM');
  266 |         const firstTarget = technician.page.getByRole('listbox', { name: 'Target', exact: true }).getByRole('option').first();
  267 |         await expect(firstTarget).toBeVisible();
  268 |         const targetLabel = await firstTarget.innerText();
  269 |         await firstTarget.click();
  270 |         context.values.machineCode = targetLabel.split(' - ')[0].trim();
  271 | 
  272 |         await typeControls.nth(1).selectOption('corrective');
  273 |         await technician.page.getByRole('combobox', { name: 'Priority', exact: true }).selectOption('high');
  274 |         await technician.page.getByRole('textbox', { name: /Description/ }).fill(`${context.values.runPrefix} corrective breakdown inspection and repair`);
  275 |         await technician.page.getByRole('button', { name: 'Create work order', exact: true }).click();
  276 |         await expect(technician.page.getByRole('heading', { level: 1 })).toContainText(/MWO-/);
  277 |         const heading = await technician.page.getByRole('heading', { level: 1 }).innerText();
  278 |         const number = heading.match(/MWO-[A-Z0-9-]+/i)?.[0];
  279 |         expect(number, 'work order detail should show its generated number').toBeTruthy();
  280 |         context.values.mwoNumber = number;
  281 |         await expectStatus(technician.page, 'open');
  282 |       },
  283 |       async () => {
  284 |         const row = await assertDocumentAndStatus(context, 'open', 'idle');
  285 |         expect(row.created_by_email).toBe(technicianIdentity.email);
  286 |         expect(row.assigned_email).toBeNull();
  287 |         expect(technician.page.getByRole('button', { name: 'Confirm start', exact: true })).toBeVisible();
  288 |         expect(technician.page.getByRole('button', { name: 'Record work', exact: true })).toHaveCount(0);
  289 |         expect(technician.page.getByRole('button', { name: 'Complete', exact: true })).toHaveCount(0);
  290 |       },
  291 |       async () => {
  292 |         const number = contextValue<string>(context, 'mwoNumber');
  293 |         const matches = await readOnlyDatabaseJson<Array<{ count: number }>>(`
  294 |           SELECT COUNT(*)::int AS count FROM maintenance_work_orders WHERE mwo_number = ${sqlText(number)}
  295 |         `);
  296 |         expect(matches[0].count, 'generated MWO number should be unique').toBe(1);
  297 |         expect(await notifications(context)).toEqual([{
  298 |           type: 'maintenance.assignment_needed',
  299 |           email: supervisorIdentity.email,
  300 |           title: `Maintenance assignment needed: ${number}`,
  301 |         }]);
  302 |         await assertAuditTransitions(context, []);
  303 |       },
  304 |     );
  305 | 
  306 |     await step(
  307 |       observerIdentity.actor,
  308 |       'Production manager discovers the order from the work order list',
  309 |       'Maintenance → Maintenance Work Orders → work order row',
  310 |       'open',
  311 |       observer,
  312 |       async () => {
  313 |         await openMaintenanceMenu(observer);
  314 |         const row = observer.page.getByRole('row').filter({ hasText: contextValue<string>(context, 'mwoNumber') });
  315 |         await expect(row).toHaveCount(1);
  316 |         await row.click();
  317 |         await expectStatus(observer.page, 'open');
  318 |       },
  319 |       async () => {
  320 |         await expect(observer.page.getByRole('button', { name: 'Assign worker', exact: true })).toHaveCount(0);
  321 |         await expect(observer.page.getByRole('button', { name: 'Confirm start', exact: true })).toHaveCount(0);
  322 |         await expect(observer.page.getByRole('button', { name: 'Record work', exact: true })).toHaveCount(0);
  323 |         await expect(observer.page.getByRole('button', { name: 'Complete', exact: true })).toHaveCount(0);
  324 |       },
  325 |       async () => {
  326 |         await assertDocumentAndStatus(context, 'open', 'idle');
  327 |         await assertNotificationRecipients(context, [{ type: 'maintenance.assignment_needed', email: supervisorIdentity.email }]);
  328 |       },
  329 |     );
  330 | 
  331 |     await step(
  332 |       supervisorIdentity.actor,
  333 |       'Maintenance supervisor follows assignment-needed notification and assigns the technician',
  334 |       'Notifications → assignment-needed notification → Assign worker',
  335 |       'assigned',
  336 |       supervisor,
  337 |       async () => {
  338 |         await openMaintenanceNotification(supervisor, contextValue<string>(context, 'mwoNumber'));
  339 |         await expectStatus(supervisor.page, 'open');
  340 |         await supervisor.page.getByRole('button', { name: 'Assign worker', exact: true }).click();
  341 |         await expect(supervisor.page.getByRole('heading', { name: 'Assign worker', exact: true })).toBeVisible();
  342 |         const options = supervisor.page.getByRole('radio');
> 343 |         await expect(options).toHaveCount(1);
      |                               ^ Error: expect(locator).toHaveCount(expected) failed
  344 |         await options.check();
  345 |         const presenceAttestation = supervisor.page.getByRole('checkbox');
  346 |         if (await presenceAttestation.count()) await presenceAttestation.check();
  347 |         await supervisor.page.getByRole('button', { name: 'Assign technician', exact: true }).click();
  348 |         await expectStatus(supervisor.page, 'assigned');
  349 |       },
  350 |       async () => {
  351 |         const row = await assertDocumentAndStatus(context, 'assigned', 'idle');
  352 |         expect(row.created_by_email).toBe(technicianIdentity.email);
  353 |         expect(row.assigned_email).toBe(technicianIdentity.email);
  354 |         await expectStatus(observer.page, 'assigned');
  355 |         await expect(observer.page.getByRole('button', { name: 'Assign worker', exact: true })).toHaveCount(0);
  356 |       },
  357 |       async () => {
  358 |         await assertNotificationRecipients(context, [
  359 |           { type: 'maintenance.assignment_needed', email: supervisorIdentity.email },
  360 |           { type: 'maintenance.assigned', email: technicianIdentity.email },
  361 |         ]);
  362 |         await assertAuditTransitions(context, [{ status: 'assigned', email: supervisorIdentity.email }]);
  363 |       },
  364 |     );
  365 | 
  366 |     await step(
  367 |       technicianIdentity.actor,
  368 |       'Technician follows assignment notification and starts the order',
  369 |       'Notifications → assigned notification → Confirm start',
  370 |       'in_progress',
  371 |       technician,
  372 |       async () => {
  373 |         await openMaintenanceNotification(technician, contextValue<string>(context, 'mwoNumber'));
  374 |         await expectStatus(technician.page, 'assigned');
  375 |         await technician.page.getByRole('button', { name: 'Confirm start', exact: true }).click();
  376 |         await expect(technician.page.getByRole('heading', { name: 'Confirm maintenance start', exact: true })).toBeVisible();
  377 |         await technician.page.getByRole('button', { name: 'Confirm & start', exact: true }).click();
  378 |         await expectStatus(technician.page, 'in_progress');
  379 |       },
  380 |       async () => {
  381 |         const row = await assertDocumentAndStatus(context, 'in_progress', 'maintenance');
  382 |         expect(row.assigned_email).toBe(technicianIdentity.email);
  383 |         expect(row.started_at).not.toBeNull();
  384 |         await expectStatus(observer.page, 'in_progress');
  385 |         await assertDowntime(context, false);
  386 |         await expect(technician.page.getByRole('button', { name: 'Record work', exact: true })).toBeVisible();
  387 |         await expect(technician.page.getByRole('button', { name: 'Complete', exact: true })).toHaveCount(0);
  388 |       },
  389 |       async () => {
  390 |         await assertNotificationRecipients(context, [
  391 |           { type: 'maintenance.assignment_needed', email: supervisorIdentity.email },
  392 |           { type: 'maintenance.assigned', email: technicianIdentity.email },
  393 |         ]);
  394 |         await assertAuditTransitions(context, [
  395 |           { status: 'assigned', email: supervisorIdentity.email },
  396 |           { status: 'in_progress', email: technicianIdentity.email },
  397 |         ]);
  398 |         await assertWorkRecords(context, 0);
  399 |       },
  400 |     );
  401 | 
  402 |     await step(
  403 |       technicianIdentity.actor,
  404 |       'Technician records the passing work result',
  405 |       'Work order detail → Record work → Save work record',
  406 |       'in_progress',
  407 |       technician,
  408 |       async () => {
  409 |         const finding = `${context.values.runPrefix} inspected the injection unit and isolated a failed sensor.`;
  410 |         const actionTaken = 'Replaced the sensor connector and verified the safety interlock and dry cycle.';
  411 |         context.values.finding = finding;
  412 |         context.values.actionTaken = actionTaken;
  413 |         context.values.technicianEmail = technicianIdentity.email;
  414 |         await technician.page.getByRole('button', { name: 'Record work', exact: true }).click();
  415 |         await expect(technician.page.getByRole('heading', { name: 'Record work', exact: true })).toBeVisible();
  416 |         await technician.page.getByRole('textbox', { name: /Finding/ }).fill(finding);
  417 |         await technician.page.getByRole('textbox', { name: /Action taken/ }).fill(actionTaken);
  418 |         await technician.page.getByRole('combobox', { name: /Result/ }).selectOption('passed');
  419 |         await technician.page.getByRole('button', { name: 'Save work record', exact: true }).click();
  420 |         await expect(technician.page.getByText(finding, { exact: true })).toBeVisible();
  421 |         await expect(technician.page.getByText('Passed', { exact: true })).toBeVisible();
  422 |         await expectStatus(technician.page, 'in_progress');
  423 |       },
  424 |       async () => {
  425 |         await assertDocumentAndStatus(context, 'in_progress', 'maintenance');
  426 |         await expect(observer.page.getByText(contextValue<string>(context, 'finding'), { exact: true })).toBeVisible();
  427 |         await assertWorkRecords(context, 1);
  428 |         await expect(technician.page.getByRole('button', { name: 'Complete', exact: true })).toBeVisible();
  429 |       },
  430 |       async () => {
  431 |         await assertAuditTransitions(context, [
  432 |           { status: 'assigned', email: supervisorIdentity.email },
  433 |           { status: 'in_progress', email: technicianIdentity.email },
  434 |         ]);
  435 |         await assertDowntime(context, false);
  436 |       },
  437 |     );
  438 | 
  439 |     await step(
  440 |       technicianIdentity.actor,
  441 |       'Technician completes the work order',
  442 |       'Work order detail → Complete → Confirm complete',
  443 |       'completed',
```