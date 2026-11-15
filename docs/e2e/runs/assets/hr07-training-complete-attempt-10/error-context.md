# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-training.process.spec.ts >> HR07-TRAINING-COMPLETE — HR assigns and completes training while the department-scoped observer follows the upcoming badge
- Location: e2e-process/hr-training.process.spec.ts:200:1

# Error details

```
TimeoutError: page.waitForResponse: Timeout 15000ms exceeded while waiting for event "response"
```

# Page snapshot

```yaml
- generic [ref=e3]:
  - link "Skip to content" [ref=e4] [cursor=pointer]:
    - /url: "#main-content"
  - banner [ref=e5]:
    - generic [ref=e6]:
      - button "Toggle sidebar" [ref=e7] [cursor=pointer]
      - link "Ogami ERP Philippine Ogami Corporation" [ref=e10] [cursor=pointer]:
        - /url: /dashboard
        - img "Ogami ERP" [ref=e11]
        - generic "Philippine Ogami Corporation" [ref=e12]
      - navigation "Breadcrumb" [ref=e14]:
        - link "Human Resources" [ref=e16] [cursor=pointer]:
          - /url: /hr/employees
        - link "Employees" [ref=e20] [cursor=pointer]:
          - /url: /hr/employees
        - generic [ref=e21]: E2E department head hr
    - button "Search… ⌘K" [ref=e26] [cursor=pointer]:
      - generic [ref=e30]: Search…
      - generic [ref=e31]: ⌘K
    - generic [ref=e32]:
      - button "Toggle theme" [ref=e35] [cursor=pointer]
      - button "Notifications" [ref=e40] [cursor=pointer]
      - generic [ref=e44]:
        - 'status "Real-time updates: Live. Last updated 2:17 AM" [ref=e45]':
          - generic [ref=e47]: Live
        - button "Keyboard shortcuts" [ref=e50] [cursor=pointer]
      - button "Profile menu for E2E hr officer" [ref=e55] [cursor=pointer]:
        - img "E2E hr officer" [ref=e56]: EH
        - generic "E2E hr officer" [ref=e57]
  - generic [ref=e60]:
    - complementary "Sidebar" [ref=e61]:
      - navigation "Modules" [ref=e62]:
        - group "Work" [ref=e63]:
          - list [ref=e65]:
            - listitem [ref=e66]:
              - link "Dashboard" [ref=e67] [cursor=pointer]:
                - /url: /dashboard
            - listitem [ref=e74]:
              - link "Action Center" [ref=e75] [cursor=pointer]:
                - /url: /action-center
            - listitem [ref=e81]:
              - link "Decision Center" [ref=e82] [cursor=pointer]:
                - /url: /decision-center
            - listitem [ref=e88]:
              - link "Approval Queue" [ref=e89] [cursor=pointer]:
                - /url: /approvals
            - listitem [ref=e94]:
              - link "Notifications" [ref=e95] [cursor=pointer]:
                - /url: /notifications
        - generic [ref=e100]:
          - button "Human Resources" [disabled] [expanded] [ref=e101]
          - list [ref=e105]:
            - listitem [ref=e106]:
              - link "Recruitment" [ref=e107] [cursor=pointer]:
                - /url: /hr/recruitment
            - listitem [ref=e114]:
              - link "Training Programs" [ref=e115] [cursor=pointer]:
                - /url: /hr/trainings
            - listitem [ref=e120]:
              - link "Employee Training Records" [ref=e121] [cursor=pointer]:
                - /url: /hr/employee-trainings
            - listitem [ref=e126]:
              - link "Employee Loans" [ref=e127] [cursor=pointer]:
                - /url: /hr/loans
            - listitem [ref=e132]:
              - link "Separation Clearance" [ref=e133] [cursor=pointer]:
                - /url: /hr/separations
            - listitem [ref=e139]:
              - link "Profile Change Requests" [ref=e140] [cursor=pointer]:
                - /url: /hr/profile-update-requests
            - listitem [ref=e144]:
              - link "Employees" [ref=e145] [cursor=pointer]:
                - /url: /hr/employees
            - listitem [ref=e153]:
              - link "Departments" [ref=e154] [cursor=pointer]:
                - /url: /hr/departments
            - listitem [ref=e158]:
              - link "Attendance & DTR" [ref=e159] [cursor=pointer]:
                - /url: /hr/attendance
            - listitem [ref=e164]:
              - link "Overtime" [ref=e165] [cursor=pointer]:
                - /url: /hr/attendance/overtime
            - listitem [ref=e171]:
              - link "Leave Management" [ref=e172] [cursor=pointer]:
                - /url: /hr/leaves
            - listitem [ref=e176]:
              - link "Payroll Processing" [ref=e177] [cursor=pointer]:
                - /url: /payroll/periods
            - listitem [ref=e182]:
              - link "Payroll Adjustments" [ref=e183] [cursor=pointer]:
                - /url: /payroll/adjustments
            - listitem [ref=e188]:
              - link "Payroll Statutory Exports" [ref=e189] [cursor=pointer]:
                - /url: /payroll/statutory
        - button "Administration" [ref=e195]
      - group "Module controls" [ref=e199]:
        - button "Expand all" [ref=e200]
    - main [ref=e203]:
      - generic [ref=e205]:
        - generic [ref=e207]:
          - generic [ref=e208]:
            - link "Employees" [ref=e209] [cursor=pointer]:
              - /url: /hr/employees
            - heading "E2E department head hr Active" [level=1] [ref=e213]:
              - generic [ref=e214]:
                - text: E2E department head hr
                - generic [ref=e215]: Active
            - generic [ref=e216]: E2E-32663B6B7DA1372 · HR Manager · Human Resources
          - generic [ref=e219]:
            - button "Edit" [ref=e220] [cursor=pointer]
            - button "Separate" [ref=e224] [cursor=pointer]
        - generic [ref=e229]:
          - heading "Onboarding" [level=3] [ref=e232]
          - generic [ref=e235]:
            - generic [ref=e240]:
              - generic [ref=e241]: Profile
              - generic [ref=e242]: 11/5/2026
            - generic [ref=e243]: Shift
            - generic [ref=e251]:
              - generic [ref=e252]: Leave Balances
              - generic [ref=e253]: 11/5/2026
            - generic [ref=e258]:
              - generic [ref=e259]: System Account
              - generic [ref=e260]: 11/5/2026
            - generic [ref=e263]:
              - generic [ref=e264]: Dept Team Notified
              - generic [ref=e265]:
                - generic [ref=e266]: HR confirmation is required for this step.
                - button "Mark team notified" [ref=e267] [cursor=pointer]
            - generic [ref=e268]: Government IDs
            - generic [ref=e272]: Banking Info
        - tablist "Employee details" [ref=e277]:
          - tab "Overview" [ref=e278] [cursor=pointer]
          - tab "Employment history" [ref=e279] [cursor=pointer]
          - tab "Attendance" [ref=e280] [cursor=pointer]
          - tab "Leaves" [ref=e281] [cursor=pointer]
          - tab "Loans" [ref=e282] [cursor=pointer]
          - tab "Documents" [ref=e283] [cursor=pointer]
          - tab "Trainings" [selected] [ref=e284] [cursor=pointer]
          - tab "Skills" [ref=e286] [cursor=pointer]
          - tab "Payroll" [ref=e287] [cursor=pointer]
          - tab "Activity" [ref=e288] [cursor=pointer]
        - generic [ref=e289]:
          - generic [ref=e290]:
            - generic [ref=e291]:
              - generic [ref=e292]:
                - heading "Trainings (0)" [level=3] [ref=e294]
                - button "Assign" [ref=e296] [cursor=pointer]
              - heading "No trainings assigned" [level=3] [ref=e306]
            - dialog [ref=e307]:
              - generic [ref=e309]:
                - generic [ref=e310]:
                  - heading "Assign training" [level=2] [ref=e311]
                  - button "Close modal" [ref=e312]
                - generic [ref=e317]:
                  - generic [ref=e318]:
                    - generic [ref=e319]: Training
                    - combobox "Training" [ref=e320]:
                      - option "— Select —"
                      - option "E2E-51e6e613 Competence Training" [selected]
                  - generic [ref=e321]:
                    - generic [ref=e322]: Scheduled for
                    - textbox "Scheduled for" [active] [ref=e324]: 2026-11-05
                  - generic [ref=e325]:
                    - button "Cancel" [ref=e326] [cursor=pointer]
                    - button "Assign" [ref=e327] [cursor=pointer]
          - generic [ref=e328]:
            - generic [ref=e329]:
              - heading "At a glance" [level=3] [ref=e332]
              - generic [ref=e334]:
                - generic [ref=e335]:
                  - term [ref=e336]: Employee no
                  - definition [ref=e337]: E2E-32663B6B7DA1372
                - generic [ref=e338]:
                  - term [ref=e339]: Hired
                  - definition [ref=e340]: Nov 5, 2024
                - generic [ref=e341]:
                  - term [ref=e342]: Pay type
                  - definition [ref=e343]: Monthly
                - generic [ref=e344]:
                  - term [ref=e345]: Monthly salary
                  - definition [ref=e346]: ₱30,000.00
                - generic [ref=e347]:
                  - term [ref=e348]: Linked user
                  - definition [ref=e349]: e2e+department_head-hr@ogami-e2e.test
            - generic [ref=e350]:
              - heading "System Account" [level=3] [ref=e353]
              - generic [ref=e355]:
                - generic [ref=e356]:
                  - generic [ref=e357]: Active
                  - generic [ref=e358]: "Last login: Nov 5, 2026 · 2:17 AM"
                - generic [ref=e359]: "Email: e2e+department_head-hr@ogami-e2e.test"
                - generic [ref=e360]: "Role: Department Head"
```

# Test source

```ts
  201 |   test.setTimeout(180_000);
  202 | 
  203 |   const actors = await loadActors();
  204 |   const hrActor = actorByName(actors, 'hr_officer');
  205 |   const observerActor = actorByName(actors, 'department_head__hr');
  206 |   const employee = await employeeForEmail(observerActor.email);
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
  298 |         await expect(hr.page.getByRole('dialog', { name: 'Assign training' })).toBeVisible();
  299 |         await hr.page.getByLabel('Training', { exact: true }).selectOption({ label: courseName });
  300 |         await hr.page.getByLabel('Scheduled for', { exact: true }).fill(workDate);
> 301 |         const responsePromise = hr.page.waitForResponse((response) =>
      |                                         ^ TimeoutError: page.waitForResponse: Timeout 15000ms exceeded while waiting for event "response"
  302 |           new URL(response.url()).pathname.endsWith('/trainings')
  303 |           && response.request().method() === 'POST');
  304 |         await hr.page.getByRole('button', { name: 'Assign training', exact: true }).click();
  305 |         const response = await responsePromise;
  306 |         expect(response.status(), 'the employee training assignment should be created by HR through the UI').toBe(201);
  307 |         const body = await response.json() as { data?: { id?: string } };
  308 |         if (!body.data?.id) throw new Error('The UI-created training assignment response had no public id.');
  309 |         journey.values.employeeTrainingPublicId = body.data.id;
  310 |       },
  311 |       verify: async () => {
  312 |         const assignedRow = hr.page.getByRole('row').filter({ hasText: courseName });
  313 |         await expect(assignedRow).toHaveCount(1);
  314 |         await expect(assignedRow).toContainText('Scheduled');
  315 |         await expect(hr.page.getByRole('button', { name: `Mark ${courseName} complete`, exact: true })).toBeVisible();
  316 |         const upcomingSignal = observer.page.getByRole('button', { name: /View all Employee Training Records signals:.*Trainings upcoming or expiring soon: 1/ });
  317 |         await expect(upcomingSignal, 'the employee-scoped upcoming badge should update for the assigned record').toBeVisible();
  318 |       },
  319 |       assertInvariants: async () => {
  320 |         const rows = await employeeTrainingFor(employee.employee_no, courseName);
  321 |         expect(rows).toHaveLength(1);
  322 |         expect(rows[0]).toMatchObject({
  323 |           employee_no: employee.employee_no,
  324 |           course_name: courseName,
  325 |           status: 'scheduled',
  326 |           scheduled_for: workDate,
  327 |           completed_at: null,
  328 |           expires_at: null,
  329 |           created_by_email: hrActor.email,
  330 |           notification_count: 0,
  331 |         });
  332 |         expect(rows[0].audit_entries).toEqual([{ action: 'created', actor_email: hrActor.email, status: 'scheduled' }]);
  333 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  334 |         hr.diagnostics.assertClean('HR assigns a course to the department-scoped Employee');
  335 |         observer.diagnostics.assertClean('the Department Head receives the upcoming-training badge');
  336 |       },
  337 |     });
  338 | 
  339 |     await runProcessStep({
  340 |       ...plannedSteps[2],
  341 |       page: observer.page,
  342 |       plannedSteps,
  343 |       context: journey,
  344 |       testInfo,
  345 |       action: async () => {
  346 |         const signalButton = observer.page.getByRole('button', { name: /View all Employee Training Records signals:/ });
  347 |         await signalButton.click();
  348 |         const signalMenu = observer.page.getByRole('dialog', { name: 'Employee Training Records signals' });
  349 |         const upcomingLink = signalMenu.getByRole('link', { name: /Trainings upcoming or expiring soon: 1/ });
  350 |         await expect(upcomingLink).toBeVisible();
  351 |         await upcomingLink.click();
  352 |         await expect(observer.page).toHaveURL(/\/hr\/employee-trainings\?signal=upcoming/);
  353 |         await expect(observer.page.getByRole('heading', { name: 'Employee Training Records', exact: true })).toBeVisible();
  354 |         const trainingRow = observer.page.getByRole('row').filter({ hasText: courseName });
  355 |         await expect(trainingRow, 'the badge destination should list the newly assigned course').toHaveCount(1);
  356 |         await trainingRow.click();
  357 |         await expect(observer.page).toHaveURL(/\/hr\/employee-trainings\//);
  358 |       },
  359 |       verify: async () => {
  360 |         await expect(observer.page.getByRole('heading', { name: courseName, exact: true })).toBeVisible();
  361 |         await expect(observer.page.getByText('Scheduled', { exact: true })).toBeVisible();
  362 |         await expect(observer.page.getByText(workDate, { exact: true })).toBeVisible();
  363 |         await expect(observer.page.getByRole('button', { name: /complete/i })).toHaveCount(0);
  364 |         await expect(observer.page.getByRole('button', { name: /^Cancel$/ })).toHaveCount(0);
  365 |       },
  366 |       assertInvariants: async () => {
  367 |         const rows = await employeeTrainingFor(employee.employee_no, courseName);
  368 |         expect(rows).toHaveLength(1);
  369 |         expect(rows[0].status).toBe('scheduled');
  370 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  371 |         observer.diagnostics.assertClean('Department Head finds and opens the work from the upcoming badge');
  372 |         hr.diagnostics.assertClean('HR retains the scheduled training assignment');
  373 |       },
  374 |     });
  375 | 
  376 |     await runProcessStep({
  377 |       ...plannedSteps[3],
  378 |       page: observer.page,
  379 |       plannedSteps,
  380 |       context: journey,
  381 |       testInfo,
  382 |       action: async () => {
  383 |         const row = hr.page.getByRole('row').filter({ hasText: courseName });
  384 |         await expect(row).toContainText('Scheduled');
  385 |         await hr.page.getByRole('button', { name: `Mark ${courseName} complete`, exact: true }).click();
  386 |         await expect(hr.page.getByRole('dialog', { name: `Complete ${courseName}` })).toBeVisible();
  387 |         await hr.page.getByRole('button', { name: 'Complete training', exact: true }).click();
  388 |         await expect(row).toContainText('Completed');
  389 |       },
  390 |       verify: async () => {
  391 |         await expect(observer.page.getByText('Completed', { exact: true }), 'the open Department Head detail should update without reload').toBeVisible();
  392 |         await expect(observer.page.getByText(workDate, { exact: true })).toBeVisible();
  393 |         await expect(observer.page.getByText(expectedExpiry, { exact: true })).toBeVisible();
  394 |         await expect(observer.page.getByRole('button', { name: /View all Employee Training Records signals:/ })).toHaveCount(0);
  395 |         await expect(hr.page.getByRole('button', { name: `Mark ${courseName} complete`, exact: true })).toHaveCount(0);
  396 |       },
  397 |       assertInvariants: async () => {
  398 |         const rows = await employeeTrainingFor(employee.employee_no, courseName);
  399 |         expect(rows).toHaveLength(1);
  400 |         expect(rows[0]).toMatchObject({
  401 |           employee_no: employee.employee_no,
```