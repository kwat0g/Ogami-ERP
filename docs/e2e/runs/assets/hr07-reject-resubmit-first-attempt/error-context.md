# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-profile.process.spec.ts >> HR07-REJECT-RESUBMIT — HR rejects a bank change, then employee resubmits for dual approval
- Location: e2e-process/hr-profile.process.spec.ts:537:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('region', { name: 'Requested changes' })
Expected: visible
Error: strict mode violation: getByRole('region', { name: 'Requested changes' }) resolved to 2 elements:
    1) <section aria-label="Requested changes">…</section> aka getByRole('table').getByText('Requested changesBankE2E RESUBMIT 19C84010Bank account numberE2ERS19C84010E6284')
    2) <section aria-label="Requested changes">…</section> aka getByRole('table').getByText('Requested changesBankE2E REJECT 19C84010Bank account numberE2ER19C84010ED1081')

Call log:
  - Expect "toBeVisible" getByRole('region', { name: 'Requested changes' }) with timeout 8000ms
  - waiting for getByRole('region', { name: 'Requested changes' })

```

# Test source

```ts
  164 | 
  165 | async function clickBadgeHandoff(session: ActorPage, itemLabel: string, expectedCount: number): Promise<void> {
  166 |   const badge = session.page.getByRole('button', {
  167 |     name: new RegExp(`View all ${itemLabel} signals: ${expectedCount}`),
  168 |   });
  169 |   await expect(badge, `${itemLabel} should expose the actionable work count`).toBeVisible();
  170 |   await expect(badge).toContainText(String(expectedCount));
  171 |   await badge.click();
  172 |   const signalMenu = session.page.getByRole('dialog', { name: `${itemLabel} signals` });
  173 |   const actionable = signalMenu.getByRole('link').filter({ hasText: 'Awaiting my action' });
  174 |   await expect(actionable, `${itemLabel} should expose the actionable queue link`).toHaveCount(1);
  175 |   await expect(actionable).toContainText(String(expectedCount));
  176 |   await actionable.click();
  177 |   // The live SPA currently routes this signal to the queue path without the
  178 |   // expected `signal=awaiting_my_action` query (UX-HR-001 is tracked).
  179 |   await expect(session.page).toHaveURL(/\/hr\/profile-update-requests(?:\?|$)/);
  180 | }
  181 | 
  182 | async function expandEmployeeRequest(session: ActorPage, employeeName: string): Promise<void> {
  183 |   const row = session.page.getByRole('row').filter({ hasText: employeeName });
  184 |   await expect(row, 'the employee request should be present in this role\'s actionable queue').toHaveCount(1);
  185 |   await row.locator('td').last().click();
  186 |   await expect(session.page.getByRole('region', { name: 'Requested changes' })).toBeVisible();
  187 | }
  188 | 
  189 | async function mailpitForActors(emails: string[]): Promise<MailpitSummary[]> {
  190 |   const messages = await Promise.all(emails.map((email) => findMailpitMessagesFor(email)));
  191 |   return messages.flat();
  192 | }
  193 | 
  194 | type RejectionRunRequest = RequestSnapshot & {
  195 |   created_at: string;
  196 | };
  197 | 
  198 | type ProcessAuditSnapshot = AuditSnapshot & {
  199 |   request_id: number;
  200 | };
  201 | 
  202 | async function requestsForJourney(requesterEmail: string, createdAfter: string): Promise<RejectionRunRequest[]> {
  203 |   return readOnlyDatabaseJson<RejectionRunRequest[]>(`
  204 |     SELECT request.id AS request_id, request.created_at,
  205 |            request.status, request.requires_finance, request.employee_id,
  206 |            requester.email AS requester_email,
  207 |            employee.bank_name AS employee_bank_name,
  208 |            employee.bank_account_no AS employee_bank_account_cipher,
  209 |            hr.email AS hr_reviewer_email,
  210 |            finance.email AS finance_reviewer_email,
  211 |            (SELECT COUNT(*)::int FROM audit_logs audit
  212 |              WHERE audit.model_type ILIKE '%ProfileUpdateRequest'
  213 |                AND audit.model_id = request.id) AS request_audit_count,
  214 |            (SELECT COUNT(*)::int FROM notifications notice
  215 |              WHERE notice.data->>'subject_type' ILIKE '%ProfileUpdateRequest'
  216 |                AND notice.data->>'subject_id' = request.id::text) AS notification_count
  217 |       FROM profile_update_requests request
  218 |       JOIN employees employee ON employee.id = request.employee_id
  219 |       JOIN users requester ON requester.id = request.requested_by
  220 |       LEFT JOIN users hr ON hr.id = request.reviewed_by
  221 |       LEFT JOIN users finance ON finance.id = request.finance_reviewed_by
  222 |      WHERE requester.email = ${sqlText(requesterEmail)}
  223 |        AND request.note = 'Bank account change'
  224 |        AND request.created_at >= ${sqlText(createdAfter)}::timestamptz
  225 |      ORDER BY request.created_at, request.id
  226 |   `);
  227 | }
  228 | 
  229 | async function processAuditsForJourney(requesterEmail: string, createdAfter: string): Promise<ProcessAuditSnapshot[]> {
  230 |   return readOnlyDatabaseJson<ProcessAuditSnapshot[]>(`
  231 |     SELECT request.id AS request_id, audit.action,
  232 |            actor.email AS actor_email,
  233 |            audit.new_values->>'status' AS status
  234 |       FROM audit_logs audit
  235 |       JOIN profile_update_requests request
  236 |         ON audit.model_type ILIKE '%ProfileUpdateRequest'
  237 |        AND audit.model_id = request.id
  238 |       JOIN users requester ON requester.id = request.requested_by
  239 |       LEFT JOIN users actor ON actor.id = audit.user_id
  240 |      WHERE requester.email = ${sqlText(requesterEmail)}
  241 |        AND request.note = 'Bank account change'
  242 |        AND request.created_at >= ${sqlText(createdAfter)}::timestamptz
  243 |      ORDER BY audit.id
  244 |   `);
  245 | }
  246 | 
  247 | async function profileForActor(session: ActorPage, employeeName: string): Promise<void> {
  248 |   await session.page.goto('/dashboard');
  249 |   const profileMenuButton = session.page.getByRole('button', { name: `Profile menu for ${employeeName}`, exact: true });
  250 |   await expect(profileMenuButton).toBeVisible();
  251 |   await profileMenuButton.click();
  252 |   await session.page.getByRole('menu').getByRole('menuitem', { name: 'Profile', exact: true }).click();
  253 |   await expect(session.page.getByRole('heading', { name: /E2E employee/i })).toBeVisible();
  254 |   await expect(session.page.getByRole('heading', { name: 'Bank account', exact: true })).toBeVisible();
  255 | }
  256 | 
  257 | function requestRow(session: ActorPage, employeeName: string, status: string) {
  258 |   return session.page.getByRole('row').filter({ hasText: employeeName }).filter({ hasText: status });
  259 | }
  260 | 
  261 | async function expandRequestRow(session: ActorPage, row: ReturnType<typeof requestRow>): Promise<void> {
  262 |   await expect(row).toHaveCount(1);
  263 |   await row.locator('td').last().click();
> 264 |   await expect(session.page.getByRole('region', { name: 'Requested changes' })).toBeVisible();
      |                                                                                 ^ Error: expect(locator).toBeVisible() failed
  265 | }
  266 | 
  267 | test('HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval', async ({ browser }, testInfo) => {
  268 |   test.setTimeout(180_000);
  269 | 
  270 |   const actors = await loadActors();
  271 |   const employeeUser = actorByName(actors, 'employee');
  272 |   const hrUser = actorByName(actors, 'hr_officer');
  273 |   const financeUser = actorByName(actors, 'finance_officer');
  274 |   const employee = await actorPage(browser, employeeUser.actor);
  275 |   const hr = await actorPage(browser, hrUser.actor);
  276 |   const finance = await actorPage(browser, financeUser.actor);
  277 |   const journey = createJourneyContext('HR07-BANK-CHANGE');
  278 |   const seenIdentifiers = new Set<string>();
  279 |   const bankName = `E2E BANK ${journey.runId.slice(0, 8).toUpperCase()}`;
  280 |   const accountNumber = `E2E${journey.runId.replaceAll('-', '').slice(0, 10).toUpperCase()}7426`;
  281 |   const employeeRows = await readOnlyDatabaseJson<EmployeeBaseline[]>(`
  282 |     SELECT employee.id AS employee_id, employee.employee_no,
  283 |            employee.first_name, employee.last_name, employee.bank_name,
  284 |            employee.bank_account_no AS bank_account_cipher
  285 |       FROM employees employee
  286 |       JOIN users actor ON actor.employee_id = employee.id
  287 |      WHERE actor.email = ${sqlText(employeeUser.email)}
  288 |   `);
  289 |   expect(employeeRows, 'the E2E employee actor should resolve to one seeded employee').toHaveLength(1);
  290 |   const baseline = employeeRows[0];
  291 |   const employeeName = `${baseline.first_name} ${baseline.last_name}`;
  292 |   const mailBefore = await mailpitForActors([employeeUser.email, hrUser.email, financeUser.email]);
  293 |   let requestHashId = '';
  294 | 
  295 |   try {
  296 |     await runProcessStep({
  297 |       name: plannedSteps[0].name,
  298 |       actor: plannedSteps[0].actor,
  299 |       uiPath: plannedSteps[0].uiPath,
  300 |       expectedStatus: plannedSteps[0].expectedStatus,
  301 |       page: employee.page,
  302 |       plannedSteps,
  303 |       context: journey,
  304 |       testInfo,
  305 |       action: async () => {
  306 |         // Start at the authenticated landing page established by UI sign-in;
  307 |         // then follow the profile dropdown's self-service menu item.
  308 |         await employee.page.goto('/dashboard');
  309 |         const profileMenuButton = employee.page.getByRole('button', { name: `Profile menu for ${employeeName}`, exact: true });
  310 |         await expect(profileMenuButton).toBeVisible();
  311 |         await profileMenuButton.click();
  312 |         await employee.page.getByRole('menu').getByRole('menuitem', { name: 'Profile', exact: true }).click();
  313 |         await expect(employee.page.getByRole('heading', { name: /E2E employee/i })).toBeVisible();
  314 |         await expect(employee.page.getByRole('heading', { name: 'Bank account', exact: true })).toBeVisible();
  315 |         await expect(employee.page.getByRole('button', { name: 'Request update', exact: true })).toBeVisible();
  316 |         await expect(employee.page.getByText('No change requests', { exact: true })).toBeVisible();
  317 | 
  318 |         await openQueue(hr, 'Human Resources', 'Profile Change Requests');
  319 |         await expect(hr.page.getByRole('heading', { name: 'No requests', exact: true })).toBeVisible();
  320 |         await openQueue(finance, 'Payroll', 'Bank Account Changes');
  321 |         await expect(finance.page.getByRole('heading', { name: 'No requests', exact: true })).toBeVisible();
  322 |       },
  323 |       verify: async () => {
  324 |         expect(await requestSnapshot(employeeUser.email, journey.startedAt)).toHaveLength(0);
  325 |         expect(baseline.bank_name).toBeNull();
  326 |         expect(baseline.bank_account_cipher).toBeNull();
  327 |       },
  328 |       assertInvariants: async () => {
  329 |         employee.diagnostics.assertClean('employee baseline profile view');
  330 |         hr.diagnostics.assertClean('HR baseline profile queue');
  331 |         finance.diagnostics.assertClean('Finance baseline bank-change queue');
  332 |       },
  333 |     });
  334 | 
  335 |     await runProcessStep({
  336 |       name: plannedSteps[1].name,
  337 |       actor: plannedSteps[1].actor,
  338 |       uiPath: plannedSteps[1].uiPath,
  339 |       expectedStatus: plannedSteps[1].expectedStatus,
  340 |       page: employee.page,
  341 |       plannedSteps,
  342 |       context: journey,
  343 |       testInfo,
  344 |       action: async () => {
  345 |         await employee.page.getByRole('button', { name: 'Request update', exact: true }).click();
  346 |         const dialog = employee.page.getByRole('dialog', { name: 'Request Bank Account Update' });
  347 |         await expect(dialog).toBeVisible();
  348 |         await dialog.getByLabel('Bank name').fill(bankName);
  349 |         await dialog.getByLabel('New account number').fill(accountNumber);
  350 |         const responsePromise = employee.page.waitForResponse((response) =>
  351 |           response.url().includes('/hr/self-service/profile/request-update') && response.request().method() === 'POST',
  352 |         );
  353 |         await dialog.getByRole('button', { name: 'Submit request', exact: true }).click();
  354 |         const response = await responsePromise;
  355 |         expect(response.status(), 'employee UI request should be created').toBe(201);
  356 |         const payload = await response.json() as { data?: { id?: string; status?: string } };
  357 |         requestHashId = payload.data?.id ?? '';
  358 |         expect(requestHashId, 'UI create response should provide the public request identifier').not.toBe('');
  359 |         expect(payload.data?.status).toBe('pending');
  360 |         journey.values.profileUpdateRequestId = requestHashId;
  361 |         journey.values.bankName = bankName;
  362 |         journey.values.accountLast4 = accountNumber.slice(-4);
  363 |         expect(seenIdentifiers.has(requestHashId), 'the UI should not return a duplicate request identifier').toBe(false);
  364 |         seenIdentifiers.add(requestHashId);
```