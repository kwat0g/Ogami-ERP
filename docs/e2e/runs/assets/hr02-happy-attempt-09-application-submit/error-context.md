# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-recruitment.process.spec.ts >> HR02-HAPPY — candidate applies through the posting lifecycle and HR hires through interview and offer
- Location: e2e-process/hr-recruitment.process.spec.ts:260:1

# Error details

```
Error: locator.check: Error: strict mode violation: getByRole('checkbox') resolved to 2 elements:
    1) <input type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded-sm border-default accent-accent"/> aka getByRole('checkbox', { name: 'I agree to the Privacy Policy. We use your application only to assess your' })
    2) <input type="checkbox" class="mt-0.5 h-3.5 w-3.5 shrink-0 rounded-sm border-default accent-accent"/> aka getByRole('checkbox', { name: 'I agree to the Privacy Policy.', exact: true })

Call log:
  - waiting for getByRole('checkbox')

```

# Test source

```ts
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
  313 |         await hr.page.locator('[data-layout="page-header"]').getByRole('button', { name: 'New Posting', exact: true }).click();
  314 |         await expect(hr.page.getByRole('heading', { name: 'Create Job Posting', exact: true })).toBeVisible();
  315 |         await hr.page.getByLabel('Job Title').fill(postingTitle);
  316 |         await hr.page.getByRole('combobox', { name: 'Department' }).click();
  317 |         await hr.page.getByRole('option', { name: 'Human Resources', exact: true }).click();
  318 |         await hr.page.getByLabel('Employment Type').selectOption('regular');
  319 |         await hr.page.getByLabel('Slots').fill('1');
  320 |         await hr.page.getByPlaceholder('Describe the role, responsibilities, and day-to-day work...').fill(`Quality role for the E2E recruitment process ${runId}.`);
  321 |         await hr.page.getByPlaceholder('Type and press Enter to add').fill(requirement);
  322 |         await hr.page.getByRole('button', { name: 'Add', exact: true }).click();
  323 |         await hr.page.getByRole('button', { name: 'Create Posting', exact: true }).click();
  324 |         await expect(hr.page.getByText(postingTitle, { exact: true })).toBeVisible();
  325 |         await expect(statusBadge(hr.page, 'Draft')).toBeVisible();
  326 |       },
  327 |       verify: async () => {
  328 |         const rows = await postingSnapshot(postingTitle);
  329 |         expect(rows).toHaveLength(1);
  330 |         expect(rows[0].status).toBe('draft');
  331 |         expect(rows[0].slots).toBe(1);
  332 |         expect(rows[0].created_by_email).toBe(hrActor.email);
  333 |         expect(rows[0].posting_number).toMatch(/.+/);
  334 |         expect(rows[0].audit_entries.some((entry) => entry.actor_email === hrActor.email && entry.action === 'created')).toBe(true);
  335 |         journey.values.postingNumber = rows[0].posting_number;
  336 |         journey.values.postingId = rows[0].id;
  337 |       },
  338 |       assertInvariants: async () => hr.diagnostics.assertClean('HR draft posting creation'),
  339 |     });
  340 | 
  341 |     await runProcessStep({
  342 |       ...plannedSteps[1],
  343 |       page: hr.page,
  344 |       plannedSteps,
  345 |       context: journey,
  346 |       testInfo,
  347 |       action: async () => {
  348 |         await expect(hr.page.getByRole('button', { name: 'Publish', exact: true })).toBeEnabled();
  349 |         await hr.page.getByRole('button', { name: 'Publish', exact: true }).click();
  350 |       },
  351 |       verify: async () => {
  352 |         await expect(statusBadge(hr.page, 'Open')).toBeVisible();
  353 |         await expect(hr.page.getByRole('button', { name: 'Publish', exact: true })).toHaveCount(0);
  354 |         await expect(hr.page.locator('[data-layout="page-header"]').getByRole('button', { name: 'Close', exact: true })).toBeEnabled();
  355 |         const rows = await postingSnapshot(postingTitle);
  356 |         expect(rows).toHaveLength(1);
  357 |         expect(rows[0].status).toBe('open');
  358 |         expect(rows[0].posted_at).toBeTruthy();
  359 |         expect(rows[0].audit_entries.some((entry) => entry.actor_email === hrActor.email && entry.status === 'open')).toBe(true);
  360 |       },
  361 |       assertInvariants: async () => hr.diagnostics.assertClean('HR posting publication'),
  362 |     });
  363 | 
  364 |     await runProcessStep({
  365 |       ...plannedSteps[2],
  366 |       page: applicantPage,
  367 |       plannedSteps,
  368 |       context: journey,
  369 |       testInfo,
  370 |       action: async () => {
  371 |         await applicantPage.goto('/');
  372 |         const careersLink = applicantPage.getByRole('navigation', { name: 'Footer company' }).getByRole('link', { name: 'Careers', exact: true });
  373 |         await careersLink.scrollIntoViewIfNeeded();
  374 |         await careersLink.click();
  375 |         await expect(applicantPage).toHaveURL(/\/careers$/);
  376 |         await applicantPage.getByRole('link', { name: postingTitle, exact: false }).click();
  377 |         await expect(applicantPage.getByRole('heading', { name: postingTitle, exact: true })).toBeVisible();
  378 |         await applicantPage.getByLabel('First Name *').fill(firstName);
  379 |         await applicantPage.getByLabel('Last Name *').fill(lastName);
  380 |         await applicantPage.getByLabel('Email *').fill(candidateEmail);
  381 |         await applicantPage.getByLabel('Phone *').fill(phone);
  382 |         await applicantPage.getByLabel('Cover Letter').fill(`I am applying for the run ${runId} Quality Engineer position.`);
  383 |         await applicantPage.locator('#resume-upload').setInputFiles({
  384 |           name: resumeName,
  385 |           mimeType: 'application/pdf',
  386 |           buffer: Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF'),
  387 |         });
> 388 |         await applicantPage.getByRole('checkbox').check();
      |                                                   ^ Error: locator.check: Error: strict mode violation: getByRole('checkbox') resolved to 2 elements:
  389 |         const responsePromise = applicantPage.waitForResponse((response) => {
  390 |           const url = new URL(response.url());
  391 |           return response.request().method() === 'POST' && url.pathname.endsWith('/apply');
  392 |         });
  393 |         await applicantPage.getByRole('button', { name: 'Submit Application', exact: true }).click();
  394 |         const response = await responsePromise;
  395 |         expect(response.status(), 'the public application form should report a successful creation').toBe(201);
  396 |         await expect(applicantPage.getByRole('heading', { name: 'Application Submitted!', exact: true })).toBeVisible();
  397 |         const trackingCode = (await applicantPage.getByText(/^RCT-[A-Z0-9]{6}$/, { exact: true }).textContent())?.trim();
  398 |         expect(trackingCode).toMatch(/^RCT-[A-Z0-9]{6}$/);
  399 |         journey.values.trackingCode = trackingCode;
  400 |       },
  401 |       verify: async () => {
  402 |         const application = await waitForApplication(candidateEmail, 'new');
  403 |         expect(application.email).toBe(candidateEmail);
  404 |         expect(application.tracking_code).toBe(contextValue<string>(journey, 'trackingCode'));
  405 |         expect(application.consent_at).toBeTruthy();
  406 |         expect(application.resume_original_name).toBe(resumeName);
  407 |         expect(application.application_number).toMatch(/.+/);
  408 |         journey.values.applicationNumber = application.application_number;
  409 |         const posting = await postingSnapshot(postingTitle);
  410 |         expect(posting).toHaveLength(1);
  411 |         expect(posting[0].status).toBe('open');
  412 |         expect(posting[0].application_count).toBe(1);
  413 |         const mail = await newCandidateMail(candidateEmail, primaryMailIdsBefore, `Application received: ${postingTitle}`, [candidateName, postingTitle, application.tracking_code, '/careers/track']);
  414 |         journey.values.applicationReceivedMailId = mail.ID;
  415 |       },
  416 |       assertInvariants: async () => {
  417 |         hr.diagnostics.assertClean('HR view during public application creation');
  418 |         applicantDiagnostics.assertClean('candidate application submission');
  419 |       },
  420 |     });
  421 | 
  422 |     await runProcessStep({
  423 |       ...plannedSteps[3],
  424 |       page: hr.page,
  425 |       plannedSteps,
  426 |       context: journey,
  427 |       testInfo,
  428 |       action: async () => {
  429 |         await openSidebarItem(hr, 'Overview', '/notifications', 'Notifications');
  430 |         await expect(hr.page.getByRole('heading', { name: 'Notifications', exact: true })).toBeVisible();
  431 |         const notificationLink = hr.page.getByRole('link', {
  432 |           name: new RegExp(`New Job Application: ${candidateName} applied for ${postingTitle}`),
  433 |         });
  434 |         await expect(notificationLink).toBeVisible();
  435 |         await notificationLink.click();
  436 |         await expect(hr.page).toHaveURL(/\/hr\/recruitment\/applications\/[A-Za-z0-9]+$/);
  437 |         const applicationId = new URL(hr.page.url()).pathname.split('/').at(-1);
  438 |         if (!applicationId) throw new Error('The notification did not lead to a recruitment application record.');
  439 |         journey.values.applicationHashId = applicationId;
  440 |         await expect(statusBadge(hr.page, 'New')).toBeVisible();
  441 |         await expect(hr.page.getByRole('button', { name: 'Move to Screening', exact: true })).toBeEnabled();
  442 | 
  443 |         await observer.page.goto('/dashboard');
  444 |         await expect(observer.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  445 |         await openSidebarItem(observer, 'Human Resources', '/hr/recruitment', 'Recruitment');
  446 |         await expect(observer.page.getByRole('heading', { name: 'Recruitment', exact: true })).toBeVisible();
  447 |         await observer.page.getByRole('link', { name: 'View all' }).first().click();
  448 |         await expect(observer.page.getByRole('heading', { name: 'Applications', exact: true })).toBeVisible();
  449 |         const applicantRow = observer.page.getByRole('row').filter({ hasText: candidateName });
  450 |         await expect(applicantRow).toBeVisible();
  451 |         await applicantRow.click();
  452 |         await expect(observer.page).toHaveURL(new RegExp(`/hr/recruitment/applications/${applicationId}$`));
  453 |         await expect(statusBadge(observer.page, 'New')).toBeVisible();
  454 |       },
  455 |       verify: async () => {
  456 |         const expectedRecipients = [hrActor.email, adminActor.email];
  457 |         const notices = await applicationNotifications(contextValue<string>(journey, 'applicationHashId'));
  458 |         assertRecipientsExactly(notices.map((notice) => notice.email), expectedRecipients);
  459 |         expect(notices.every((notice) => notice.role_slug === 'hr_officer' || notice.role_slug === 'system_admin')).toBe(true);
  460 |         expect(notices.every((notice) => notice.link_to.endsWith(`/hr/recruitment/applications/${contextValue<string>(journey, 'applicationHashId')}`))).toBe(true);
  461 |         await expect.poll(async () => {
  462 |           const refreshed = await applicationNotifications(contextValue<string>(journey, 'applicationHashId'));
  463 |           return refreshed.find((notice) => notice.email === hrActor.email)?.read_at ?? null;
  464 |         }, { timeout: 10_000, message: 'following the HR notification should mark it as read' }).not.toBeNull();
  465 |         const application = await waitForApplication(candidateEmail, 'new');
  466 |         expect(application.events).toHaveLength(0);
  467 |       },
  468 |       assertInvariants: async () => {
  469 |         hr.diagnostics.assertClean('HR notification hand-off');
  470 |         observer.diagnostics.assertClean('system administrator opens the application as observer');
  471 |       },
  472 |     });
  473 | 
  474 |     await runProcessStep({
  475 |       ...plannedSteps[4],
  476 |       page: hr.page,
  477 |       plannedSteps,
  478 |       context: journey,
  479 |       testInfo,
  480 |       action: async () => {
  481 |         const before = await existingMailIds(candidateEmail);
  482 |         await hr.page.getByRole('button', { name: 'Move to Screening', exact: true }).click();
  483 |         await expect(hr.page.getByRole('dialog', { name: 'Advance to next stage?' })).toBeVisible();
  484 |         await hr.page.getByRole('dialog', { name: 'Advance to next stage?' }).getByRole('button', { name: 'Advance', exact: true }).click();
  485 |         await expect(statusBadge(hr.page, 'Screening')).toBeVisible();
  486 |         await assertLiveStage(observer, 'Screening', 'Move to Interview');
  487 |         await assertStageEvent(candidateEmail, 'new', 'screening', hrActor.email);
  488 |         const mail = await newCandidateMail(candidateEmail, before, `Application update: ${postingTitle}`, [postingTitle, 'Under Review', contextValue<string>(journey, 'trackingCode')]);
```