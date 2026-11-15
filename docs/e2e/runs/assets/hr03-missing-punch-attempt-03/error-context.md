# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-attendance.process.spec.ts >> HR03-MISSING-PUNCH: employee time-in is corrected by HR and the open employee DTR updates live
- Location: e2e-process/hr-attendance.process.spec.ts:605:1

# Error details

```
Error: expect(locator).toContainText(expected) failed

Locator: getByRole('row').filter({ hasText: 'E2E-2FDC0177057D3A5' })
Expected substring: "Missing"
Received string:    "Nov 5, 2026E2E employeeE2E-2FDC0177057D3A5Day Shift6:00 AM—0.000.000.00—Present"
Timeout: 8000ms

Call log:
  - Expect "toContainText" getByRole('row').filter({ hasText: 'E2E-2FDC0177057D3A5' }) with timeout 8000ms
  - waiting for getByRole('row').filter({ hasText: 'E2E-2FDC0177057D3A5' })
    20 × locator resolved to <tr tabindex="0" aria-keyshortcuts="j k ArrowDown ArrowUp Enter Space Control+A Meta+A" class="border-b border-subtle transition-colors duration-fast motion-reduce:transition-none relative group cursor-pointer active:scale-[0.998] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-inset hover:bg-surface hover:z-10 h-row-compact">…</tr>
       - unexpected value "Nov 5, 2026E2E employeeE2E-2FDC0177057D3A5Day Shift6:00 AM—0.000.000.00—Present"

```

```yaml
- row "Nov 5, 2026 E2E employee E2E-2FDC0177057D3A5 Day Shift 6:00 AM — 0.00 0.00 0.00 — Present":
  - cell "Nov 5, 2026"
  - cell "E2E employee E2E-2FDC0177057D3A5"
  - cell "Day Shift"
  - cell "6:00 AM"
  - cell "—"
  - cell "0.00"
  - cell "0.00"
  - cell "0.00"
  - cell "—"
  - cell "Present"
```

# Test source

```ts
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
  596 |   await hr.page.getByRole('combobox', { name: 'Time out', exact: true }).click();
  597 |   await hr.page.getByRole('option', { name: 'Missing time-out', exact: true }).click();
  598 |   expect((await missingResponse).status(), 'HR missing-time-out filter should return the record').toBe(200);
  599 | 
  600 |   const row = hr.page.getByRole('row').filter({ hasText: employeeNo });
  601 |   await expect(row).toHaveCount(1);
> 602 |   await expect(row).toContainText('Missing');
      |                     ^ Error: expect(locator).toContainText(expected) failed
  603 | }
  604 | 
  605 | test('HR03-MISSING-PUNCH: employee time-in is corrected by HR and the open employee DTR updates live', async ({ browser, request }, testInfo) => {
  606 |   const actors = await loadActors();
  607 |   const employeeActor = actorByName(actors, 'employee');
  608 |   const hrActor = actorByName(actors, 'hr_officer');
  609 |   const baseline = await punchBaseline(employeeActor.email);
  610 |   const journey = createJourneyContext('HR03-MISSING-PUNCH');
  611 |   journey.values.employeeNo = baseline.employee_no;
  612 |   journey.values.workDate = baseline.work_date;
  613 |   journey.values.shift = baseline.shift_name;
  614 |   journey.values.shiftStart = baseline.shift_start;
  615 |   journey.values.shiftEnd = baseline.shift_end;
  616 | 
  617 |   const employee = await actorPage(browser, employeeActor.actor, { timezoneId: 'Asia/Manila' });
  618 |   const hr = await actorPage(browser, hrActor.actor, { timezoneId: 'Asia/Manila' });
  619 |   const geoOrigin = new URL(process.env.E2E_BASE_URL!).origin;
  620 |   const mailIdsBefore = (await listMailpitMessages(500)).map((message) => message.ID).sort();
  621 |   let clockFrozen = false;
  622 |   let primaryError: unknown;
  623 | 
  624 |   try {
  625 |     await employee.context.grantPermissions(['geolocation'], { origin: geoOrigin });
  626 |     await employee.context.setGeolocation({
  627 |       latitude: baseline.latitude,
  628 |       longitude: baseline.longitude,
  629 |       accuracy: Math.min(5, baseline.accuracy_cap_m),
  630 |     });
  631 | 
  632 |     await runProcessStep({
  633 |       ...missingPunchSteps[0],
  634 |       page: employee.page,
  635 |       plannedSteps: missingPunchSteps,
  636 |       context: journey,
  637 |       testInfo,
  638 |       action: async () => {
  639 |         await freezeClock(request, localInstant(baseline.work_date, baseline.shift_start));
  640 |         clockFrozen = true;
  641 |         await openEmployeeClock(employee);
  642 |         const responsePromise = employee.page.waitForResponse((response) =>
  643 |           response.url().endsWith('/api/v1/attendance/punch')
  644 |           && response.request().method() === 'POST');
  645 |         await employee.page.getByRole('button', { name: 'Time in', exact: true }).click();
  646 |         const response = await responsePromise;
  647 |         expect(response.status(), 'Employee time-in should create the attendance through the UI').toBe(201);
  648 |       },
  649 |       verify: async (page) => {
  650 |         await expect(page.getByRole('button', { name: 'Time out', exact: true })).toBeEnabled();
  651 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  652 |         expect(snapshot.attendance_status).toBe('present');
  653 |         expect(snapshot.time_in_local).toBe(localDateTime(baseline.work_date, baseline.shift_start));
  654 |         expect(snapshot.time_out_local).toBeNull();
  655 |         expect(snapshot.punches[0].direction).toBe('in');
  656 |         expect(snapshot.punches[0].punched_at_local).toBe(localDateTime(baseline.work_date, baseline.shift_start));
  657 |       },
  658 |       assertInvariants: async () => {
  659 |         employee.diagnostics.assertClean('Employee records a time-in for the missing-punch journey');
  660 |       },
  661 |     });
  662 | 
  663 |     await runProcessStep({
  664 |       ...missingPunchSteps[1],
  665 |       page: hr.page,
  666 |       plannedSteps: missingPunchSteps,
  667 |       context: journey,
  668 |       testInfo,
  669 |       action: async () => {
  670 |         await freezeClock(request, localInstant(nextLocalDate(baseline.work_date), '00:01:00'));
  671 |         await openMissingPunchList(hr, baseline.work_date, baseline.employee_no);
  672 |       },
  673 |       verify: async () => {
  674 |         const row = hr.page.getByRole('row').filter({ hasText: baseline.employee_no });
  675 |         await expect(row).toContainText('Present');
  676 |         await expect(row).toContainText('Missing');
  677 |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_start)));
  678 |       },
  679 |       assertInvariants: async () => {
  680 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  681 |         expect(snapshot.time_out_local).toBeNull();
  682 |         expect(snapshot.overtime_count).toBe(0);
  683 |         expect(snapshot.attendance_notification_count).toBe(0);
  684 |         hr.diagnostics.assertClean('HR finds the previous-day missing time-out through the DTR filters');
  685 |       },
  686 |     });
  687 | 
  688 |     await runProcessStep({
  689 |       ...missingPunchSteps[2],
  690 |       page: employee.page,
  691 |       plannedSteps: missingPunchSteps,
  692 |       context: journey,
  693 |       testInfo,
  694 |       action: async () => openSelfServiceDtr(employee, baseline.work_date),
  695 |       verify: async (page) => {
  696 |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  697 |         await expect(row).toHaveCount(1);
  698 |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_start)));
  699 |       },
  700 |       assertInvariants: async () => {
  701 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  702 |         expect(snapshot.attendance_status).toBe('present');
```