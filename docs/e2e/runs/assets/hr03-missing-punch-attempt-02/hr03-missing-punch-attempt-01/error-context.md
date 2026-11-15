# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-attendance.process.spec.ts >> HR03-MISSING-PUNCH: employee time-in is corrected by HR and the open employee DTR updates live
- Location: e2e-process/hr-attendance.process.spec.ts:604:1

# Error details

```
Error: page.waitForResponse: Target page, context or browser has been closed
```

```
Error: locator.selectOption: Error: strict mode violation: getByLabel('Time out') resolved to 5 elements:
    1) <button disabled type="button" aria-expanded="true" aria-controls=":r2:-desktop-human-resources" aria-label="Human Resources: Past attendance records with a time in but no time out: 1" class="flex min-h-11 w-full items-center gap-2 rounded-md px-4 py-1.5 text-left text-sm font-normal transition-colors duration-fast md:min-h-9 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-inset font-medium text-primary">…</button> aka getByRole('button', { name: 'Human Resources: Past' })
    2) <button type="button" aria-controls=":rg:" aria-expanded="false" aria-haspopup="dialog" aria-label="View all Attendance & DTR signals: 1 Past attendance records with a time in but no time out" class="z-10 flex shrink-0 cursor-pointer items-center justify-center rounded p-1 transition-colors hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent ml-1">…</button> aka getByRole('button', { name: 'View all Attendance & DTR' })
    3) <select tabindex="-1" class="sr-only" aria-hidden="true" aria-label="Time out">…</select> aka getByLabel('Time out').nth(2)
    4) <input id="select-r10" role="combobox" autocomplete="off" aria-invalid="false" aria-label="Time out" aria-expanded="false" value="Time out: All" aria-controls="r11-list" aria-autocomplete="list" placeholder="Search or select…" class="flex-1 min-w-0 bg-transparent placeholder:text-subtle outline-none px-3 text-md"/> aka getByRole('combobox', { name: 'Time out' })
    5) <button type="button" tabindex="-1" aria-label="Show Time out" class="flex h-full items-center">…</button> aka getByRole('button', { name: 'Show Time out' })

Call log:
  - waiting for getByLabel('Time out')

```

# Test source

```ts
  496 |       page: employee.page,
  497 |       plannedSteps,
  498 |       context: journey,
  499 |       testInfo,
  500 |       action: () => openSelfServiceDtr(employee, baseline.work_date),
  501 |       verify: async (page) => {
  502 |         const dateRow = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  503 |         await expect(dateRow).toHaveCount(1);
  504 |         await expect(dateRow).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_start)));
  505 |         await expect(dateRow).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
  506 |         await expect(dateRow).toContainText('7.5');
  507 |         await expect(dateRow).toContainText('Present');
  508 |       },
  509 |       assertInvariants: async () => {
  510 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 2);
  511 |         expect(snapshot.attendance_id).toBeGreaterThan(0);
  512 |         expect(snapshot.audits).toHaveLength(4);
  513 |         expect(snapshot.punches.every((punch) => punch.accuracy_m <= baseline.accuracy_cap_m)).toBe(true);
  514 |         expect(snapshot.punches.every((punch) => punch.distance_m <= baseline.radius_m)).toBe(true);
  515 |         expect(snapshot.attendance_notification_count).toBe(0);
  516 |         const mailIdsAfter = (await listMailpitMessages(500)).map((message) => message.ID).sort();
  517 |         expect(mailIdsAfter).toEqual(mailIdsBefore);
  518 |         const mailpitEmployeeIdsAfter = (await listMailpitMessages(500))
  519 |           .filter((message) => message.To.some((recipient) => recipient.Address.toLowerCase() === employeeActor.email.toLowerCase()))
  520 |           .map((message) => message.ID)
  521 |           .sort();
  522 |         expect(mailpitEmployeeIdsAfter).toEqual(mailpitEmployeeIdsBefore);
  523 |         employee.diagnostics.assertClean('Employee reviews the completed DTR');
  524 |         hr.diagnostics.assertClean('HR retains the completed attendance view');
  525 |       },
  526 |     });
  527 |   } catch (error) {
  528 |     primaryError = error;
  529 |     throw error;
  530 |   } finally {
  531 |     const cleanupErrors: string[] = [];
  532 |     try {
  533 |       if (clockFrozen) await clearClock(request);
  534 |     } catch (error) {
  535 |       cleanupErrors.push(`clock reset: ${error instanceof Error ? error.message : String(error)}`);
  536 |     } finally {
  537 |       const closeResults = await Promise.allSettled([employee.context.close(), hr.context.close()]);
  538 |       closeResults.forEach((result, index) => {
  539 |         if (result.status === 'rejected') {
  540 |           cleanupErrors.push(`${index === 0 ? 'employee' : 'HR'} context close: ${result.reason instanceof Error ? result.reason.message : String(result.reason)}`);
  541 |         }
  542 |       });
  543 |     }
  544 |     if (cleanupErrors.length > 0) {
  545 |       await testInfo.attach('journey-cleanup-errors.txt', {
  546 |         body: cleanupErrors.join('\n'),
  547 |         contentType: 'text/plain',
  548 |       });
  549 |       if (primaryError === undefined) throw new Error(cleanupErrors.join('\n'));
  550 |     }
  551 |   }
  552 | });
  553 | 
  554 | const missingPunchSteps = [
  555 |   { name: 'Employee records time in for a scheduled shift', actor: 'employee', uiPath: 'Profile menu → Dashboard → Time in / out → Time in', expectedStatus: 'present with time_in; time_out is empty; one geofenced punch is audited' },
  556 |   { name: 'HR finds the prior-day record in Missing time-out', actor: 'hr_officer', uiPath: 'Human Resources → Attendance & DTR → Date and Time out filters', expectedStatus: 'the employee row shows Missing and is correctable' },
  557 |   { name: 'Employee opens the DTR as the correction observer', actor: 'employee', uiPath: 'Profile menu → DTR', expectedStatus: 'the same date shows the existing time-in and no time-out' },
  558 |   { name: 'HR corrects the missing time-out through the attendance row', actor: 'hr_officer', uiPath: 'Attendance & DTR → employee row → Correct record → Save correction', expectedStatus: 'present; time_out and regular hours are recomputed; HR actor is audited' },
  559 |   { name: 'Employee DTR receives the correction without refresh', actor: 'employee observer', uiPath: 'Already-open Profile menu → DTR', expectedStatus: 'time-out and recomputed regular hours appear live' },
  560 |   { name: 'Employee reloads DTR and confirms the saved correction', actor: 'employee', uiPath: 'Profile menu → DTR', expectedStatus: 'the refreshed row matches the corrected attendance record' },
  561 | ];
  562 | 
  563 | async function openMissingPunchList(hr: ActorPage, date: string, employeeNo: string): Promise<void> {
  564 |   await hr.page.goto('/dashboard');
  565 |   await expect(hr.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  566 |   const nav = hr.page.getByRole('navigation', { name: 'Modules' });
  567 |   const attendanceLink = nav.locator('a[href="/hr/attendance"]');
  568 |   if (!(await attendanceLink.isVisible())) {
  569 |     const section = hr.page.locator('[data-module="Human Resources"]');
  570 |     const toggle = section.getByRole('button');
  571 |     if (await toggle.count() !== 1) throw new Error('Human Resources sidebar section did not resolve uniquely.');
  572 |     if (await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
  573 |   }
  574 |   await expect(attendanceLink).toBeVisible();
  575 |   await attendanceLink.click();
  576 |   await expect(hr.page).toHaveURL(/\/hr\/attendance(?:\?|$)/);
  577 |   await expect(hr.page.getByRole('heading', { name: 'Daily Time Records', exact: true })).toBeVisible();
  578 | 
  579 |   await hr.page.getByLabel('Date from').fill(date);
  580 |   const dateResponse = hr.page.waitForResponse((response) => {
  581 |     const url = new URL(response.url());
  582 |     return url.pathname.endsWith('/api/v1/attendance/attendances')
  583 |       && url.searchParams.get('from') === date
  584 |       && url.searchParams.get('to') === date;
  585 |   });
  586 |   await hr.page.getByLabel('Date to').fill(date);
  587 |   expect((await dateResponse).status(), 'HR date-filtered DTR list should be readable').toBe(200);
  588 | 
  589 |   const missingResponse = hr.page.waitForResponse((response) => {
  590 |     const url = new URL(response.url());
  591 |     return url.pathname.endsWith('/api/v1/attendance/attendances')
  592 |       && url.searchParams.get('from') === date
  593 |       && url.searchParams.get('to') === date
  594 |       && url.searchParams.get('missing_time_out') === '1';
  595 |   });
> 596 |   await hr.page.getByLabel('Time out').selectOption('1');
      |                                        ^ Error: locator.selectOption: Error: strict mode violation: getByLabel('Time out') resolved to 5 elements:
  597 |   expect((await missingResponse).status(), 'HR missing-time-out filter should return the record').toBe(200);
  598 | 
  599 |   const row = hr.page.getByRole('row').filter({ hasText: employeeNo });
  600 |   await expect(row).toHaveCount(1);
  601 |   await expect(row).toContainText('Missing');
  602 | }
  603 | 
  604 | test('HR03-MISSING-PUNCH: employee time-in is corrected by HR and the open employee DTR updates live', async ({ browser, request }, testInfo) => {
  605 |   const actors = await loadActors();
  606 |   const employeeActor = actorByName(actors, 'employee');
  607 |   const hrActor = actorByName(actors, 'hr_officer');
  608 |   const baseline = await punchBaseline(employeeActor.email);
  609 |   const journey = createJourneyContext('HR03-MISSING-PUNCH');
  610 |   journey.values.employeeNo = baseline.employee_no;
  611 |   journey.values.workDate = baseline.work_date;
  612 |   journey.values.shift = baseline.shift_name;
  613 |   journey.values.shiftStart = baseline.shift_start;
  614 |   journey.values.shiftEnd = baseline.shift_end;
  615 | 
  616 |   const employee = await actorPage(browser, employeeActor.actor, { timezoneId: 'Asia/Manila' });
  617 |   const hr = await actorPage(browser, hrActor.actor, { timezoneId: 'Asia/Manila' });
  618 |   const geoOrigin = new URL(process.env.E2E_BASE_URL!).origin;
  619 |   const mailIdsBefore = (await listMailpitMessages(500)).map((message) => message.ID).sort();
  620 |   let clockFrozen = false;
  621 |   let primaryError: unknown;
  622 | 
  623 |   try {
  624 |     await employee.context.grantPermissions(['geolocation'], { origin: geoOrigin });
  625 |     await employee.context.setGeolocation({
  626 |       latitude: baseline.latitude,
  627 |       longitude: baseline.longitude,
  628 |       accuracy: Math.min(5, baseline.accuracy_cap_m),
  629 |     });
  630 | 
  631 |     await runProcessStep({
  632 |       ...missingPunchSteps[0],
  633 |       page: employee.page,
  634 |       plannedSteps: missingPunchSteps,
  635 |       context: journey,
  636 |       testInfo,
  637 |       action: async () => {
  638 |         await freezeClock(request, localInstant(baseline.work_date, baseline.shift_start));
  639 |         clockFrozen = true;
  640 |         await openEmployeeClock(employee);
  641 |         const responsePromise = employee.page.waitForResponse((response) =>
  642 |           response.url().endsWith('/api/v1/attendance/punch')
  643 |           && response.request().method() === 'POST');
  644 |         await employee.page.getByRole('button', { name: 'Time in', exact: true }).click();
  645 |         const response = await responsePromise;
  646 |         expect(response.status(), 'Employee time-in should create the attendance through the UI').toBe(201);
  647 |       },
  648 |       verify: async (page) => {
  649 |         await expect(page.getByRole('button', { name: 'Time out', exact: true })).toBeEnabled();
  650 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  651 |         expect(snapshot.attendance_status).toBe('present');
  652 |         expect(snapshot.time_in_local).toBe(localDateTime(baseline.work_date, baseline.shift_start));
  653 |         expect(snapshot.time_out_local).toBeNull();
  654 |         expect(snapshot.punches[0].direction).toBe('in');
  655 |         expect(snapshot.punches[0].punched_at_local).toBe(localDateTime(baseline.work_date, baseline.shift_start));
  656 |       },
  657 |       assertInvariants: async () => {
  658 |         employee.diagnostics.assertClean('Employee records a time-in for the missing-punch journey');
  659 |       },
  660 |     });
  661 | 
  662 |     await runProcessStep({
  663 |       ...missingPunchSteps[1],
  664 |       page: hr.page,
  665 |       plannedSteps: missingPunchSteps,
  666 |       context: journey,
  667 |       testInfo,
  668 |       action: async () => {
  669 |         await freezeClock(request, localInstant(nextLocalDate(baseline.work_date), '00:01:00'));
  670 |         await openMissingPunchList(hr, baseline.work_date, baseline.employee_no);
  671 |       },
  672 |       verify: async () => {
  673 |         const row = hr.page.getByRole('row').filter({ hasText: baseline.employee_no });
  674 |         await expect(row).toContainText('Present');
  675 |         await expect(row).toContainText('Missing');
  676 |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_start)));
  677 |       },
  678 |       assertInvariants: async () => {
  679 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  680 |         expect(snapshot.time_out_local).toBeNull();
  681 |         expect(snapshot.overtime_count).toBe(0);
  682 |         expect(snapshot.attendance_notification_count).toBe(0);
  683 |         hr.diagnostics.assertClean('HR finds the previous-day missing time-out through the DTR filters');
  684 |       },
  685 |     });
  686 | 
  687 |     await runProcessStep({
  688 |       ...missingPunchSteps[2],
  689 |       page: employee.page,
  690 |       plannedSteps: missingPunchSteps,
  691 |       context: journey,
  692 |       testInfo,
  693 |       action: async () => openSelfServiceDtr(employee, baseline.work_date),
  694 |       verify: async (page) => {
  695 |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  696 |         await expect(row).toHaveCount(1);
```