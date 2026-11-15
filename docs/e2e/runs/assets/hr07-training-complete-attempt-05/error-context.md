# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-training.process.spec.ts >> HR07-TRAINING-COMPLETE — HR assigns and completes training while the department-scoped observer follows the upcoming badge
- Location: e2e-process/hr-training.process.spec.ts:200:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('heading', { name: 'E2E department head  hr', exact: true })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByRole('heading', { name: 'E2E department head  hr', exact: true }) with timeout 8000ms
  - waiting for getByRole('heading', { name: 'E2E department head  hr', exact: true })

```

```yaml
- link "Skip to content":
  - /url: "#main-content"
- banner:
  - button "Toggle sidebar":
    - img
  - link "Ogami ERP Philippine Ogami Corporation":
    - /url: /dashboard
    - img "Ogami ERP"
    - text: Philippine Ogami Corporation
  - navigation "Breadcrumb":
    - link "Human Resources":
      - /url: /hr/employees
    - img
    - link "Employees":
      - /url: /hr/employees
    - img
    - text: E2E department head hr
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications":
    - img
  - 'status "Real-time updates: Reconnecting. Last updated 1:53 AM"': Reconnecting
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E hr officer":
    - img "E2E hr officer": EH
    - text: E2E hr officer
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
    - button "Human Resources" [disabled] [expanded]
    - list:
      - listitem:
        - link "Recruitment":
          - /url: /hr/recruitment
          - img
          - text: Recruitment
      - listitem:
        - link "Training Programs":
          - /url: /hr/trainings
          - img
          - text: Training Programs
      - listitem:
        - link "Employee Training Records":
          - /url: /hr/employee-trainings
          - img
          - text: Employee Training Records
      - listitem:
        - link "Employee Loans":
          - /url: /hr/loans
          - img
          - text: Employee Loans
      - listitem:
        - link "Separation Clearance":
          - /url: /hr/separations
          - img
          - text: Separation Clearance
      - listitem:
        - link "Profile Change Requests":
          - /url: /hr/profile-update-requests
          - img
          - text: Profile Change Requests
      - listitem:
        - link "Employees":
          - /url: /hr/employees
          - img
          - text: Employees
      - listitem:
        - link "Departments":
          - /url: /hr/departments
          - img
          - text: Departments
      - listitem:
        - link "Attendance & DTR":
          - /url: /hr/attendance
          - img
          - text: Attendance & DTR
      - listitem:
        - link "Overtime":
          - /url: /hr/attendance/overtime
          - img
          - text: Overtime
      - listitem:
        - link "Leave Management":
          - /url: /hr/leaves
          - img
          - text: Leave Management
      - listitem:
        - link "Payroll Processing":
          - /url: /payroll/periods
          - img
          - text: Payroll Processing
      - listitem:
        - link "Payroll Adjustments":
          - /url: /payroll/adjustments
          - img
          - text: Payroll Adjustments
      - listitem:
        - link "Payroll Statutory Exports":
          - /url: /payroll/statutory
          - img
          - text: Payroll Statutory Exports
    - button "Administration"
  - group "Module controls":
    - button "Expand all"
- main:
  - link "Employees":
    - /url: /hr/employees
    - img
    - text: Employees
  - heading "E2E department head hr Active" [level=1]
  - text: E2E-32663B6B7DA1372 · HR Manager · Human Resources
  - button "Edit":
    - img
    - text: Edit
  - button "Separate":
    - img
    - text: Separate
  - heading "Onboarding" [level=3]
  - text: Profile 11/5/2026 Shift Leave Balances 11/5/2026 System Account 11/5/2026 Dept Team Notified HR confirmation is required for this step.
  - button "Mark team notified"
  - text: Government IDs Banking Info
  - tablist "Employee details":
    - tab "Overview" [selected]
    - tab "Employment history"
    - tab "Attendance"
    - tab "Leaves"
    - tab "Loans"
    - tab "Documents"
    - tab "Trainings"
    - tab "Skills"
    - tab "Payroll"
    - tab "Activity"
  - heading "Personal" [level=3]
  - img
  - text: Upload
  - term: Full name
  - definition: E2E department head hr
  - term: Birth date
  - definition: Nov 5, 1996
  - term: Gender
  - definition: Male
  - term: Civil status
  - definition: Single
  - term: Nationality
  - definition: Filipino
  - heading "Contact" [level=3]
  - term: Mobile
  - definition: —
  - term: Email
  - definition: e2e+department_head-hr@ogami-e2e.test
  - term: Address
  - definition: —
  - term: Emergency contact
  - definition: —
  - heading "Government IDs" [level=3]
  - text: Masked. Click reveal to view.
  - button "Reveal":
    - img
    - text: Reveal
  - term: SSS
  - definition: —
  - term: PhilHealth
  - definition: —
  - term: Pag-IBIG
  - definition: —
  - term: TIN
  - definition: —
  - heading "Banking" [level=3]
  - term: Bank
  - definition: —
  - term: Account number
  - definition: —
  - heading "At a glance" [level=3]
  - term: Employee no
  - definition: E2E-32663B6B7DA1372
  - term: Hired
  - definition: Nov 5, 2024
  - term: Pay type
  - definition: Monthly
  - term: Monthly salary
  - definition: ₱30,000.00
  - term: Linked user
  - definition: e2e+department_head-hr@ogami-e2e.test
  - heading "System Account" [level=3]
  - text: "Active Last login: Nov 5, 2026 · 1:53 AM Email: e2e+department_head-hr@ogami-e2e.test Role: Department Head"
```

# Test source

```ts
  189 |   await expect(target, `the ${label} route should be available to this role`).toHaveCount(1);
  190 |   if (!(await target.isVisible())) {
  191 |     const module = session.page.locator(`[data-module="${section}"]`);
  192 |     const toggle = module.getByRole('button');
  193 |     await expect(toggle, `the ${section} sidebar group should be expandable`).toHaveCount(1);
  194 |     if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click();
  195 |   }
  196 |   await expect(target).toBeVisible();
  197 |   await target.click();
  198 | }
  199 | 
  200 | test('HR07-TRAINING-COMPLETE — HR assigns and completes training while the department-scoped observer follows the upcoming badge', async ({ browser, request }, testInfo) => {
  201 |   test.setTimeout(180_000);
  202 | 
  203 |   const actors = await loadActors();
  204 |   const hrActor = actorByName(actors, 'hr_officer');
  205 |   const observerActor = actorByName(actors, 'department_head__hr');
  206 |   const employee = await employeeForEmail(observerActor.email);
  207 |   const clockNow = await readClock(request);
  208 |   const workDate = manilaDate(clockNow);
  209 |   const courseName = `E2E-${randomUUID().slice(0, 8)} Competence Training`;
  210 |   const courseDescription = `Run-scoped employee training for ${courseName}`;
  211 |   const expectedExpiry = addValidityMonths(workDate, 12);
  212 |   const journey = createJourneyContext('HR07-TRAINING-COMPLETE');
  213 |   journey.values.employeeNo = employee.employee_no;
  214 |   journey.values.employeeName = `${employee.first_name} ${employee.last_name}`;
  215 |   journey.values.courseName = courseName;
  216 |   journey.values.scheduledFor = workDate;
  217 |   journey.values.completedAt = workDate;
  218 |   journey.values.expiresAt = expectedExpiry;
  219 | 
  220 |   const hr = await actorPage(browser, hrActor.actor, { timezoneId: 'Asia/Manila' });
  221 |   const observer = await actorPage(browser, observerActor.actor, { timezoneId: 'Asia/Manila' });
  222 |   const actorEmails = [hrActor.email, observerActor.email];
  223 |   const mailIdsBefore = await mailIdsForActors(actorEmails);
  224 |   let primaryError: unknown;
  225 | 
  226 |   try {
  227 |     await hr.page.clock.setFixedTime(new Date(clockNow));
  228 |     await observer.page.clock.setFixedTime(new Date(clockNow));
  229 | 
  230 |     await runProcessStep({
  231 |       ...plannedSteps[0],
  232 |       page: hr.page,
  233 |       plannedSteps,
  234 |       context: journey,
  235 |       testInfo,
  236 |       action: async () => {
  237 |         // actorPage creates an authenticated context with no initial document.
  238 |         // Land at the application's post-login destination before navigating
  239 |         // to Training Programs through the sidebar.
  240 |         await hr.page.goto('/dashboard');
  241 |         await expect(hr.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  242 |         await openSidebarItem(hr, 'Human Resources', '/hr/trainings', 'Training Programs');
  243 |         await expect(hr.page.getByRole('heading', { name: 'Trainings', exact: true })).toBeVisible();
  244 |         await hr.page.getByRole('button', { name: 'Add Training', exact: true }).click();
  245 |         await expect(hr.page.getByRole('heading', { name: 'Add training', exact: true })).toBeVisible();
  246 |         await observer.page.goto('/dashboard');
  247 |         await expect(observer.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  248 |       },
  249 |       verify: async () => {
  250 |         await hr.page.getByLabel(/^Name\s*\*?$/).fill(courseName);
  251 |         await hr.page.getByLabel('Description', { exact: true }).fill(courseDescription);
  252 |         await hr.page.getByLabel('Duration (hours)', { exact: true }).fill('2');
  253 |         await hr.page.getByLabel('Validity (months)', { exact: true }).fill('12');
  254 |         const responsePromise = hr.page.waitForResponse((response) =>
  255 |           new URL(response.url()).pathname === '/api/v1/hr/trainings'
  256 |           && response.request().method() === 'POST');
  257 |         await hr.page.getByRole('button', { name: 'Create training', exact: true }).click();
  258 |         const response = await responsePromise;
  259 |         expect(response.status(), 'course master data should be created by the HR UI').toBe(201);
  260 |         await expect(hr.page.getByRole('heading', { name: 'Trainings', exact: true })).toBeVisible();
  261 |         await expect(hr.page.getByRole('row').filter({ hasText: courseName })).toHaveCount(1);
  262 |       },
  263 |       assertInvariants: async () => {
  264 |         const courses = await courseForName(courseName);
  265 |         expect(courses).toHaveLength(1);
  266 |         expect(courses[0]).toMatchObject({ name: courseName, is_active: true, validity_months: 12 });
  267 |         expect(courses[0].audit_entries).toEqual([{ action: 'created', actor_email: hrActor.email }]);
  268 |         expect(await employeeTrainingFor(employee.employee_no, courseName)).toHaveLength(0);
  269 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  270 |         hr.diagnostics.assertClean('HR creates the active training course through the catalog UI');
  271 |         observer.diagnostics.assertClean('Department Head opens the dashboard before the hand-off');
  272 |       },
  273 |     });
  274 | 
  275 |     await runProcessStep({
  276 |       ...plannedSteps[1],
  277 |       page: hr.page,
  278 |       plannedSteps,
  279 |       context: journey,
  280 |       testInfo,
  281 |       action: async () => {
  282 |         await openSidebarItem(hr, 'Human Resources', '/hr/employees', 'Employees');
  283 |         await expect(hr.page.getByRole('heading', { name: 'Employees', exact: true })).toBeVisible();
  284 |         const employeeSearch = hr.page.getByPlaceholder('Search name or employee no…');
  285 |         await employeeSearch.fill(employee.employee_no);
  286 |         const employeeRow = hr.page.getByRole('row').filter({ hasText: employee.employee_no });
  287 |         await expect(employeeRow, 'HR should find the in-department Employee through the list').toHaveCount(1);
  288 |         await employeeRow.click();
> 289 |         await expect(hr.page.getByRole('heading', { name: `${employee.first_name} ${employee.last_name}`, exact: true })).toBeVisible();
      |                                                                                                                           ^ Error: expect(locator).toBeVisible() failed
  290 |         await hr.page.getByRole('tab', { name: 'Trainings', exact: true }).click();
  291 |         await expect(hr.page.getByRole('heading', { name: 'Trainings (0)', exact: true })).toBeVisible();
  292 |         await hr.page.getByRole('button', { name: 'Assign', exact: true }).click();
  293 |         await expect(hr.page.getByRole('dialog', { name: 'Assign training' })).toBeVisible();
  294 |         await hr.page.getByLabel('Training', { exact: true }).selectOption({ label: courseName });
  295 |         await hr.page.getByLabel('Scheduled for', { exact: true }).fill(workDate);
  296 |         const responsePromise = hr.page.waitForResponse((response) =>
  297 |           new URL(response.url()).pathname.endsWith('/trainings')
  298 |           && response.request().method() === 'POST');
  299 |         await hr.page.getByRole('button', { name: 'Assign training', exact: true }).click();
  300 |         const response = await responsePromise;
  301 |         expect(response.status(), 'the employee training assignment should be created by HR through the UI').toBe(201);
  302 |         const body = await response.json() as { data?: { id?: string } };
  303 |         if (!body.data?.id) throw new Error('The UI-created training assignment response had no public id.');
  304 |         journey.values.employeeTrainingPublicId = body.data.id;
  305 |       },
  306 |       verify: async () => {
  307 |         const assignedRow = hr.page.getByRole('row').filter({ hasText: courseName });
  308 |         await expect(assignedRow).toHaveCount(1);
  309 |         await expect(assignedRow).toContainText('Scheduled');
  310 |         await expect(hr.page.getByRole('button', { name: `Mark ${courseName} complete`, exact: true })).toBeVisible();
  311 |         const upcomingSignal = observer.page.getByRole('button', { name: /View all Employee Training Records signals:.*Trainings upcoming or expiring soon: 1/ });
  312 |         await expect(upcomingSignal, 'the employee-scoped upcoming badge should update for the assigned record').toBeVisible();
  313 |       },
  314 |       assertInvariants: async () => {
  315 |         const rows = await employeeTrainingFor(employee.employee_no, courseName);
  316 |         expect(rows).toHaveLength(1);
  317 |         expect(rows[0]).toMatchObject({
  318 |           employee_no: employee.employee_no,
  319 |           course_name: courseName,
  320 |           status: 'scheduled',
  321 |           scheduled_for: workDate,
  322 |           completed_at: null,
  323 |           expires_at: null,
  324 |           created_by_email: hrActor.email,
  325 |           notification_count: 0,
  326 |         });
  327 |         expect(rows[0].audit_entries).toEqual([{ action: 'created', actor_email: hrActor.email, status: 'scheduled' }]);
  328 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  329 |         hr.diagnostics.assertClean('HR assigns a course to the department-scoped Employee');
  330 |         observer.diagnostics.assertClean('the Department Head receives the upcoming-training badge');
  331 |       },
  332 |     });
  333 | 
  334 |     await runProcessStep({
  335 |       ...plannedSteps[2],
  336 |       page: observer.page,
  337 |       plannedSteps,
  338 |       context: journey,
  339 |       testInfo,
  340 |       action: async () => {
  341 |         const signalButton = observer.page.getByRole('button', { name: /View all Employee Training Records signals:/ });
  342 |         await signalButton.click();
  343 |         const signalMenu = observer.page.getByRole('dialog', { name: 'Employee Training Records signals' });
  344 |         const upcomingLink = signalMenu.getByRole('link', { name: /Trainings upcoming or expiring soon: 1/ });
  345 |         await expect(upcomingLink).toBeVisible();
  346 |         await upcomingLink.click();
  347 |         await expect(observer.page).toHaveURL(/\/hr\/employee-trainings\?signal=upcoming/);
  348 |         await expect(observer.page.getByRole('heading', { name: 'Employee Training Records', exact: true })).toBeVisible();
  349 |         const trainingRow = observer.page.getByRole('row').filter({ hasText: courseName });
  350 |         await expect(trainingRow, 'the badge destination should list the newly assigned course').toHaveCount(1);
  351 |         await trainingRow.click();
  352 |         await expect(observer.page).toHaveURL(/\/hr\/employee-trainings\//);
  353 |       },
  354 |       verify: async () => {
  355 |         await expect(observer.page.getByRole('heading', { name: courseName, exact: true })).toBeVisible();
  356 |         await expect(observer.page.getByText('Scheduled', { exact: true })).toBeVisible();
  357 |         await expect(observer.page.getByText(workDate, { exact: true })).toBeVisible();
  358 |         await expect(observer.page.getByRole('button', { name: /complete/i })).toHaveCount(0);
  359 |         await expect(observer.page.getByRole('button', { name: /^Cancel$/ })).toHaveCount(0);
  360 |       },
  361 |       assertInvariants: async () => {
  362 |         const rows = await employeeTrainingFor(employee.employee_no, courseName);
  363 |         expect(rows).toHaveLength(1);
  364 |         expect(rows[0].status).toBe('scheduled');
  365 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  366 |         observer.diagnostics.assertClean('Department Head finds and opens the work from the upcoming badge');
  367 |         hr.diagnostics.assertClean('HR retains the scheduled training assignment');
  368 |       },
  369 |     });
  370 | 
  371 |     await runProcessStep({
  372 |       ...plannedSteps[3],
  373 |       page: observer.page,
  374 |       plannedSteps,
  375 |       context: journey,
  376 |       testInfo,
  377 |       action: async () => {
  378 |         const row = hr.page.getByRole('row').filter({ hasText: courseName });
  379 |         await expect(row).toContainText('Scheduled');
  380 |         await hr.page.getByRole('button', { name: `Mark ${courseName} complete`, exact: true }).click();
  381 |         await expect(hr.page.getByRole('dialog', { name: `Complete ${courseName}` })).toBeVisible();
  382 |         await hr.page.getByRole('button', { name: 'Complete training', exact: true }).click();
  383 |         await expect(row).toContainText('Completed');
  384 |       },
  385 |       verify: async () => {
  386 |         await expect(observer.page.getByText('Completed', { exact: true }), 'the open Department Head detail should update without reload').toBeVisible();
  387 |         await expect(observer.page.getByText(workDate, { exact: true })).toBeVisible();
  388 |         await expect(observer.page.getByText(expectedExpiry, { exact: true })).toBeVisible();
  389 |         await expect(observer.page.getByRole('button', { name: /View all Employee Training Records signals:/ })).toHaveCount(0);
```