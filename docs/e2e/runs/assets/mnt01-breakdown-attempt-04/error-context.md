# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: maintenance-breakdown.process.spec.ts >> MNT01-BREAKDOWN — corrective maintenance through assignment, repair log, and completion
- Location: e2e-process/maintenance-breakdown.process.spec.ts:211:1

# Error details

```
TimeoutError: locator.fill: Timeout 15000ms exceeded.
Call log:
  - waiting for getByLabel('Description', { exact: true })

```

# Test source

```ts
  176 |     ORDER BY log.id
  177 |   `);
  178 |   expect(rows).toHaveLength(expectedStructured);
  179 |   if (expectedStructured > 0) {
  180 |     expect(rows).toEqual([{
  181 |       finding: contextValue<string>(context, 'finding'),
  182 |       action_taken: contextValue<string>(context, 'actionTaken'),
  183 |       result: 'passed',
  184 |       email: contextValue<string>(context, 'technicianEmail'),
  185 |     }]);
  186 |   }
  187 | }
  188 | 
  189 | async function assertDowntime(context: JourneyContext, ended: boolean): Promise<void> {
  190 |   const number = contextValue<string>(context, 'mwoNumber');
  191 |   const rows = await readOnlyDatabaseJson<Array<{
  192 |     category: string; end_time: string | null; duration_minutes: number | null;
  193 |   }>>(`
  194 |     SELECT downtime.category, downtime.end_time, downtime.duration_minutes
  195 |     FROM machine_downtimes downtime
  196 |     JOIN maintenance_work_orders wo ON wo.id = downtime.maintenance_order_id
  197 |     WHERE wo.mwo_number = ${sqlText(number)}
  198 |     ORDER BY downtime.id
  199 |   `);
  200 |   expect(rows).toHaveLength(1);
  201 |   expect(rows[0].category).toBe('planned_maintenance');
  202 |   if (ended) {
  203 |     expect(rows[0].end_time).not.toBeNull();
  204 |     expect(rows[0].duration_minutes).not.toBeNull();
  205 |   } else {
  206 |     expect(rows[0].end_time).toBeNull();
  207 |     expect(rows[0].duration_minutes).toBeNull();
  208 |   }
  209 | }
  210 | 
  211 | test('MNT01-BREAKDOWN — corrective maintenance through assignment, repair log, and completion', async ({ browser }, testInfo) => {
  212 |   test.setTimeout(6 * 60 * 1000);
  213 |   const actors = await loadActors();
  214 |   const technicianIdentity = actorByName(actors, 'maintenance_tech');
  215 |   const supervisorIdentity = actorByName(actors, 'maintenance_supervisor');
  216 |   const observerIdentity = actorByName(actors, 'production_manager');
  217 |   const context = createJourneyContext('MNT01-BREAKDOWN');
  218 |   const actorSessions: ActorPage[] = [];
  219 | 
  220 |   const openActor = async (actor: string): Promise<ActorPage> => {
  221 |     const session = await actorPage(browser, actor);
  222 |     actorSessions.push(session);
  223 |     await openSession(session);
  224 |     return session;
  225 |   };
  226 | 
  227 |   const technician = await openActor(technicianIdentity.actor);
  228 |   const supervisor = await openActor(supervisorIdentity.actor);
  229 |   const observer = await openActor(observerIdentity.actor);
  230 | 
  231 |   const checkDiagnostics = () => {
  232 |     for (const session of actorSessions) session.diagnostics.assertClean(context.journeyId);
  233 |   };
  234 |   const step = async (
  235 |     actor: string,
  236 |     name: string,
  237 |     uiPath: string,
  238 |     expectedStatus: string,
  239 |     session: ActorPage,
  240 |     action: () => Promise<void>,
  241 |     verify: () => Promise<void>,
  242 |     invariants: () => Promise<void>,
  243 |   ) => runProcessStep({
  244 |     name, actor, uiPath, expectedStatus, page: session.page, plannedSteps, context, testInfo,
  245 |     action,
  246 |     verify,
  247 |     assertInvariants: async () => { await invariants(); checkDiagnostics(); },
  248 |   });
  249 | 
  250 |   try {
  251 |     await step(
  252 |       technicianIdentity.actor,
  253 |       'Create a corrective maintenance work order for a free machine',
  254 |       'Maintenance → Maintenance Work Orders → New work order → Create work order',
  255 |       'open',
  256 |       technician,
  257 |       async () => {
  258 |         await openMaintenanceMenu(technician);
  259 |         await technician.page.getByRole('button', { name: 'New work order', exact: true }).click();
  260 |         await expect(technician.page.getByRole('heading', { name: 'New maintenance work order' })).toBeVisible();
  261 | 
  262 |         const typeControls = technician.page.getByRole('combobox', { name: 'Type', exact: true });
  263 |         await expect(typeControls).toHaveCount(2);
  264 |         await expect(technician.page.getByRole('option', { name: 'Machine', exact: true })).toHaveCount(1);
  265 |         await typeControls.nth(0).selectOption('machine');
  266 |         const target = technician.page.getByRole('combobox', { name: 'Target', exact: true });
  267 |         await target.fill('IMM');
  268 |         const firstTarget = technician.page.getByRole('listbox', { name: 'Target', exact: true }).getByRole('option').first();
  269 |         await expect(firstTarget).toBeVisible();
  270 |         const targetLabel = await firstTarget.innerText();
  271 |         await firstTarget.click();
  272 |         context.values.machineCode = targetLabel.split(' - ')[0].trim();
  273 | 
  274 |         await typeControls.nth(1).selectOption('corrective');
  275 |         await technician.page.getByRole('combobox', { name: 'Priority', exact: true }).selectOption('high');
> 276 |         await technician.page.getByLabel('Description', { exact: true }).fill(`${context.values.runPrefix} corrective breakdown inspection and repair`);
      |                                                                          ^ TimeoutError: locator.fill: Timeout 15000ms exceeded.
  277 |         await technician.page.getByRole('button', { name: 'Create work order', exact: true }).click();
  278 |         await expect(technician.page.getByRole('heading', { level: 1 })).toContainText(/MWO-/);
  279 |         const heading = await technician.page.getByRole('heading', { level: 1 }).innerText();
  280 |         const number = heading.match(/MWO-[A-Z0-9-]+/i)?.[0];
  281 |         expect(number, 'work order detail should show its generated number').toBeTruthy();
  282 |         context.values.mwoNumber = number;
  283 |         await expectStatus(technician.page, 'open');
  284 |       },
  285 |       async () => {
  286 |         const row = await assertDocumentAndStatus(context, 'open', 'idle');
  287 |         expect(row.created_by_email).toBe(technicianIdentity.email);
  288 |         expect(row.assigned_email).toBeNull();
  289 |         expect(technician.page.getByRole('button', { name: 'Confirm start', exact: true })).toBeVisible();
  290 |         expect(technician.page.getByRole('button', { name: 'Record work', exact: true })).toHaveCount(0);
  291 |         expect(technician.page.getByRole('button', { name: 'Complete', exact: true })).toHaveCount(0);
  292 |       },
  293 |       async () => {
  294 |         const number = contextValue<string>(context, 'mwoNumber');
  295 |         const matches = await readOnlyDatabaseJson<Array<{ count: number }>>(`
  296 |           SELECT COUNT(*)::int AS count FROM maintenance_work_orders WHERE mwo_number = ${sqlText(number)}
  297 |         `);
  298 |         expect(matches[0].count, 'generated MWO number should be unique').toBe(1);
  299 |         expect(await notifications(context)).toEqual([{
  300 |           type: 'maintenance.assignment_needed',
  301 |           email: supervisorIdentity.email,
  302 |           title: `Maintenance assignment needed: ${number}`,
  303 |         }]);
  304 |         await assertAuditTransitions(context, []);
  305 |       },
  306 |     );
  307 | 
  308 |     await step(
  309 |       observerIdentity.actor,
  310 |       'Production manager discovers the order from the work order list',
  311 |       'Maintenance → Maintenance Work Orders → work order row',
  312 |       'open',
  313 |       observer,
  314 |       async () => {
  315 |         await openMaintenanceMenu(observer);
  316 |         const row = observer.page.getByRole('row').filter({ hasText: contextValue<string>(context, 'mwoNumber') });
  317 |         await expect(row).toHaveCount(1);
  318 |         await row.click();
  319 |         await expectStatus(observer.page, 'open');
  320 |       },
  321 |       async () => {
  322 |         await expect(observer.page.getByRole('button', { name: 'Assign worker', exact: true })).toHaveCount(0);
  323 |         await expect(observer.page.getByRole('button', { name: 'Confirm start', exact: true })).toHaveCount(0);
  324 |         await expect(observer.page.getByRole('button', { name: 'Record work', exact: true })).toHaveCount(0);
  325 |         await expect(observer.page.getByRole('button', { name: 'Complete', exact: true })).toHaveCount(0);
  326 |       },
  327 |       async () => {
  328 |         await assertDocumentAndStatus(context, 'open', 'idle');
  329 |         await assertNotificationRecipients(context, [{ type: 'maintenance.assignment_needed', email: supervisorIdentity.email }]);
  330 |       },
  331 |     );
  332 | 
  333 |     await step(
  334 |       supervisorIdentity.actor,
  335 |       'Maintenance supervisor follows assignment-needed notification and assigns the technician',
  336 |       'Notifications → assignment-needed notification → Assign worker',
  337 |       'assigned',
  338 |       supervisor,
  339 |       async () => {
  340 |         await openMaintenanceNotification(supervisor, contextValue<string>(context, 'mwoNumber'));
  341 |         await expectStatus(supervisor.page, 'open');
  342 |         await supervisor.page.getByRole('button', { name: 'Assign worker', exact: true }).click();
  343 |         await expect(supervisor.page.getByRole('heading', { name: 'Assign worker', exact: true })).toBeVisible();
  344 |         const options = supervisor.page.getByRole('radio');
  345 |         await expect(options).toHaveCount(1);
  346 |         await options.check();
  347 |         const presenceAttestation = supervisor.page.getByRole('checkbox');
  348 |         if (await presenceAttestation.count()) await presenceAttestation.check();
  349 |         await supervisor.page.getByRole('button', { name: 'Assign technician', exact: true }).click();
  350 |         await expectStatus(supervisor.page, 'assigned');
  351 |       },
  352 |       async () => {
  353 |         const row = await assertDocumentAndStatus(context, 'assigned', 'idle');
  354 |         expect(row.created_by_email).toBe(technicianIdentity.email);
  355 |         expect(row.assigned_email).toBe(technicianIdentity.email);
  356 |         await expect(observer.page.locator('[data-layout="page-header"]').getByText('Assigned', { exact: true })).toBeVisible();
  357 |         await expect(observer.page.getByRole('button', { name: 'Assign worker', exact: true })).toHaveCount(0);
  358 |       },
  359 |       async () => {
  360 |         await assertNotificationRecipients(context, [
  361 |           { type: 'maintenance.assignment_needed', email: supervisorIdentity.email },
  362 |           { type: 'maintenance.assigned', email: technicianIdentity.email },
  363 |         ]);
  364 |         await assertAuditTransitions(context, [{ status: 'assigned', email: supervisorIdentity.email }]);
  365 |       },
  366 |     );
  367 | 
  368 |     await step(
  369 |       technicianIdentity.actor,
  370 |       'Technician follows assignment notification and starts the order',
  371 |       'Notifications → assigned notification → Confirm start',
  372 |       'in_progress',
  373 |       technician,
  374 |       async () => {
  375 |         await openMaintenanceNotification(technician, contextValue<string>(context, 'mwoNumber'));
  376 |         await expectStatus(technician.page, 'assigned');
```