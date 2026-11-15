# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: maintenance-breakdown.process.spec.ts >> MNT01-BREAKDOWN — corrective maintenance through assignment, repair log, and completion
- Location: e2e-process/maintenance-breakdown.process.spec.ts:224:1

# Error details

```
TimeoutError: locator.selectOption: Timeout 15000ms exceeded.
Call log:
  - waiting for getByRole('combobox', { name: 'Department', exact: true })
    - locator resolved to <select required="" id="select-r18" aria-invalid="false" name="department_id" class="flex-1 min-w-0 bg-transparent appearance-none outline-none cursor-pointer disabled:cursor-not-allowed disabled:opacity-60 pl-3 pr-8 text-md">…</select>
  - attempting select option action
    2 × waiting for element to be visible and enabled
      - did not find some options
    - retrying select option action
    - waiting 20ms
    2 × waiting for element to be visible and enabled
      - did not find some options
    - retrying select option action
      - waiting 100ms
    27 × waiting for element to be visible and enabled
       - did not find some options
     - retrying select option action
       - waiting 500ms

```

# Test source

```ts
  179 |   const number = contextValue<string>(context, 'mwoNumber');
  180 |   const rows = await readOnlyDatabaseJson<Array<{
  181 |     finding: string | null; action_taken: string | null; result: string | null; email: string;
  182 |   }>>(`
  183 |     SELECT log.finding, log.action_taken, log.result, actor.email
  184 |     FROM maintenance_logs log
  185 |     JOIN maintenance_work_orders wo ON wo.id = log.work_order_id
  186 |     JOIN users actor ON actor.id = log.logged_by
  187 |     WHERE wo.mwo_number = ${sqlText(number)}
  188 |       AND log.finding IS NOT NULL
  189 |     ORDER BY log.id
  190 |   `);
  191 |   expect(rows).toHaveLength(expectedStructured);
  192 |   if (expectedStructured > 0) {
  193 |     expect(rows).toEqual([{
  194 |       finding: contextValue<string>(context, 'finding'),
  195 |       action_taken: contextValue<string>(context, 'actionTaken'),
  196 |       result: 'passed',
  197 |       email: contextValue<string>(context, 'technicianEmail'),
  198 |     }]);
  199 |   }
  200 | }
  201 | 
  202 | async function assertDowntime(context: JourneyContext, ended: boolean): Promise<void> {
  203 |   const number = contextValue<string>(context, 'mwoNumber');
  204 |   const rows = await readOnlyDatabaseJson<Array<{
  205 |     category: string; end_time: string | null; duration_minutes: number | null;
  206 |   }>>(`
  207 |     SELECT downtime.category, downtime.end_time, downtime.duration_minutes
  208 |     FROM machine_downtimes downtime
  209 |     JOIN maintenance_work_orders wo ON wo.id = downtime.maintenance_order_id
  210 |     WHERE wo.mwo_number = ${sqlText(number)}
  211 |     ORDER BY downtime.id
  212 |   `);
  213 |   expect(rows).toHaveLength(1);
  214 |   expect(rows[0].category).toBe('planned_maintenance');
  215 |   if (ended) {
  216 |     expect(rows[0].end_time).not.toBeNull();
  217 |     expect(rows[0].duration_minutes).not.toBeNull();
  218 |   } else {
  219 |     expect(rows[0].end_time).toBeNull();
  220 |     expect(rows[0].duration_minutes).toBeNull();
  221 |   }
  222 | }
  223 | 
  224 | test('MNT01-BREAKDOWN — corrective maintenance through assignment, repair log, and completion', async ({ browser }, testInfo) => {
  225 |   test.setTimeout(6 * 60 * 1000);
  226 |   const actors = await loadActors();
  227 |   const hrIdentity = actorByName(actors, 'hr_officer');
  228 |   const technicianIdentity = actorByName(actors, 'maintenance_tech');
  229 |   const supervisorIdentity = actorByName(actors, 'maintenance_supervisor');
  230 |   const observerIdentity = actorByName(actors, 'production_manager');
  231 |   const context = createJourneyContext('MNT01-BREAKDOWN');
  232 |   const actorSessions: ActorPage[] = [];
  233 | 
  234 |   const openActor = async (actor: string): Promise<ActorPage> => {
  235 |     const session = await actorPage(browser, actor, { timezoneId: 'Asia/Manila' });
  236 |     actorSessions.push(session);
  237 |     await openSession(session);
  238 |     return session;
  239 |   };
  240 | 
  241 |   const hr = await openActor(hrIdentity.actor);
  242 |   const technician = await openActor(technicianIdentity.actor);
  243 |   const supervisor = await openActor(supervisorIdentity.actor);
  244 |   const observer = await openActor(observerIdentity.actor);
  245 | 
  246 |   const checkDiagnostics = () => {
  247 |     for (const session of actorSessions) session.diagnostics.assertClean(context.journeyId);
  248 |   };
  249 |   const step = async (
  250 |     actor: string,
  251 |     name: string,
  252 |     uiPath: string,
  253 |     expectedStatus: string,
  254 |     session: ActorPage,
  255 |     action: () => Promise<void>,
  256 |     verify: () => Promise<void>,
  257 |     invariants: () => Promise<void>,
  258 |   ) => runProcessStep({
  259 |     name, actor, uiPath, expectedStatus, page: session.page, plannedSteps, context, testInfo,
  260 |     action,
  261 |     verify,
  262 |     assertInvariants: async () => { await invariants(); checkDiagnostics(); },
  263 |   });
  264 | 
  265 |   try {
  266 |     await step(
  267 |       hrIdentity.actor,
  268 |       'HR configures Maintenance day-shift coverage for the work period',
  269 |       'Human Resources → Attendance & DTR → Shifts → Bulk assign',
  270 |       'configured',
  271 |       hr,
  272 |       async () => {
  273 |         await openAttendance(hr);
  274 |         await hr.page.getByRole('button', { name: 'Shifts', exact: true }).click();
  275 |         await expect(hr.page.getByRole('heading', { name: 'Shifts', exact: true })).toBeVisible();
  276 |         await hr.page.getByRole('button', { name: 'Bulk assign', exact: true }).click();
  277 |         await expect(hr.page.getByRole('heading', { name: 'Bulk assign shift', exact: true })).toBeVisible();
  278 | 
> 279 |         await hr.page.getByRole('combobox', { name: 'Department', exact: true }).selectOption({ label: 'Maintenance' });
      |                                                                                  ^ TimeoutError: locator.selectOption: Timeout 15000ms exceeded.
  280 |         await hr.page.getByRole('combobox', { name: 'Shift', exact: true }).selectOption({ label: 'Day Shift' });
  281 |         const todayManila = new Intl.DateTimeFormat('en-CA', {
  282 |           timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit',
  283 |         }).format(new Date());
  284 |         await hr.page.getByRole('textbox', { name: 'Effective from', exact: true }).fill(todayManila);
  285 |         for (const day of ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']) {
  286 |           await hr.page.getByRole('checkbox', { name: day, exact: true }).check();
  287 |         }
  288 |         await hr.page.getByRole('button', { name: 'Assign shift', exact: true }).click();
  289 |         await expect(hr.page.getByRole('heading', { name: 'Shifts', exact: true })).toBeVisible();
  290 |         await expect(hr.page.getByText(/Assigned shift to \d+ employees\./)).toBeVisible();
  291 |       },
  292 |       async () => {
  293 |         const assignment = await readOnlyDatabaseJson<Array<{
  294 |           id: number; employee_email: string; shift_name: string; effective_date: string; weekdays_match: boolean;
  295 |         }>>(`
  296 |           SELECT assignment.id, employee.email AS employee_email, shift.name AS shift_name,
  297 |                  assignment.effective_date::text,
  298 |                  assignment.working_days::jsonb = '[1,2,3,4,5]'::jsonb AS weekdays_match
  299 |           FROM employee_shift_assignments assignment
  300 |           JOIN employees employee ON employee.id = assignment.employee_id
  301 |           JOIN shifts shift ON shift.id = assignment.shift_id
  302 |           WHERE employee.email = ${sqlText(technicianIdentity.email)}
  303 |             AND shift.name = 'Day Shift'
  304 |             AND assignment.effective_date <= (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date
  305 |             AND (assignment.end_date IS NULL OR assignment.end_date >= (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date)
  306 |         `);
  307 |         expect(assignment).toHaveLength(1);
  308 |         expect(assignment[0].weekdays_match).toBe(true);
  309 |         context.values.shiftAssignmentId = assignment[0].id;
  310 |         context.values.shiftAssignmentEmployee = assignment[0].employee_email;
  311 | 
  312 |         const audit = await readOnlyDatabaseJson<Array<{ action: string; email: string }>>(`
  313 |           SELECT log.action, actor.email
  314 |           FROM audit_logs log
  315 |           JOIN users actor ON actor.id = log.user_id
  316 |           WHERE log.model_type ILIKE '%EmployeeShiftAssignment'
  317 |             AND log.model_id = ${assignment[0].id}
  318 |             AND log.action = 'created'
  319 |         `);
  320 |         expect(audit).toEqual([{ action: 'created', email: hrIdentity.email }]);
  321 |       },
  322 |       async () => {
  323 |         expect(context.values.shiftAssignmentEmployee).toBe(technicianIdentity.email);
  324 |         expect(context.values.shiftAssignmentId).toBeTruthy();
  325 |       },
  326 |     );
  327 | 
  328 |     await step(
  329 |       technicianIdentity.actor,
  330 |       'Create a corrective maintenance work order for a free machine',
  331 |       'Maintenance → Maintenance Work Orders → New work order → Create work order',
  332 |       'open',
  333 |       technician,
  334 |       async () => {
  335 |         await openMaintenanceMenu(technician);
  336 |         await technician.page.getByRole('button', { name: 'New work order', exact: true }).click();
  337 |         await expect(technician.page.getByRole('heading', { name: 'New maintenance work order' })).toBeVisible();
  338 | 
  339 |         const typeControls = technician.page.getByRole('combobox', { name: 'Type', exact: true });
  340 |         await expect(typeControls).toHaveCount(2);
  341 |         await expect(technician.page.getByRole('option', { name: 'Machine', exact: true })).toHaveCount(1);
  342 |         await typeControls.nth(0).selectOption('machine');
  343 |         const target = technician.page.getByRole('combobox', { name: 'Target', exact: true });
  344 |         await target.fill('IMM');
  345 |         const firstTarget = technician.page.getByRole('listbox', { name: 'Target', exact: true }).getByRole('option').first();
  346 |         await expect(firstTarget).toBeVisible();
  347 |         const targetLabel = await firstTarget.innerText();
  348 |         await firstTarget.click();
  349 |         context.values.machineCode = targetLabel.split(' - ')[0].trim();
  350 | 
  351 |         await typeControls.nth(1).selectOption('corrective');
  352 |         await technician.page.getByRole('combobox', { name: 'Priority', exact: true }).selectOption('high');
  353 |         await technician.page.getByRole('textbox', { name: /Description/ }).fill(`${context.values.runPrefix} corrective breakdown inspection and repair`);
  354 |         await technician.page.getByRole('button', { name: 'Create work order', exact: true }).click();
  355 |         await expect(technician.page.getByRole('heading', { level: 1 })).toContainText(/MWO-/);
  356 |         const heading = await technician.page.getByRole('heading', { level: 1 }).innerText();
  357 |         const number = heading.match(/MWO-[A-Z0-9-]+/i)?.[0];
  358 |         expect(number, 'work order detail should show its generated number').toBeTruthy();
  359 |         context.values.mwoNumber = number;
  360 |         await expectStatus(technician.page, 'open');
  361 |       },
  362 |       async () => {
  363 |         const row = await assertDocumentAndStatus(context, 'open', 'idle');
  364 |         expect(row.created_by_email).toBe(technicianIdentity.email);
  365 |         expect(row.assigned_email).toBeNull();
  366 |         expect(technician.page.getByRole('button', { name: 'Confirm start', exact: true })).toBeVisible();
  367 |         expect(technician.page.getByRole('button', { name: 'Record work', exact: true })).toHaveCount(0);
  368 |         expect(technician.page.getByRole('button', { name: 'Complete', exact: true })).toHaveCount(0);
  369 |       },
  370 |       async () => {
  371 |         const number = contextValue<string>(context, 'mwoNumber');
  372 |         const matches = await readOnlyDatabaseJson<Array<{ count: number }>>(`
  373 |           SELECT COUNT(*)::int AS count FROM maintenance_work_orders WHERE mwo_number = ${sqlText(number)}
  374 |         `);
  375 |         expect(matches[0].count, 'generated MWO number should be unique').toBe(1);
  376 |         expect(await notifications(context)).toEqual([{
  377 |           type: 'maintenance.assignment_needed',
  378 |           email: supervisorIdentity.email,
  379 |           title: `Maintenance assignment needed: ${number}`,
```