# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-training.process.spec.ts >> HR07-TRAINING-COMPLETE — HR assigns and completes training while the department-scoped observer follows the upcoming badge
- Location: e2e-process/hr-training.process.spec.ts:199:1

# Error details

```
TimeoutError: locator.fill: Timeout 15000ms exceeded.
Call log:
  - waiting for getByLabel('Name', { exact: true })

```

# Test source

```ts
  149 |            record.expires_at::text AS expires_at,
  150 |            creator.email AS created_by_email,
  151 |            COALESCE((
  152 |              SELECT json_agg(json_build_object(
  153 |                'action', audit.action,
  154 |                'actor_email', actor.email,
  155 |                'status', audit.new_values->>'status'
  156 |              ) ORDER BY audit.id)
  157 |                FROM audit_logs audit
  158 |                LEFT JOIN users actor ON actor.id = audit.user_id
  159 |               WHERE audit.model_type ILIKE '%EmployeeTraining'
  160 |                 AND audit.model_id = record.id
  161 |            ), '[]'::json) AS audit_entries,
  162 |            (SELECT COUNT(*)::int
  163 |               FROM notifications notice
  164 |              WHERE notice.data->>'subject_type' ILIKE '%EmployeeTraining'
  165 |                AND notice.data->>'subject_id' = record.id::text) AS notification_count
  166 |       FROM employee_trainings record
  167 |       JOIN employees employee ON employee.id = record.employee_id
  168 |       JOIN trainings course ON course.id = record.training_id
  169 |       LEFT JOIN users creator ON creator.id = record.created_by
  170 |      WHERE employee.employee_no = ${sqlText(employeeNo)}
  171 |        AND course.name = ${sqlText(courseName)}
  172 |   `);
  173 | }
  174 | 
  175 | async function mailIdsForActors(emails: string[]): Promise<string[]> {
  176 |   const messages = await Promise.all(emails.map((email) => findMailpitMessagesFor(email)));
  177 |   return messages.flat().map((message) => message.ID).sort();
  178 | }
  179 | 
  180 | async function openSidebarItem(
  181 |   session: ActorPage,
  182 |   section: string,
  183 |   href: string,
  184 |   label: string,
  185 | ): Promise<void> {
  186 |   const nav = session.page.getByRole('navigation', { name: 'Modules' });
  187 |   const target = nav.locator(`a[href="${href}"]`);
  188 |   await expect(target, `the ${label} route should be available to this role`).toHaveCount(1);
  189 |   if (!(await target.isVisible())) {
  190 |     const module = session.page.locator(`[data-module="${section}"]`);
  191 |     const toggle = module.getByRole('button');
  192 |     await expect(toggle, `the ${section} sidebar group should be expandable`).toHaveCount(1);
  193 |     if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click();
  194 |   }
  195 |   await expect(target).toBeVisible();
  196 |   await target.click();
  197 | }
  198 | 
  199 | test('HR07-TRAINING-COMPLETE — HR assigns and completes training while the department-scoped observer follows the upcoming badge', async ({ browser, request }, testInfo) => {
  200 |   test.setTimeout(180_000);
  201 | 
  202 |   const actors = await loadActors();
  203 |   const hrActor = actorByName(actors, 'hr_officer');
  204 |   const observerActor = actorByName(actors, 'department_head__hr');
  205 |   const employee = await employeeForEmail(observerActor.email);
  206 |   const clockNow = await readClock(request);
  207 |   const workDate = manilaDate(clockNow);
  208 |   const courseName = `E2E-${randomUUID().slice(0, 8)} Competence Training`;
  209 |   const courseDescription = `Run-scoped employee training for ${courseName}`;
  210 |   const expectedExpiry = addValidityMonths(workDate, 12);
  211 |   const journey = createJourneyContext('HR07-TRAINING-COMPLETE');
  212 |   journey.values.employeeNo = employee.employee_no;
  213 |   journey.values.employeeName = `${employee.first_name} ${employee.last_name}`;
  214 |   journey.values.courseName = courseName;
  215 |   journey.values.scheduledFor = workDate;
  216 |   journey.values.completedAt = workDate;
  217 |   journey.values.expiresAt = expectedExpiry;
  218 | 
  219 |   const hr = await actorPage(browser, hrActor.actor, { timezoneId: 'Asia/Manila' });
  220 |   const observer = await actorPage(browser, observerActor.actor, { timezoneId: 'Asia/Manila' });
  221 |   const actorEmails = [hrActor.email, observerActor.email];
  222 |   const mailIdsBefore = await mailIdsForActors(actorEmails);
  223 |   let primaryError: unknown;
  224 | 
  225 |   try {
  226 |     await hr.page.clock.setFixedTime(new Date(clockNow));
  227 |     await observer.page.clock.setFixedTime(new Date(clockNow));
  228 | 
  229 |     await runProcessStep({
  230 |       ...plannedSteps[0],
  231 |       page: hr.page,
  232 |       plannedSteps,
  233 |       context: journey,
  234 |       testInfo,
  235 |       action: async () => {
  236 |         // actorPage creates an authenticated context with no initial document.
  237 |         // Land at the application's post-login destination before navigating
  238 |         // to Training Programs through the sidebar.
  239 |         await hr.page.goto('/dashboard');
  240 |         await expect(hr.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  241 |         await openSidebarItem(hr, 'Human Resources', '/hr/trainings', 'Training Programs');
  242 |         await expect(hr.page.getByRole('heading', { name: 'Trainings', exact: true })).toBeVisible();
  243 |         await hr.page.getByRole('button', { name: 'Add Training', exact: true }).click();
  244 |         await expect(hr.page.getByRole('heading', { name: 'Add training', exact: true })).toBeVisible();
  245 |         await observer.page.goto('/dashboard');
  246 |         await expect(observer.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  247 |       },
  248 |       verify: async () => {
> 249 |         await hr.page.getByLabel('Name', { exact: true }).fill(courseName);
      |                                                           ^ TimeoutError: locator.fill: Timeout 15000ms exceeded.
  250 |         await hr.page.getByLabel('Description', { exact: true }).fill(courseDescription);
  251 |         await hr.page.getByLabel('Duration (hours)', { exact: true }).fill('2');
  252 |         await hr.page.getByLabel('Validity (months)', { exact: true }).fill('12');
  253 |         const responsePromise = hr.page.waitForResponse((response) =>
  254 |           new URL(response.url()).pathname === '/api/v1/hr/trainings'
  255 |           && response.request().method() === 'POST');
  256 |         await hr.page.getByRole('button', { name: 'Create training', exact: true }).click();
  257 |         const response = await responsePromise;
  258 |         expect(response.status(), 'course master data should be created by the HR UI').toBe(201);
  259 |         await expect(hr.page.getByRole('heading', { name: 'Trainings', exact: true })).toBeVisible();
  260 |         await expect(hr.page.getByRole('row').filter({ hasText: courseName })).toHaveCount(1);
  261 |       },
  262 |       assertInvariants: async () => {
  263 |         const courses = await courseForName(courseName);
  264 |         expect(courses).toHaveLength(1);
  265 |         expect(courses[0]).toMatchObject({ name: courseName, is_active: true, validity_months: 12 });
  266 |         expect(courses[0].audit_entries).toEqual([{ action: 'created', actor_email: hrActor.email, status: 'true' }]);
  267 |         expect(await employeeTrainingFor(employee.employee_no, courseName)).toHaveLength(0);
  268 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  269 |         hr.diagnostics.assertClean('HR creates the active training course through the catalog UI');
  270 |         observer.diagnostics.assertClean('Department Head opens the dashboard before the hand-off');
  271 |       },
  272 |     });
  273 | 
  274 |     await runProcessStep({
  275 |       ...plannedSteps[1],
  276 |       page: hr.page,
  277 |       plannedSteps,
  278 |       context: journey,
  279 |       testInfo,
  280 |       action: async () => {
  281 |         await openSidebarItem(hr, 'Human Resources', '/hr/employees', 'Employees');
  282 |         await expect(hr.page.getByRole('heading', { name: 'Employees', exact: true })).toBeVisible();
  283 |         const employeeSearch = hr.page.getByPlaceholder('Search name or employee no…');
  284 |         await employeeSearch.fill(employee.employee_no);
  285 |         const employeeRow = hr.page.getByRole('row').filter({ hasText: employee.employee_no });
  286 |         await expect(employeeRow, 'HR should find the in-department Employee through the list').toHaveCount(1);
  287 |         await employeeRow.click();
  288 |         await expect(hr.page.getByRole('heading', { name: `${employee.first_name} ${employee.last_name}`, exact: true })).toBeVisible();
  289 |         await hr.page.getByRole('tab', { name: 'Trainings', exact: true }).click();
  290 |         await expect(hr.page.getByRole('heading', { name: 'Trainings (0)', exact: true })).toBeVisible();
  291 |         await hr.page.getByRole('button', { name: 'Assign', exact: true }).click();
  292 |         await expect(hr.page.getByRole('dialog', { name: 'Assign training' })).toBeVisible();
  293 |         await hr.page.getByLabel('Training', { exact: true }).selectOption({ label: courseName });
  294 |         await hr.page.getByLabel('Scheduled for', { exact: true }).fill(workDate);
  295 |         const responsePromise = hr.page.waitForResponse((response) =>
  296 |           new URL(response.url()).pathname.endsWith('/trainings')
  297 |           && response.request().method() === 'POST');
  298 |         await hr.page.getByRole('button', { name: 'Assign training', exact: true }).click();
  299 |         const response = await responsePromise;
  300 |         expect(response.status(), 'the employee training assignment should be created by HR through the UI').toBe(201);
  301 |         const body = await response.json() as { data?: { id?: string } };
  302 |         if (!body.data?.id) throw new Error('The UI-created training assignment response had no public id.');
  303 |         journey.values.employeeTrainingPublicId = body.data.id;
  304 |       },
  305 |       verify: async () => {
  306 |         const assignedRow = hr.page.getByRole('row').filter({ hasText: courseName });
  307 |         await expect(assignedRow).toHaveCount(1);
  308 |         await expect(assignedRow).toContainText('Scheduled');
  309 |         await expect(hr.page.getByRole('button', { name: `Mark ${courseName} complete`, exact: true })).toBeVisible();
  310 |         const upcomingSignal = observer.page.getByRole('button', { name: /View all Employee Training Records signals:.*Trainings upcoming or expiring soon: 1/ });
  311 |         await expect(upcomingSignal, 'the employee-scoped upcoming badge should update for the assigned record').toBeVisible();
  312 |       },
  313 |       assertInvariants: async () => {
  314 |         const rows = await employeeTrainingFor(employee.employee_no, courseName);
  315 |         expect(rows).toHaveLength(1);
  316 |         expect(rows[0]).toMatchObject({
  317 |           employee_no: employee.employee_no,
  318 |           course_name: courseName,
  319 |           status: 'scheduled',
  320 |           scheduled_for: workDate,
  321 |           completed_at: null,
  322 |           expires_at: null,
  323 |           created_by_email: hrActor.email,
  324 |           notification_count: 0,
  325 |         });
  326 |         expect(rows[0].audit_entries).toEqual([{ action: 'created', actor_email: hrActor.email, status: 'scheduled' }]);
  327 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  328 |         hr.diagnostics.assertClean('HR assigns a course to the department-scoped Employee');
  329 |         observer.diagnostics.assertClean('the Department Head receives the upcoming-training badge');
  330 |       },
  331 |     });
  332 | 
  333 |     await runProcessStep({
  334 |       ...plannedSteps[2],
  335 |       page: observer.page,
  336 |       plannedSteps,
  337 |       context: journey,
  338 |       testInfo,
  339 |       action: async () => {
  340 |         const signalButton = observer.page.getByRole('button', { name: /View all Employee Training Records signals:/ });
  341 |         await signalButton.click();
  342 |         const signalMenu = observer.page.getByRole('dialog', { name: 'Employee Training Records signals' });
  343 |         const upcomingLink = signalMenu.getByRole('link', { name: /Trainings upcoming or expiring soon: 1/ });
  344 |         await expect(upcomingLink).toBeVisible();
  345 |         await upcomingLink.click();
  346 |         await expect(observer.page).toHaveURL(/\/hr\/employee-trainings\?signal=upcoming/);
  347 |         await expect(observer.page.getByRole('heading', { name: 'Employee Training Records', exact: true })).toBeVisible();
  348 |         const trainingRow = observer.page.getByRole('row').filter({ hasText: courseName });
  349 |         await expect(trainingRow, 'the badge destination should list the newly assigned course').toHaveCount(1);
```