# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-recruitment.process.spec.ts >> HR02-HAPPY — candidate applies through the posting lifecycle and HR hires through interview and offer
- Location: e2e-process/hr-recruitment.process.spec.ts:260:1

# Error details

```
Error: locator.click: Error: strict mode violation: getByRole('button', { name: 'New Posting', exact: true }) resolved to 2 elements:
    1) <button class="inline-flex items-center justify-center gap-2 rounded-md transition-colors duration-fast cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 disabled:shadow-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 h-7 px-3 text-caption bg-accent text-accent-fg font-medium border-hairline border-transparent hover:bg-accent-hover active:scale-[0.98] transition-colors">…</button> aka getByRole('button', { name: 'New Posting' }).first()
    2) <button class="inline-flex items-center justify-center gap-2 rounded-md transition-colors duration-fast cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 disabled:shadow-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 h-7 px-3 text-caption bg-accent text-accent-fg font-medium border-hairline border-transparent hover:bg-accent-hover active:scale-[0.98] transition-colors">New Posting</button> aka getByRole('button', { name: 'New Posting' }).nth(1)

Call log:
  - waiting for getByRole('button', { name: 'New Posting', exact: true })

```

# Test source

```ts
  213 | async function waitForApplication(email: string, expectedStage: string): Promise<ApplicationSnapshot> {
  214 |   let rows: ApplicationSnapshot[] = [];
  215 |   await expect.poll(async () => {
  216 |     rows = await applicationSnapshot(email);
  217 |     return rows[0]?.stage ?? 'missing';
  218 |   }, { timeout: 15_000, message: `candidate application should reach ${expectedStage}` }).toBe(expectedStage);
  219 |   if (!rows[0]) throw new Error(`Application for ${email} disappeared from the E2E database.`);
  220 |   return rows[0];
  221 | }
  222 | 
  223 | async function newCandidateMail(
  224 |   email: string,
  225 |   excludedIds: Set<string>,
  226 |   subjectFragment: string,
  227 |   expectedBody: string[],
  228 | ): Promise<MailpitSummary> {
  229 |   let matching: MailpitSummary[] = [];
  230 |   await expect.poll(async () => {
  231 |     matching = (await findMailpitMessagesFor(email)).filter(
  232 |       (message) => !excludedIds.has(message.ID) && message.Subject.includes(subjectFragment),
  233 |     );
  234 |     return matching.length;
  235 |   }, { timeout: 25_000, message: `Mailpit should receive ${subjectFragment} for ${email}` }).toBeGreaterThan(0);
  236 | 
  237 |   const message = matching.sort((left, right) => right.Created.localeCompare(left.Created))[0];
  238 |   assertRecipientsExactly(message.To.map((recipient) => recipient.Address), [email]);
  239 |   const contents = JSON.stringify(await getMailpitMessage(message.ID));
  240 |   for (const expected of expectedBody) expect(contents, `${subjectFragment} email should contain ${expected}`).toContain(expected);
  241 |   return message;
  242 | }
  243 | 
  244 | async function existingMailIds(email: string): Promise<Set<string>> {
  245 |   return new Set((await findMailpitMessagesFor(email)).map((message) => message.ID));
  246 | }
  247 | 
  248 | async function assertStageEvent(email: string, from: string, to: string, actorEmail: string): Promise<void> {
  249 |   const application = await waitForApplication(email, to);
  250 |   const event = application.events.find((entry) => entry.event_type === 'stage.advanced' && entry.from_stage === from && entry.to_stage === to);
  251 |   expect(event, `stage event ${from} → ${to} should be persisted`).toBeTruthy();
  252 |   expect(event?.actor_email).toBe(actorEmail);
  253 | }
  254 | 
  255 | async function assertLiveStage(observer: ActorPage, expected: string, availableAction?: string): Promise<void> {
  256 |   await expect(statusBadge(observer.page, expected), `the open observer record should update to ${expected} without refresh`).toBeVisible();
  257 |   if (availableAction) await expect(observer.page.getByRole('button', { name: availableAction, exact: true })).toBeEnabled();
  258 | }
  259 | 
  260 | test('HR02-HAPPY — candidate applies through the posting lifecycle and HR hires through interview and offer', async ({ browser, request }, testInfo) => {
  261 |   test.setTimeout(240_000);
  262 | 
  263 |   const actors = await loadActors();
  264 |   const hrActor = actorByName(actors, 'hr_officer');
  265 |   const adminActor = actorByName(actors, 'system_admin');
  266 |   const clockNow = await readClock(request);
  267 |   const runId = randomUUID().replaceAll('-', '').slice(0, 12).toLowerCase();
  268 |   const postingTitle = `E2E ${runId} Quality Engineer`;
  269 |   const requirement = `Run ${runId}: injection molding quality experience`;
  270 |   const candidateName = `E2E Candidate ${runId}`;
  271 |   const firstName = 'E2E';
  272 |   const lastName = `Candidate ${runId}`;
  273 |   const candidateEmail = `candidate-${runId}@candidate.test`;
  274 |   const phone = '09171234567';
  275 |   const resumeName = `resume-${runId}.pdf`;
  276 |   const interviewerName = `E2E Interviewer ${runId}`;
  277 |   const interviewLocation = `Quality room ${runId}`;
  278 |   const interviewAt = manilaDateTimeInput(new Date(Date.parse(clockNow) + 48 * 60 * 60 * 1000).toISOString());
  279 |   const journey = createJourneyContext('HR02-HAPPY');
  280 |   journey.values.runId = runId;
  281 |   journey.values.postingTitle = postingTitle;
  282 |   journey.values.candidateEmail = candidateEmail;
  283 |   journey.values.candidateName = candidateName;
  284 | 
  285 |   const hr = await actorPage(browser, hrActor.actor, { timezoneId: 'Asia/Manila' });
  286 |   const observer = await actorPage(browser, adminActor.actor, { timezoneId: 'Asia/Manila' });
  287 |   const applicantContext = await browser.newContext({
  288 |     baseURL: process.env.E2E_BASE_URL,
  289 |     ignoreHTTPSErrors: true,
  290 |     timezoneId: 'Asia/Manila',
  291 |   });
  292 |   const applicantPage = await applicantContext.newPage();
  293 |   const applicantDiagnostics = await monitorPage(applicantPage);
  294 |   const primaryMailIdsBefore = await existingMailIds(candidateEmail);
  295 |   let primaryError: unknown;
  296 | 
  297 |   try {
  298 |     await hr.context.clock.setFixedTime(new Date(clockNow));
  299 |     await observer.context.clock.setFixedTime(new Date(clockNow));
  300 |     await applicantContext.clock.setFixedTime(new Date(clockNow));
  301 | 
  302 |     await runProcessStep({
  303 |       ...plannedSteps[0],
  304 |       page: hr.page,
  305 |       plannedSteps,
  306 |       context: journey,
  307 |       testInfo,
  308 |       action: async () => {
  309 |         await hr.page.goto('/dashboard');
  310 |         await expect(hr.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  311 |         await openSidebarItem(hr, 'Human Resources', '/hr/recruitment', 'Recruitment');
  312 |         await expect(hr.page.getByRole('heading', { name: 'Recruitment', exact: true })).toBeVisible();
> 313 |         await hr.page.getByRole('button', { name: 'New Posting', exact: true }).click();
      |                                                                                 ^ Error: locator.click: Error: strict mode violation: getByRole('button', { name: 'New Posting', exact: true }) resolved to 2 elements:
  314 |         await expect(hr.page.getByRole('heading', { name: 'Create Job Posting', exact: true })).toBeVisible();
  315 |         await hr.page.getByLabel('Job Title').fill(postingTitle);
  316 |         await hr.page.getByLabel('Department').selectOption({ label: 'Human Resources' });
  317 |         await hr.page.getByLabel('Employment Type').selectOption('regular');
  318 |         await hr.page.getByLabel('Slots').fill('1');
  319 |         await hr.page.getByPlaceholder('Describe the role, responsibilities, and day-to-day work...').fill(`Quality role for the E2E recruitment process ${runId}.`);
  320 |         await hr.page.getByPlaceholder('Type and press Enter to add').fill(requirement);
  321 |         await hr.page.getByRole('button', { name: 'Add', exact: true }).click();
  322 |         await hr.page.getByRole('button', { name: 'Create Posting', exact: true }).click();
  323 |         await expect(hr.page.getByText(postingTitle, { exact: true })).toBeVisible();
  324 |         await expect(statusBadge(hr.page, 'Draft')).toBeVisible();
  325 |       },
  326 |       verify: async () => {
  327 |         const rows = await postingSnapshot(postingTitle);
  328 |         expect(rows).toHaveLength(1);
  329 |         expect(rows[0].status).toBe('draft');
  330 |         expect(rows[0].slots).toBe(1);
  331 |         expect(rows[0].created_by_email).toBe(hrActor.email);
  332 |         expect(rows[0].posting_number).toMatch(/.+/);
  333 |         expect(rows[0].audit_entries.some((entry) => entry.actor_email === hrActor.email && entry.action === 'created')).toBe(true);
  334 |         journey.values.postingNumber = rows[0].posting_number;
  335 |         journey.values.postingId = rows[0].id;
  336 |       },
  337 |       assertInvariants: async () => hr.diagnostics.assertClean('HR draft posting creation'),
  338 |     });
  339 | 
  340 |     await runProcessStep({
  341 |       ...plannedSteps[1],
  342 |       page: hr.page,
  343 |       plannedSteps,
  344 |       context: journey,
  345 |       testInfo,
  346 |       action: async () => {
  347 |         await expect(hr.page.getByRole('button', { name: 'Publish', exact: true })).toBeEnabled();
  348 |         await hr.page.getByRole('button', { name: 'Publish', exact: true }).click();
  349 |       },
  350 |       verify: async () => {
  351 |         await expect(statusBadge(hr.page, 'Open')).toBeVisible();
  352 |         await expect(hr.page.getByRole('button', { name: 'Publish', exact: true })).toHaveCount(0);
  353 |         await expect(hr.page.getByRole('button', { name: 'Close', exact: true })).toBeEnabled();
  354 |         const rows = await postingSnapshot(postingTitle);
  355 |         expect(rows).toHaveLength(1);
  356 |         expect(rows[0].status).toBe('open');
  357 |         expect(rows[0].posted_at).toBeTruthy();
  358 |         expect(rows[0].audit_entries.some((entry) => entry.actor_email === hrActor.email && entry.status === 'open')).toBe(true);
  359 |       },
  360 |       assertInvariants: async () => hr.diagnostics.assertClean('HR posting publication'),
  361 |     });
  362 | 
  363 |     await runProcessStep({
  364 |       ...plannedSteps[2],
  365 |       page: applicantPage,
  366 |       plannedSteps,
  367 |       context: journey,
  368 |       testInfo,
  369 |       action: async () => {
  370 |         await applicantPage.goto('/');
  371 |         const careersLink = applicantPage.getByRole('navigation', { name: 'Footer company' }).getByRole('link', { name: 'Careers', exact: true });
  372 |         await careersLink.scrollIntoViewIfNeeded();
  373 |         await careersLink.click();
  374 |         await expect(applicantPage).toHaveURL(/\/careers$/);
  375 |         await applicantPage.getByRole('link', { name: postingTitle, exact: false }).click();
  376 |         await expect(applicantPage.getByRole('heading', { name: postingTitle, exact: true })).toBeVisible();
  377 |         await applicantPage.getByLabel('First Name *').fill(firstName);
  378 |         await applicantPage.getByLabel('Last Name *').fill(lastName);
  379 |         await applicantPage.getByLabel('Email *').fill(candidateEmail);
  380 |         await applicantPage.getByLabel('Phone *').fill(phone);
  381 |         await applicantPage.getByLabel('Cover Letter').fill(`I am applying for the run ${runId} Quality Engineer position.`);
  382 |         await applicantPage.locator('#resume-upload').setInputFiles({
  383 |           name: resumeName,
  384 |           mimeType: 'application/pdf',
  385 |           buffer: Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF'),
  386 |         });
  387 |         await applicantPage.getByRole('checkbox').check();
  388 |         const responsePromise = applicantPage.waitForResponse((response) => {
  389 |           const url = new URL(response.url());
  390 |           return response.request().method() === 'POST' && url.pathname.endsWith('/apply');
  391 |         });
  392 |         await applicantPage.getByRole('button', { name: 'Submit Application', exact: true }).click();
  393 |         const response = await responsePromise;
  394 |         expect(response.status(), 'the public application form should report a successful creation').toBe(201);
  395 |         await expect(applicantPage.getByRole('heading', { name: 'Application Submitted!', exact: true })).toBeVisible();
  396 |         const trackingCode = (await applicantPage.getByText(/^RCT-[A-Z0-9]{6}$/, { exact: true }).textContent())?.trim();
  397 |         expect(trackingCode).toMatch(/^RCT-[A-Z0-9]{6}$/);
  398 |         journey.values.trackingCode = trackingCode;
  399 |       },
  400 |       verify: async () => {
  401 |         const application = await waitForApplication(candidateEmail, 'new');
  402 |         expect(application.email).toBe(candidateEmail);
  403 |         expect(application.tracking_code).toBe(contextValue<string>(journey, 'trackingCode'));
  404 |         expect(application.consent_at).toBeTruthy();
  405 |         expect(application.resume_original_name).toBe(resumeName);
  406 |         expect(application.application_number).toMatch(/.+/);
  407 |         journey.values.applicationNumber = application.application_number;
  408 |         const posting = await postingSnapshot(postingTitle);
  409 |         expect(posting).toHaveLength(1);
  410 |         expect(posting[0].status).toBe('open');
  411 |         expect(posting[0].application_count).toBe(1);
  412 |         const mail = await newCandidateMail(candidateEmail, primaryMailIdsBefore, `Application received: ${postingTitle}`, [candidateName, postingTitle, application.tracking_code, '/careers/track']);
  413 |         journey.values.applicationReceivedMailId = mail.ID;
```