# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-attendance.process.spec.ts >> HR03-MISSING-PUNCH: employee time-in is corrected by HR and the open employee DTR updates live
- Location: e2e-process/hr-attendance.process.spec.ts:793:1

# Error details

```
Error: expect(received).toEqual(expected) // deep equality

- Expected  -  1
+ Received  + 14

- Array []
+ Array [
+   "0puymNDw0cnHeWW6ozKtuv",
+   "0wJV9fafS518zHpbqVDNJ2",
+   "1cmSAr1LCxVHhdMqKL8AYP",
+   "1fyEtBvp8qjiPUgh5q3aBG",
+   "1il4nTQ74MgTowdbS1ZJEd",
+   "1nbrcMKrrEKc2AgE3xAFWz",
+   "2N7sI10DkAnbH3r13GRzsA",
+   "4Amx2xrkLKIUGYTIxPVKz6",
+   "5VTI0ZCIOwudsobT6Nstf7",
+   "62dk6ptXkzUHRIXs4yTsfw",
+   "6cBuZFMqBBx2xZ1ZXov72Z",
+   "705Tk9WtmGRYMelxrmU4nR",
+ ]
```

# Test source

```ts
  881  |       page: employee.page,
  882  |       plannedSteps: missingPunchSteps,
  883  |       context: journey,
  884  |       testInfo,
  885  |       action: async () => {
  886  |         await employee.page.clock.setFixedTime(new Date(localInstant(nextLocalDate(baseline.work_date), '00:01:00')));
  887  |         await openSelfServiceDtr(employee, baseline.work_date);
  888  |       },
  889  |       verify: async (page) => {
  890  |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  891  |         await expect(row).toHaveCount(1);
  892  |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_start)));
  893  |       },
  894  |       assertInvariants: async () => {
  895  |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  896  |         expect(snapshot.attendance_status).toBe('present');
  897  |         expect(snapshot.time_out_local).toBeNull();
  898  |         employee.diagnostics.assertClean('Employee opens DTR while HR prepares the correction');
  899  |       },
  900  |     });
  901  | 
  902  |     await runProcessStep({
  903  |       ...missingPunchSteps[3],
  904  |       page: hr.page,
  905  |       plannedSteps: missingPunchSteps,
  906  |       context: journey,
  907  |       testInfo,
  908  |       action: async (page) => {
  909  |         const row = page.getByRole('row').filter({ hasText: baseline.employee_no });
  910  |         await row.click();
  911  |         const modal = page.getByRole('dialog', { name: 'Correct attendance record' });
  912  |         await expect(modal).toBeVisible();
  913  |         await expect(modal.getByLabel('Time in')).toHaveValue(baseline.shift_start.slice(0, 5));
  914  |         await expect(modal.getByLabel('Time out')).toHaveValue('');
  915  |         await modal.getByLabel('Time out').fill(baseline.shift_end.slice(0, 5));
  916  |         await modal.getByLabel('Remarks').fill(`E2E ${journey.runId}: correct missed time-out from shift record`);
  917  |         const correction = page.waitForResponse((response) =>
  918  |           response.url().match(/\/api\/v1\/attendance\/attendances\/[^/]+$/) !== null
  919  |           && response.request().method() === 'PUT');
  920  |         await modal.getByRole('button', { name: 'Save correction', exact: true }).click();
  921  |         const response = await correction;
  922  |         const body = await response.text();
  923  |         expect(response.status(), `HR correction submitted through the UI should succeed; response: ${body.slice(0, 500)}`).toBe(200);
  924  |       },
  925  |       verify: async (page) => {
  926  |         await expect(page.getByRole('dialog', { name: 'Correct attendance record' })).toHaveCount(0);
  927  |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  928  |         expect(snapshot.attendance_status).toBe('present');
  929  |         expect(snapshot.time_in_local).toBe(localDateTime(baseline.work_date, baseline.shift_start));
  930  |         expect(snapshot.time_out_local).toBe(localDateTime(baseline.work_date, baseline.shift_end));
  931  |         expect(snapshot.regular_hours).toBe('7.50');
  932  |         expect(snapshot.overtime_hours).toBe('0.00');
  933  |         expect(snapshot.audits.some((audit) => audit.actor_email?.toLowerCase() === hrActor.email.toLowerCase())).toBe(true);
  934  |         journey.values.correctedAttendance = {
  935  |           status: snapshot.attendance_status,
  936  |           timeIn: snapshot.time_in_local,
  937  |           timeOut: snapshot.time_out_local,
  938  |           regularHours: snapshot.regular_hours,
  939  |           overtimeHours: snapshot.overtime_hours,
  940  |           correctionAuditActor: hrActor.email,
  941  |         };
  942  |         const row = page.getByRole('row').filter({ hasText: baseline.employee_no });
  943  |         await expect(row, 'a corrected record should leave the active Missing time-out filter').toHaveCount(0);
  944  |         await expect(page.getByText('No attendance found', { exact: true })).toBeVisible();
  945  |       },
  946  |       assertInvariants: async () => {
  947  |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  948  |         expect(snapshot.attendance_status).toBe('present');
  949  |         expect(snapshot.time_in_local).toBe(localDateTime(baseline.work_date, baseline.shift_start));
  950  |         expect(snapshot.time_out_local).toBe(localDateTime(baseline.work_date, baseline.shift_end));
  951  |         expect(snapshot.regular_hours).toBe('7.50');
  952  |         expect(snapshot.overtime_hours).toBe('0.00');
  953  |         expect(snapshot.tardiness_minutes).toBe(0);
  954  |         expect(snapshot.undertime_minutes).toBe(0);
  955  |         expect(snapshot.punches).toHaveLength(1);
  956  |         expect(snapshot.attendance_notification_count).toBe(0);
  957  |         expect(snapshot.audits.some((audit) => audit.actor_email?.toLowerCase() === hrActor.email.toLowerCase())).toBe(true);
  958  |         hr.diagnostics.assertClean('HR saves the attendance correction through its own session');
  959  |       },
  960  |     });
  961  | 
  962  |     await runProcessStep({
  963  |       ...missingPunchSteps[4],
  964  |       page: employee.page,
  965  |       plannedSteps: missingPunchSteps,
  966  |       context: journey,
  967  |       testInfo,
  968  |       action: async () => undefined,
  969  |       verify: async (page) => {
  970  |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  971  |         await captureAnnotatedStep(page, 'employee', 'Employee DTR remains open after HR correction', 5, testInfo);
  972  |         await expect(row, 'attendance_changed should refresh the open employee DTR without a manual reload')
  973  |           .toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
  974  |         await expect(row).toContainText('7.5');
  975  |       },
  976  |       assertInvariants: async () => {
  977  |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  978  |         expect(snapshot.time_out_local).toBe(localDateTime(baseline.work_date, baseline.shift_end));
  979  |         employee.diagnostics.assertClean('Employee observes HR correction in the already-open DTR');
  980  |         hr.diagnostics.assertClean('HR retains the corrected attendance list');
> 981  |         expect((await listMailpitMessages(500)).map((message) => message.ID).sort()).toEqual(mailIdsBefore);
       |                                                                                      ^ Error: expect(received).toEqual(expected) // deep equality
  982  |       },
  983  |     });
  984  | 
  985  |     await runProcessStep({
  986  |       ...missingPunchSteps[5],
  987  |       page: employee.page,
  988  |       plannedSteps: missingPunchSteps,
  989  |       context: journey,
  990  |       testInfo,
  991  |       action: async (page) => { await page.reload(); },
  992  |       verify: async (page) => {
  993  |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  994  |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
  995  |         await expect(row).toContainText('7.5');
  996  |         await expect(row).toContainText('Present');
  997  |       },
  998  |       assertInvariants: async () => {
  999  |         employee.diagnostics.assertClean('Employee confirms saved DTR after reloading');
  1000 |       },
  1001 |     });
  1002 |   } catch (error) {
  1003 |     primaryError = error;
  1004 |     throw error;
  1005 |   } finally {
  1006 |     const cleanupErrors: string[] = [];
  1007 |     try {
  1008 |       if (clockFrozen) await clearClock(request);
  1009 |     } catch (error) {
  1010 |       cleanupErrors.push(`clock reset: ${error instanceof Error ? error.message : String(error)}`);
  1011 |     } finally {
  1012 |       const closeResults = await Promise.allSettled([employee.context.close(), hr.context.close()]);
  1013 |       closeResults.forEach((result, index) => {
  1014 |         if (result.status === 'rejected') {
  1015 |           cleanupErrors.push(`${index === 0 ? 'employee' : 'HR'} context close: ${result.reason instanceof Error ? result.reason.message : String(result.reason)}`);
  1016 |         }
  1017 |       });
  1018 |     }
  1019 |     if (cleanupErrors.length > 0) {
  1020 |       await testInfo.attach('journey-cleanup-errors.txt', {
  1021 |         body: cleanupErrors.join('\n'),
  1022 |         contentType: 'text/plain',
  1023 |       });
  1024 |       if (primaryError === undefined) throw new Error(cleanupErrors.join('\n'));
  1025 |     }
  1026 |   }
  1027 | });
  1028 | 
  1029 | test('HR03-OVERTIME-APPROVE: employee request reaches its department head and updates the open employee view', async ({ browser }, testInfo) => {
  1030 |   const actors = await loadActors();
  1031 |   const employeeActor = actorByName(actors, 'employee');
  1032 |   const departmentHeadActor = actorByName(actors, 'department_head');
  1033 |   const baseline = await overtimeBaseline(employeeActor.email);
  1034 |   expect(baseline.existing_request_count, 'the fresh E2E baseline must have no overtime requests for this Employee').toBe(0);
  1035 |   expect(baseline.minimum_hours).toBeGreaterThan(0);
  1036 |   expect(baseline.maximum_hours).toBeGreaterThanOrEqual(baseline.minimum_hours);
  1037 |   expect(baseline.request_future_days).toBeGreaterThanOrEqual(0);
  1038 | 
  1039 |   const expectedSubmissionRecipients = await eligibleOvertimeSubmissionRecipients(employeeActor.email);
  1040 |   assertRecipientsExactly(expectedSubmissionRecipients, [departmentHeadActor.email]);
  1041 | 
  1042 |   const journey = createJourneyContext('HR03-OVERTIME-APPROVE');
  1043 |   const reason = `${journey.values.runPrefix} OT approval journey`;
  1044 |   journey.values.employeeNo = baseline.employee_no;
  1045 |   journey.values.workDate = baseline.work_date;
  1046 |   journey.values.hoursRequested = baseline.minimum_hours.toFixed(1);
  1047 |   journey.values.reason = reason;
  1048 | 
  1049 |   const employee = await actorPage(browser, employeeActor.actor, { timezoneId: 'Asia/Manila' });
  1050 |   const departmentHead = await actorPage(browser, departmentHeadActor.actor, { timezoneId: 'Asia/Manila' });
  1051 |   const mailIdsBefore = (await listMailpitMessages(500)).map((message) => message.ID).sort();
  1052 |   let primaryError: unknown;
  1053 | 
  1054 |   try {
  1055 |     const browserNow = localInstant(baseline.work_date, '12:00:00');
  1056 |     await employee.page.clock.setFixedTime(new Date(browserNow));
  1057 |     await departmentHead.page.clock.setFixedTime(new Date(browserNow));
  1058 | 
  1059 |     await runProcessStep({
  1060 |       ...overtimeSteps[0],
  1061 |       page: employee.page,
  1062 |       plannedSteps: overtimeSteps,
  1063 |       context: journey,
  1064 |       testInfo,
  1065 |       action: async (page) => {
  1066 |         await openSelfServiceOvertime(employee);
  1067 |         await expect(page.getByText('No overtime requests yet', { exact: true })).toBeVisible();
  1068 |         const applyAction = page.locator('[data-layout="page-header"]').getByRole('button', { name: 'Apply for OT', exact: true });
  1069 |         await expect(applyAction, 'the page header should expose one Apply for OT action').toHaveCount(1);
  1070 |         await expect(applyAction).toBeEnabled();
  1071 |         await applyAction.click();
  1072 |         const modal = page.getByRole('dialog', { name: 'Apply for Overtime' });
  1073 |         await expect(modal).toBeVisible();
  1074 |         await modal.getByLabel('Date').fill(baseline.work_date);
  1075 |         await modal.getByRole('button', { name: baseline.minimum_hours.toFixed(1), exact: true }).click();
  1076 |         await modal.getByLabel('Reason').fill(reason);
  1077 |         const submitted = page.waitForResponse((response) =>
  1078 |           new URL(response.url()).pathname === '/api/v1/hr/self-service/overtime'
  1079 |           && response.request().method() === 'POST');
  1080 |         await modal.getByRole('button', { name: 'Submit for approval', exact: true }).click();
  1081 |         const response = await submitted;
```