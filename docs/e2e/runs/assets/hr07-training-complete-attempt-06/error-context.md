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
  - 'status "Real-time updates: Reconnecting. Last updated 1:56 AM"': Reconnecting
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
  - text: "Refreshing… Active Last login: Nov 5, 2026 · 1:56 AM Email: e2e+department_head-hr@ogami-e2e.test Role: Department Head"
```

# Test source

```ts
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
  207 |   const employeeDisplayName = [employee.first_name, employee.last_name]
  208 |     .map((part) => part.trim())
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
> 293 |         await expect(hr.page.getByRole('heading', { name: employeeDisplayName, exact: true })).toBeVisible();
      |                                                                                                ^ Error: expect(locator).toBeVisible() failed
  294 |         await hr.page.getByRole('tab', { name: 'Trainings', exact: true }).click();
  295 |         await expect(hr.page.getByRole('heading', { name: 'Trainings (0)', exact: true })).toBeVisible();
  296 |         await hr.page.getByRole('button', { name: 'Assign', exact: true }).click();
  297 |         await expect(hr.page.getByRole('dialog', { name: 'Assign training' })).toBeVisible();
  298 |         await hr.page.getByLabel('Training', { exact: true }).selectOption({ label: courseName });
  299 |         await hr.page.getByLabel('Scheduled for', { exact: true }).fill(workDate);
  300 |         const responsePromise = hr.page.waitForResponse((response) =>
  301 |           new URL(response.url()).pathname.endsWith('/trainings')
  302 |           && response.request().method() === 'POST');
  303 |         await hr.page.getByRole('button', { name: 'Assign training', exact: true }).click();
  304 |         const response = await responsePromise;
  305 |         expect(response.status(), 'the employee training assignment should be created by HR through the UI').toBe(201);
  306 |         const body = await response.json() as { data?: { id?: string } };
  307 |         if (!body.data?.id) throw new Error('The UI-created training assignment response had no public id.');
  308 |         journey.values.employeeTrainingPublicId = body.data.id;
  309 |       },
  310 |       verify: async () => {
  311 |         const assignedRow = hr.page.getByRole('row').filter({ hasText: courseName });
  312 |         await expect(assignedRow).toHaveCount(1);
  313 |         await expect(assignedRow).toContainText('Scheduled');
  314 |         await expect(hr.page.getByRole('button', { name: `Mark ${courseName} complete`, exact: true })).toBeVisible();
  315 |         const upcomingSignal = observer.page.getByRole('button', { name: /View all Employee Training Records signals:.*Trainings upcoming or expiring soon: 1/ });
  316 |         await expect(upcomingSignal, 'the employee-scoped upcoming badge should update for the assigned record').toBeVisible();
  317 |       },
  318 |       assertInvariants: async () => {
  319 |         const rows = await employeeTrainingFor(employee.employee_no, courseName);
  320 |         expect(rows).toHaveLength(1);
  321 |         expect(rows[0]).toMatchObject({
  322 |           employee_no: employee.employee_no,
  323 |           course_name: courseName,
  324 |           status: 'scheduled',
  325 |           scheduled_for: workDate,
  326 |           completed_at: null,
  327 |           expires_at: null,
  328 |           created_by_email: hrActor.email,
  329 |           notification_count: 0,
  330 |         });
  331 |         expect(rows[0].audit_entries).toEqual([{ action: 'created', actor_email: hrActor.email, status: 'scheduled' }]);
  332 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  333 |         hr.diagnostics.assertClean('HR assigns a course to the department-scoped Employee');
  334 |         observer.diagnostics.assertClean('the Department Head receives the upcoming-training badge');
  335 |       },
  336 |     });
  337 | 
  338 |     await runProcessStep({
  339 |       ...plannedSteps[2],
  340 |       page: observer.page,
  341 |       plannedSteps,
  342 |       context: journey,
  343 |       testInfo,
  344 |       action: async () => {
  345 |         const signalButton = observer.page.getByRole('button', { name: /View all Employee Training Records signals:/ });
  346 |         await signalButton.click();
  347 |         const signalMenu = observer.page.getByRole('dialog', { name: 'Employee Training Records signals' });
  348 |         const upcomingLink = signalMenu.getByRole('link', { name: /Trainings upcoming or expiring soon: 1/ });
  349 |         await expect(upcomingLink).toBeVisible();
  350 |         await upcomingLink.click();
  351 |         await expect(observer.page).toHaveURL(/\/hr\/employee-trainings\?signal=upcoming/);
  352 |         await expect(observer.page.getByRole('heading', { name: 'Employee Training Records', exact: true })).toBeVisible();
  353 |         const trainingRow = observer.page.getByRole('row').filter({ hasText: courseName });
  354 |         await expect(trainingRow, 'the badge destination should list the newly assigned course').toHaveCount(1);
  355 |         await trainingRow.click();
  356 |         await expect(observer.page).toHaveURL(/\/hr\/employee-trainings\//);
  357 |       },
  358 |       verify: async () => {
  359 |         await expect(observer.page.getByRole('heading', { name: courseName, exact: true })).toBeVisible();
  360 |         await expect(observer.page.getByText('Scheduled', { exact: true })).toBeVisible();
  361 |         await expect(observer.page.getByText(workDate, { exact: true })).toBeVisible();
  362 |         await expect(observer.page.getByRole('button', { name: /complete/i })).toHaveCount(0);
  363 |         await expect(observer.page.getByRole('button', { name: /^Cancel$/ })).toHaveCount(0);
  364 |       },
  365 |       assertInvariants: async () => {
  366 |         const rows = await employeeTrainingFor(employee.employee_no, courseName);
  367 |         expect(rows).toHaveLength(1);
  368 |         expect(rows[0].status).toBe('scheduled');
  369 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  370 |         observer.diagnostics.assertClean('Department Head finds and opens the work from the upcoming badge');
  371 |         hr.diagnostics.assertClean('HR retains the scheduled training assignment');
  372 |       },
  373 |     });
  374 | 
  375 |     await runProcessStep({
  376 |       ...plannedSteps[3],
  377 |       page: observer.page,
  378 |       plannedSteps,
  379 |       context: journey,
  380 |       testInfo,
  381 |       action: async () => {
  382 |         const row = hr.page.getByRole('row').filter({ hasText: courseName });
  383 |         await expect(row).toContainText('Scheduled');
  384 |         await hr.page.getByRole('button', { name: `Mark ${courseName} complete`, exact: true }).click();
  385 |         await expect(hr.page.getByRole('dialog', { name: `Complete ${courseName}` })).toBeVisible();
  386 |         await hr.page.getByRole('button', { name: 'Complete training', exact: true }).click();
  387 |         await expect(row).toContainText('Completed');
  388 |       },
  389 |       verify: async () => {
  390 |         await expect(observer.page.getByText('Completed', { exact: true }), 'the open Department Head detail should update without reload').toBeVisible();
  391 |         await expect(observer.page.getByText(workDate, { exact: true })).toBeVisible();
  392 |         await expect(observer.page.getByText(expectedExpiry, { exact: true })).toBeVisible();
  393 |         await expect(observer.page.getByRole('button', { name: /View all Employee Training Records signals:/ })).toHaveCount(0);
```