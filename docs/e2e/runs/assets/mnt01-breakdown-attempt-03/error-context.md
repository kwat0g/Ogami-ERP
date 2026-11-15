# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: maintenance-breakdown.process.spec.ts >> MNT01-BREAKDOWN — corrective maintenance through assignment, repair log, and completion
- Location: e2e-process/maintenance-breakdown.process.spec.ts:211:1

# Error details

```
TimeoutError: locator.selectOption: Timeout 15000ms exceeded.
Call log:
  - waiting for getByLabel('Type', { exact: true }).first()

```

# Test source

```ts
  162 |   expect(await auditTransitions(context)).toEqual(expected);
  163 | }
  164 | 
  165 | async function assertWorkRecords(context: JourneyContext, expectedStructured: number): Promise<void> {
  166 |   const number = contextValue<string>(context, 'mwoNumber');
  167 |   const rows = await readOnlyDatabaseJson<Array<{
  168 |     finding: string | null; action_taken: string | null; result: string | null; email: string;
  169 |   }>>(`
  170 |     SELECT log.finding, log.action_taken, log.result, actor.email
  171 |     FROM maintenance_logs log
  172 |     JOIN maintenance_work_orders wo ON wo.id = log.work_order_id
  173 |     JOIN users actor ON actor.id = log.logged_by
  174 |     WHERE wo.mwo_number = ${sqlText(number)}
  175 |       AND log.finding IS NOT NULL
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
> 262 |         await technician.page.getByLabel('Type', { exact: true }).nth(0).selectOption('machine');
      |                                                                          ^ TimeoutError: locator.selectOption: Timeout 15000ms exceeded.
  263 |         const target = technician.page.getByRole('combobox', { name: 'Target', exact: true });
  264 |         await target.fill('IMM');
  265 |         const firstTarget = technician.page.getByRole('option').first();
  266 |         await expect(firstTarget).toBeVisible();
  267 |         const targetLabel = await firstTarget.innerText();
  268 |         await firstTarget.click();
  269 |         context.values.machineCode = targetLabel.split(' - ')[0].trim();
  270 | 
  271 |         await technician.page.getByLabel('Type', { exact: true }).nth(1).selectOption('corrective');
  272 |         await technician.page.getByLabel('Priority', { exact: true }).selectOption('high');
  273 |         await technician.page.getByLabel('Description', { exact: true }).fill(`${context.values.runPrefix} corrective breakdown inspection and repair`);
  274 |         await technician.page.getByRole('button', { name: 'Create work order', exact: true }).click();
  275 |         await expect(technician.page.getByRole('heading', { level: 1 })).toContainText(/MWO-/);
  276 |         const heading = await technician.page.getByRole('heading', { level: 1 }).innerText();
  277 |         const number = heading.match(/MWO-[A-Z0-9-]+/i)?.[0];
  278 |         expect(number, 'work order detail should show its generated number').toBeTruthy();
  279 |         context.values.mwoNumber = number;
  280 |         await expectStatus(technician.page, 'open');
  281 |       },
  282 |       async () => {
  283 |         const row = await assertDocumentAndStatus(context, 'open', 'idle');
  284 |         expect(row.created_by_email).toBe(technicianIdentity.email);
  285 |         expect(row.assigned_email).toBeNull();
  286 |         expect(technician.page.getByRole('button', { name: 'Confirm start', exact: true })).toBeVisible();
  287 |         expect(technician.page.getByRole('button', { name: 'Record work', exact: true })).toHaveCount(0);
  288 |         expect(technician.page.getByRole('button', { name: 'Complete', exact: true })).toHaveCount(0);
  289 |       },
  290 |       async () => {
  291 |         const number = contextValue<string>(context, 'mwoNumber');
  292 |         const matches = await readOnlyDatabaseJson<Array<{ count: number }>>(`
  293 |           SELECT COUNT(*)::int AS count FROM maintenance_work_orders WHERE mwo_number = ${sqlText(number)}
  294 |         `);
  295 |         expect(matches[0].count, 'generated MWO number should be unique').toBe(1);
  296 |         expect(await notifications(context)).toEqual([{
  297 |           type: 'maintenance.assignment_needed',
  298 |           email: supervisorIdentity.email,
  299 |           title: `Maintenance assignment needed: ${number}`,
  300 |         }]);
  301 |         await assertAuditTransitions(context, []);
  302 |       },
  303 |     );
  304 | 
  305 |     await step(
  306 |       observerIdentity.actor,
  307 |       'Production manager discovers the order from the work order list',
  308 |       'Maintenance → Maintenance Work Orders → work order row',
  309 |       'open',
  310 |       observer,
  311 |       async () => {
  312 |         await openMaintenanceMenu(observer);
  313 |         const row = observer.page.getByRole('row').filter({ hasText: contextValue<string>(context, 'mwoNumber') });
  314 |         await expect(row).toHaveCount(1);
  315 |         await row.click();
  316 |         await expectStatus(observer.page, 'open');
  317 |       },
  318 |       async () => {
  319 |         await expect(observer.page.getByRole('button', { name: 'Assign worker', exact: true })).toHaveCount(0);
  320 |         await expect(observer.page.getByRole('button', { name: 'Confirm start', exact: true })).toHaveCount(0);
  321 |         await expect(observer.page.getByRole('button', { name: 'Record work', exact: true })).toHaveCount(0);
  322 |         await expect(observer.page.getByRole('button', { name: 'Complete', exact: true })).toHaveCount(0);
  323 |       },
  324 |       async () => {
  325 |         await assertDocumentAndStatus(context, 'open', 'idle');
  326 |         await assertNotificationRecipients(context, [{ type: 'maintenance.assignment_needed', email: supervisorIdentity.email }]);
  327 |       },
  328 |     );
  329 | 
  330 |     await step(
  331 |       supervisorIdentity.actor,
  332 |       'Maintenance supervisor follows assignment-needed notification and assigns the technician',
  333 |       'Notifications → assignment-needed notification → Assign worker',
  334 |       'assigned',
  335 |       supervisor,
  336 |       async () => {
  337 |         await openMaintenanceNotification(supervisor, contextValue<string>(context, 'mwoNumber'));
  338 |         await expectStatus(supervisor.page, 'open');
  339 |         await supervisor.page.getByRole('button', { name: 'Assign worker', exact: true }).click();
  340 |         await expect(supervisor.page.getByRole('heading', { name: 'Assign worker', exact: true })).toBeVisible();
  341 |         const options = supervisor.page.getByRole('radio');
  342 |         await expect(options).toHaveCount(1);
  343 |         await options.check();
  344 |         const presenceAttestation = supervisor.page.getByRole('checkbox');
  345 |         if (await presenceAttestation.count()) await presenceAttestation.check();
  346 |         await supervisor.page.getByRole('button', { name: 'Assign technician', exact: true }).click();
  347 |         await expectStatus(supervisor.page, 'assigned');
  348 |       },
  349 |       async () => {
  350 |         const row = await assertDocumentAndStatus(context, 'assigned', 'idle');
  351 |         expect(row.created_by_email).toBe(technicianIdentity.email);
  352 |         expect(row.assigned_email).toBe(technicianIdentity.email);
  353 |         await expect(observer.page.locator('[data-layout="page-header"]').getByText('Assigned', { exact: true })).toBeVisible();
  354 |         await expect(observer.page.getByRole('button', { name: 'Assign worker', exact: true })).toHaveCount(0);
  355 |       },
  356 |       async () => {
  357 |         await assertNotificationRecipients(context, [
  358 |           { type: 'maintenance.assignment_needed', email: supervisorIdentity.email },
  359 |           { type: 'maintenance.assigned', email: technicianIdentity.email },
  360 |         ]);
  361 |         await assertAuditTransitions(context, [{ status: 'assigned', email: supervisorIdentity.email }]);
  362 |       },
```