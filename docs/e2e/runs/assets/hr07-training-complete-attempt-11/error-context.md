# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-training.process.spec.ts >> HR07-TRAINING-COMPLETE — HR assigns and completes training while the department-scoped observer follows the upcoming badge
- Location: e2e-process/hr-training.process.spec.ts:200:1

# Error details

```
Error: the employee training assignment should be created by HR through the UI

expect(received).toBe(expected) // Object.is equality

Expected: 201
Received: 500
```

# Test source

```ts
  207 |   const employeeDisplayName = [employee.first_name, employee.last_name]
  208 |     .map((part) => part.trim().replace(/\s+/g, ' '))
  209 |     .filter(Boolean)
  210 |     .join(' ');
  211 |   const clockNow = await readClock(request);
  212 |   const workDate = manilaDate(clockNow);
  213 |   const courseName = `E2E-${randomUUID().slice(0, 8)} Competence Training`;
  214 |   const courseDescription = `Run-scoped employee training for ${courseName}`;
  215 |   const expectedExpiry = addValidityMonths(workDate, 12);
  216 |   const journey = createJourneyContext('HR07-TRAINING-COMPLETE');
  217 |   journey.values.employeeNo = employee.employee_no;
  218 |   journey.values.employeeName = employeeDisplayName;
  219 |   journey.values.courseName = courseName;
  220 |   journey.values.scheduledFor = workDate;
  221 |   journey.values.completedAt = workDate;
  222 |   journey.values.expiresAt = expectedExpiry;
  223 | 
  224 |   const hr = await actorPage(browser, hrActor.actor, { timezoneId: 'Asia/Manila' });
  225 |   const observer = await actorPage(browser, observerActor.actor, { timezoneId: 'Asia/Manila' });
  226 |   const actorEmails = [hrActor.email, observerActor.email];
  227 |   const mailIdsBefore = await mailIdsForActors(actorEmails);
  228 |   let primaryError: unknown;
  229 | 
  230 |   try {
  231 |     await hr.page.clock.setFixedTime(new Date(clockNow));
  232 |     await observer.page.clock.setFixedTime(new Date(clockNow));
  233 | 
  234 |     await runProcessStep({
  235 |       ...plannedSteps[0],
  236 |       page: hr.page,
  237 |       plannedSteps,
  238 |       context: journey,
  239 |       testInfo,
  240 |       action: async () => {
  241 |         // actorPage creates an authenticated context with no initial document.
  242 |         // Land at the application's post-login destination before navigating
  243 |         // to Training Programs through the sidebar.
  244 |         await hr.page.goto('/dashboard');
  245 |         await expect(hr.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  246 |         await openSidebarItem(hr, 'Human Resources', '/hr/trainings', 'Training Programs');
  247 |         await expect(hr.page.getByRole('heading', { name: 'Trainings', exact: true })).toBeVisible();
  248 |         await hr.page.getByRole('button', { name: 'Add Training', exact: true }).click();
  249 |         await expect(hr.page.getByRole('heading', { name: 'Add training', exact: true })).toBeVisible();
  250 |         await observer.page.goto('/dashboard');
  251 |         await expect(observer.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  252 |       },
  253 |       verify: async () => {
  254 |         await hr.page.getByLabel(/^Name\s*\*?$/).fill(courseName);
  255 |         await hr.page.getByLabel('Description', { exact: true }).fill(courseDescription);
  256 |         await hr.page.getByLabel('Duration (hours)', { exact: true }).fill('2');
  257 |         await hr.page.getByLabel('Validity (months)', { exact: true }).fill('12');
  258 |         const responsePromise = hr.page.waitForResponse((response) =>
  259 |           new URL(response.url()).pathname === '/api/v1/hr/trainings'
  260 |           && response.request().method() === 'POST');
  261 |         await hr.page.getByRole('button', { name: 'Create training', exact: true }).click();
  262 |         const response = await responsePromise;
  263 |         expect(response.status(), 'course master data should be created by the HR UI').toBe(201);
  264 |         await expect(hr.page.getByRole('heading', { name: 'Trainings', exact: true })).toBeVisible();
  265 |         await expect(hr.page.getByRole('row').filter({ hasText: courseName })).toHaveCount(1);
  266 |       },
  267 |       assertInvariants: async () => {
  268 |         const courses = await courseForName(courseName);
  269 |         expect(courses).toHaveLength(1);
  270 |         expect(courses[0]).toMatchObject({ name: courseName, is_active: true, validity_months: 12 });
  271 |         expect(courses[0].audit_entries).toEqual([{ action: 'created', actor_email: hrActor.email }]);
  272 |         expect(await employeeTrainingFor(employee.employee_no, courseName)).toHaveLength(0);
  273 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  274 |         hr.diagnostics.assertClean('HR creates the active training course through the catalog UI');
  275 |         observer.diagnostics.assertClean('Department Head opens the dashboard before the hand-off');
  276 |       },
  277 |     });
  278 | 
  279 |     await runProcessStep({
  280 |       ...plannedSteps[1],
  281 |       page: hr.page,
  282 |       plannedSteps,
  283 |       context: journey,
  284 |       testInfo,
  285 |       action: async () => {
  286 |         await openSidebarItem(hr, 'Human Resources', '/hr/employees', 'Employees');
  287 |         await expect(hr.page.getByRole('heading', { name: 'Employees', exact: true })).toBeVisible();
  288 |         const employeeSearch = hr.page.getByPlaceholder('Search name or employee no…');
  289 |         await employeeSearch.fill(employee.employee_no);
  290 |         const employeeRow = hr.page.getByRole('row').filter({ hasText: employee.employee_no });
  291 |         await expect(employeeRow, 'HR should find the in-department Employee through the list').toHaveCount(1);
  292 |         await employeeRow.click();
  293 |         await expect(hr.page.getByRole('heading', { name: `${employeeDisplayName} Active`, exact: true }))
  294 |           .toBeVisible();
  295 |         await hr.page.getByRole('tab', { name: 'Trainings', exact: true }).click();
  296 |         await expect(hr.page.getByRole('heading', { name: 'Trainings (0)', exact: true })).toBeVisible();
  297 |         await hr.page.getByRole('button', { name: 'Assign', exact: true }).click();
  298 |         const assignmentDialog = hr.page.getByRole('dialog', { name: 'Assign training' });
  299 |         await expect(assignmentDialog).toBeVisible();
  300 |         await hr.page.getByLabel('Training', { exact: true }).selectOption({ label: courseName });
  301 |         await hr.page.getByLabel('Scheduled for', { exact: true }).fill(workDate);
  302 |         const responsePromise = hr.page.waitForResponse((response) =>
  303 |           new URL(response.url()).pathname.endsWith('/trainings')
  304 |           && response.request().method() === 'POST');
  305 |         await assignmentDialog.getByRole('button', { name: 'Assign', exact: true }).click();
  306 |         const response = await responsePromise;
> 307 |         expect(response.status(), 'the employee training assignment should be created by HR through the UI').toBe(201);
      |                                                                                                              ^ Error: the employee training assignment should be created by HR through the UI
  308 |         const body = await response.json() as { data?: { id?: string } };
  309 |         if (!body.data?.id) throw new Error('The UI-created training assignment response had no public id.');
  310 |         journey.values.employeeTrainingPublicId = body.data.id;
  311 |       },
  312 |       verify: async () => {
  313 |         const assignedRow = hr.page.getByRole('row').filter({ hasText: courseName });
  314 |         await expect(assignedRow).toHaveCount(1);
  315 |         await expect(assignedRow).toContainText('Scheduled');
  316 |         await expect(hr.page.getByRole('button', { name: `Mark ${courseName} complete`, exact: true })).toBeVisible();
  317 |         const upcomingSignal = observer.page.getByRole('button', { name: /View all Employee Training Records signals:.*Trainings upcoming or expiring soon: 1/ });
  318 |         await expect(upcomingSignal, 'the employee-scoped upcoming badge should update for the assigned record').toBeVisible();
  319 |       },
  320 |       assertInvariants: async () => {
  321 |         const rows = await employeeTrainingFor(employee.employee_no, courseName);
  322 |         expect(rows).toHaveLength(1);
  323 |         expect(rows[0]).toMatchObject({
  324 |           employee_no: employee.employee_no,
  325 |           course_name: courseName,
  326 |           status: 'scheduled',
  327 |           scheduled_for: workDate,
  328 |           completed_at: null,
  329 |           expires_at: null,
  330 |           created_by_email: hrActor.email,
  331 |           notification_count: 0,
  332 |         });
  333 |         expect(rows[0].audit_entries).toEqual([{ action: 'created', actor_email: hrActor.email, status: 'scheduled' }]);
  334 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  335 |         hr.diagnostics.assertClean('HR assigns a course to the department-scoped Employee');
  336 |         observer.diagnostics.assertClean('the Department Head receives the upcoming-training badge');
  337 |       },
  338 |     });
  339 | 
  340 |     await runProcessStep({
  341 |       ...plannedSteps[2],
  342 |       page: observer.page,
  343 |       plannedSteps,
  344 |       context: journey,
  345 |       testInfo,
  346 |       action: async () => {
  347 |         const signalButton = observer.page.getByRole('button', { name: /View all Employee Training Records signals:/ });
  348 |         await signalButton.click();
  349 |         const signalMenu = observer.page.getByRole('dialog', { name: 'Employee Training Records signals' });
  350 |         const upcomingLink = signalMenu.getByRole('link', { name: /Trainings upcoming or expiring soon: 1/ });
  351 |         await expect(upcomingLink).toBeVisible();
  352 |         await upcomingLink.click();
  353 |         await expect(observer.page).toHaveURL(/\/hr\/employee-trainings\?signal=upcoming/);
  354 |         await expect(observer.page.getByRole('heading', { name: 'Employee Training Records', exact: true })).toBeVisible();
  355 |         const trainingRow = observer.page.getByRole('row').filter({ hasText: courseName });
  356 |         await expect(trainingRow, 'the badge destination should list the newly assigned course').toHaveCount(1);
  357 |         await trainingRow.click();
  358 |         await expect(observer.page).toHaveURL(/\/hr\/employee-trainings\//);
  359 |       },
  360 |       verify: async () => {
  361 |         await expect(observer.page.getByRole('heading', { name: courseName, exact: true })).toBeVisible();
  362 |         await expect(observer.page.getByText('Scheduled', { exact: true })).toBeVisible();
  363 |         await expect(observer.page.getByText(workDate, { exact: true })).toBeVisible();
  364 |         await expect(observer.page.getByRole('button', { name: /complete/i })).toHaveCount(0);
  365 |         await expect(observer.page.getByRole('button', { name: /^Cancel$/ })).toHaveCount(0);
  366 |       },
  367 |       assertInvariants: async () => {
  368 |         const rows = await employeeTrainingFor(employee.employee_no, courseName);
  369 |         expect(rows).toHaveLength(1);
  370 |         expect(rows[0].status).toBe('scheduled');
  371 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  372 |         observer.diagnostics.assertClean('Department Head finds and opens the work from the upcoming badge');
  373 |         hr.diagnostics.assertClean('HR retains the scheduled training assignment');
  374 |       },
  375 |     });
  376 | 
  377 |     await runProcessStep({
  378 |       ...plannedSteps[3],
  379 |       page: observer.page,
  380 |       plannedSteps,
  381 |       context: journey,
  382 |       testInfo,
  383 |       action: async () => {
  384 |         const row = hr.page.getByRole('row').filter({ hasText: courseName });
  385 |         await expect(row).toContainText('Scheduled');
  386 |         await hr.page.getByRole('button', { name: `Mark ${courseName} complete`, exact: true }).click();
  387 |         await expect(hr.page.getByRole('dialog', { name: `Complete ${courseName}` })).toBeVisible();
  388 |         await hr.page.getByRole('button', { name: 'Complete training', exact: true }).click();
  389 |         await expect(row).toContainText('Completed');
  390 |       },
  391 |       verify: async () => {
  392 |         await expect(observer.page.getByText('Completed', { exact: true }), 'the open Department Head detail should update without reload').toBeVisible();
  393 |         await expect(observer.page.getByText(workDate, { exact: true })).toBeVisible();
  394 |         await expect(observer.page.getByText(expectedExpiry, { exact: true })).toBeVisible();
  395 |         await expect(observer.page.getByRole('button', { name: /View all Employee Training Records signals:/ })).toHaveCount(0);
  396 |         await expect(hr.page.getByRole('button', { name: `Mark ${courseName} complete`, exact: true })).toHaveCount(0);
  397 |       },
  398 |       assertInvariants: async () => {
  399 |         const rows = await employeeTrainingFor(employee.employee_no, courseName);
  400 |         expect(rows).toHaveLength(1);
  401 |         expect(rows[0]).toMatchObject({
  402 |           employee_no: employee.employee_no,
  403 |           course_name: courseName,
  404 |           status: 'completed',
  405 |           scheduled_for: workDate,
  406 |           completed_at: workDate,
  407 |           expires_at: expectedExpiry,
```