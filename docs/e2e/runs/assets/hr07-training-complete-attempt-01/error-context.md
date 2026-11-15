# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-training.process.spec.ts >> HR07-TRAINING-COMPLETE — HR assigns and completes training while the department-scoped observer follows the upcoming badge
- Location: e2e-process/hr-training.process.spec.ts:199:1

# Error details

```
Error: the Training Programs route should be available to this role

expect(locator).toHaveCount(expected) failed

Locator:  getByRole('navigation', { name: 'Modules' }).locator('a[href="/hr/trainings"]')
Expected: 1
Received: 0
Timeout:  8000ms

Call log:
  - the Training Programs route should be available to this role getByRole('navigation', { name: 'Modules' }).locator('a[href="/hr/trainings"]') with timeout 8000ms
  - waiting for getByRole('navigation', { name: 'Modules' }).locator('a[href="/hr/trainings"]')
    20 × locator resolved to 0 elements
       - unexpected value "0"

```

# Test source

```ts
  88  |     day: '2-digit',
  89  |   }).formatToParts(new Date(instant));
  90  |   const part = (type: string) => parts.find((candidate) => candidate.type === type)?.value;
  91  |   const year = part('year');
  92  |   const month = part('month');
  93  |   const day = part('day');
  94  |   if (!year || !month || !day) throw new Error(`Cannot resolve Manila business date from ${instant}.`);
  95  |   return `${year}-${month}-${day}`;
  96  | }
  97  | 
  98  | function addValidityMonths(date: string, months: number): string {
  99  |   const result = new Date(`${date}T00:00:00.000Z`);
  100 |   result.setUTCMonth(result.getUTCMonth() + months);
  101 |   return result.toISOString().slice(0, 10);
  102 | }
  103 | 
  104 | async function employeeForEmail(email: string): Promise<EmployeeBaseline> {
  105 |   const rows = await readOnlyDatabaseJson<EmployeeBaseline[]>(`
  106 |     SELECT employee.id AS employee_id,
  107 |            employee.employee_no,
  108 |            employee.first_name,
  109 |            employee.last_name
  110 |       FROM users actor
  111 |       JOIN employees employee ON employee.id = actor.employee_id
  112 |      WHERE actor.email = ${sqlText(email)}
  113 |   `);
  114 |   expect(rows, 'the department-scoped observer must resolve to its seeded Employee row').toHaveLength(1);
  115 |   return rows[0];
  116 | }
  117 | 
  118 | async function courseForName(name: string): Promise<CourseSnapshot[]> {
  119 |   return readOnlyDatabaseJson<CourseSnapshot[]>(`
  120 |     SELECT course.id AS training_id,
  121 |            course.name,
  122 |            course.is_active,
  123 |            course.validity_months,
  124 |            COALESCE((
  125 |              SELECT json_agg(json_build_object(
  126 |                'action', audit.action,
  127 |                'actor_email', actor.email,
  128 |                'status', audit.new_values->>'is_active'
  129 |              ) ORDER BY audit.id)
  130 |                FROM audit_logs audit
  131 |                LEFT JOIN users actor ON actor.id = audit.user_id
  132 |               WHERE audit.model_type ILIKE '%Training'
  133 |                 AND audit.model_type NOT ILIKE '%EmployeeTraining'
  134 |                 AND audit.model_id = course.id
  135 |            ), '[]'::json) AS audit_entries
  136 |       FROM trainings course
  137 |      WHERE course.name = ${sqlText(name)}
  138 |   `);
  139 | }
  140 | 
  141 | async function employeeTrainingFor(employeeNo: string, courseName: string): Promise<EmployeeTrainingSnapshot[]> {
  142 |   return readOnlyDatabaseJson<EmployeeTrainingSnapshot[]>(`
  143 |     SELECT record.id AS record_id,
  144 |            employee.employee_no,
  145 |            course.name AS course_name,
  146 |            record.status::text AS status,
  147 |            record.scheduled_for::text AS scheduled_for,
  148 |            record.completed_at::text AS completed_at,
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
> 188 |   await expect(target, `the ${label} route should be available to this role`).toHaveCount(1);
      |                                                                               ^ Error: the Training Programs route should be available to this role
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
  236 |         await openSidebarItem(hr, 'Human Resources', '/hr/trainings', 'Training Programs');
  237 |         await expect(hr.page.getByRole('heading', { name: 'Trainings', exact: true })).toBeVisible();
  238 |         await hr.page.getByRole('button', { name: 'Add Training', exact: true }).click();
  239 |         await expect(hr.page.getByRole('heading', { name: 'Add training', exact: true })).toBeVisible();
  240 |         await observer.page.goto('/dashboard');
  241 |         await expect(observer.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  242 |       },
  243 |       verify: async () => {
  244 |         await hr.page.getByLabel('Name', { exact: true }).fill(courseName);
  245 |         await hr.page.getByLabel('Description', { exact: true }).fill(courseDescription);
  246 |         await hr.page.getByLabel('Duration (hours)', { exact: true }).fill('2');
  247 |         await hr.page.getByLabel('Validity (months)', { exact: true }).fill('12');
  248 |         const responsePromise = hr.page.waitForResponse((response) =>
  249 |           new URL(response.url()).pathname === '/api/v1/hr/trainings'
  250 |           && response.request().method() === 'POST');
  251 |         await hr.page.getByRole('button', { name: 'Create training', exact: true }).click();
  252 |         const response = await responsePromise;
  253 |         expect(response.status(), 'course master data should be created by the HR UI').toBe(201);
  254 |         await expect(hr.page.getByRole('heading', { name: 'Trainings', exact: true })).toBeVisible();
  255 |         await expect(hr.page.getByRole('row').filter({ hasText: courseName })).toHaveCount(1);
  256 |       },
  257 |       assertInvariants: async () => {
  258 |         const courses = await courseForName(courseName);
  259 |         expect(courses).toHaveLength(1);
  260 |         expect(courses[0]).toMatchObject({ name: courseName, is_active: true, validity_months: 12 });
  261 |         expect(courses[0].audit_entries).toEqual([{ action: 'created', actor_email: hrActor.email, status: 'true' }]);
  262 |         expect(await employeeTrainingFor(employee.employee_no, courseName)).toHaveLength(0);
  263 |         expect(await mailIdsForActors(actorEmails)).toEqual(mailIdsBefore);
  264 |         hr.diagnostics.assertClean('HR creates the active training course through the catalog UI');
  265 |         observer.diagnostics.assertClean('Department Head opens the dashboard before the hand-off');
  266 |       },
  267 |     });
  268 | 
  269 |     await runProcessStep({
  270 |       ...plannedSteps[1],
  271 |       page: hr.page,
  272 |       plannedSteps,
  273 |       context: journey,
  274 |       testInfo,
  275 |       action: async () => {
  276 |         await openSidebarItem(hr, 'Human Resources', '/hr/employees', 'Employees');
  277 |         await expect(hr.page.getByRole('heading', { name: 'Employees', exact: true })).toBeVisible();
  278 |         const employeeSearch = hr.page.getByPlaceholder('Search name or employee no…');
  279 |         await employeeSearch.fill(employee.employee_no);
  280 |         const employeeRow = hr.page.getByRole('row').filter({ hasText: employee.employee_no });
  281 |         await expect(employeeRow, 'HR should find the in-department Employee through the list').toHaveCount(1);
  282 |         await employeeRow.click();
  283 |         await expect(hr.page.getByRole('heading', { name: `${employee.first_name} ${employee.last_name}`, exact: true })).toBeVisible();
  284 |         await hr.page.getByRole('tab', { name: 'Trainings', exact: true }).click();
  285 |         await expect(hr.page.getByRole('heading', { name: 'Trainings (0)', exact: true })).toBeVisible();
  286 |         await hr.page.getByRole('button', { name: 'Assign', exact: true }).click();
  287 |         await expect(hr.page.getByRole('dialog', { name: 'Assign training' })).toBeVisible();
  288 |         await hr.page.getByLabel('Training', { exact: true }).selectOption({ label: courseName });
```