# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-attendance.process.spec.ts >> HR03-OVERTIME-APPROVE: employee request reaches its department head and updates the open employee view
- Location: e2e-process/hr-attendance.process.spec.ts:1025:1

# Error details

```
Error: locator.click: Error: strict mode violation: getByRole('button', { name: 'Apply for OT', exact: true }) resolved to 2 elements:
    1) <button class="inline-flex items-center justify-center gap-2 rounded-md transition-colors duration-fast cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 disabled:shadow-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 h-7 px-3 text-caption bg-accent text-accent-fg font-medium border-hairline border-transparent hover:bg-accent-hover active:scale-[0.98] transition-colors">…</button> aka getByRole('button', { name: 'Apply for OT' }).first()
    2) <button class="inline-flex items-center justify-center gap-2 rounded-md transition-colors duration-fast cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 disabled:shadow-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 h-8 px-4 text-md bg-accent text-accent-fg font-medium border-hairline border-transparent hover:bg-accent-hover active:scale-[0.98] transition-colors">…</button> aka getByRole('button', { name: 'Apply for OT' }).nth(1)

Call log:
  - waiting for getByRole('button', { name: 'Apply for OT', exact: true })

```

# Test source

```ts
  964  |       action: async () => undefined,
  965  |       verify: async (page) => {
  966  |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  967  |         await captureAnnotatedStep(page, 'employee', 'Employee DTR remains open after HR correction', 5, testInfo);
  968  |         await expect(row, 'attendance_changed should refresh the open employee DTR without a manual reload')
  969  |           .toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
  970  |         await expect(row).toContainText('7.5');
  971  |       },
  972  |       assertInvariants: async () => {
  973  |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  974  |         expect(snapshot.time_out_local).toBe(localDateTime(baseline.work_date, baseline.shift_end));
  975  |         employee.diagnostics.assertClean('Employee observes HR correction in the already-open DTR');
  976  |         hr.diagnostics.assertClean('HR retains the corrected attendance list');
  977  |         expect((await listMailpitMessages(500)).map((message) => message.ID).sort()).toEqual(mailIdsBefore);
  978  |       },
  979  |     });
  980  | 
  981  |     await runProcessStep({
  982  |       ...missingPunchSteps[5],
  983  |       page: employee.page,
  984  |       plannedSteps: missingPunchSteps,
  985  |       context: journey,
  986  |       testInfo,
  987  |       action: async (page) => { await page.reload(); },
  988  |       verify: async (page) => {
  989  |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  990  |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
  991  |         await expect(row).toContainText('7.5');
  992  |         await expect(row).toContainText('Present');
  993  |       },
  994  |       assertInvariants: async () => {
  995  |         employee.diagnostics.assertClean('Employee confirms saved DTR after reloading');
  996  |       },
  997  |     });
  998  |   } catch (error) {
  999  |     primaryError = error;
  1000 |     throw error;
  1001 |   } finally {
  1002 |     const cleanupErrors: string[] = [];
  1003 |     try {
  1004 |       if (clockFrozen) await clearClock(request);
  1005 |     } catch (error) {
  1006 |       cleanupErrors.push(`clock reset: ${error instanceof Error ? error.message : String(error)}`);
  1007 |     } finally {
  1008 |       const closeResults = await Promise.allSettled([employee.context.close(), hr.context.close()]);
  1009 |       closeResults.forEach((result, index) => {
  1010 |         if (result.status === 'rejected') {
  1011 |           cleanupErrors.push(`${index === 0 ? 'employee' : 'HR'} context close: ${result.reason instanceof Error ? result.reason.message : String(result.reason)}`);
  1012 |         }
  1013 |       });
  1014 |     }
  1015 |     if (cleanupErrors.length > 0) {
  1016 |       await testInfo.attach('journey-cleanup-errors.txt', {
  1017 |         body: cleanupErrors.join('\n'),
  1018 |         contentType: 'text/plain',
  1019 |       });
  1020 |       if (primaryError === undefined) throw new Error(cleanupErrors.join('\n'));
  1021 |     }
  1022 |   }
  1023 | });
  1024 | 
  1025 | test('HR03-OVERTIME-APPROVE: employee request reaches its department head and updates the open employee view', async ({ browser }, testInfo) => {
  1026 |   const actors = await loadActors();
  1027 |   const employeeActor = actorByName(actors, 'employee');
  1028 |   const departmentHeadActor = actorByName(actors, 'department_head');
  1029 |   const baseline = await overtimeBaseline(employeeActor.email);
  1030 |   expect(baseline.existing_request_count, 'the fresh E2E baseline must have no overtime requests for this Employee').toBe(0);
  1031 |   expect(baseline.minimum_hours).toBeGreaterThan(0);
  1032 |   expect(baseline.maximum_hours).toBeGreaterThanOrEqual(baseline.minimum_hours);
  1033 |   expect(baseline.request_future_days).toBeGreaterThanOrEqual(0);
  1034 | 
  1035 |   const expectedSubmissionRecipients = await eligibleOvertimeSubmissionRecipients(employeeActor.email);
  1036 |   assertRecipientsExactly(expectedSubmissionRecipients, [departmentHeadActor.email]);
  1037 | 
  1038 |   const journey = createJourneyContext('HR03-OVERTIME-APPROVE');
  1039 |   const reason = `${journey.values.runPrefix} OT approval journey`;
  1040 |   journey.values.employeeNo = baseline.employee_no;
  1041 |   journey.values.workDate = baseline.work_date;
  1042 |   journey.values.hoursRequested = baseline.minimum_hours.toFixed(1);
  1043 |   journey.values.reason = reason;
  1044 | 
  1045 |   const employee = await actorPage(browser, employeeActor.actor, { timezoneId: 'Asia/Manila' });
  1046 |   const departmentHead = await actorPage(browser, departmentHeadActor.actor, { timezoneId: 'Asia/Manila' });
  1047 |   const mailIdsBefore = (await listMailpitMessages(500)).map((message) => message.ID).sort();
  1048 |   let primaryError: unknown;
  1049 | 
  1050 |   try {
  1051 |     const browserNow = localInstant(baseline.work_date, '12:00:00');
  1052 |     await employee.page.clock.setFixedTime(new Date(browserNow));
  1053 |     await departmentHead.page.clock.setFixedTime(new Date(browserNow));
  1054 | 
  1055 |     await runProcessStep({
  1056 |       ...overtimeSteps[0],
  1057 |       page: employee.page,
  1058 |       plannedSteps: overtimeSteps,
  1059 |       context: journey,
  1060 |       testInfo,
  1061 |       action: async (page) => {
  1062 |         await openSelfServiceOvertime(employee);
  1063 |         await expect(page.getByText('No overtime requests yet', { exact: true })).toBeVisible();
> 1064 |         await page.getByRole('button', { name: 'Apply for OT', exact: true }).click();
       |                                                                               ^ Error: locator.click: Error: strict mode violation: getByRole('button', { name: 'Apply for OT', exact: true }) resolved to 2 elements:
  1065 |         const modal = page.getByRole('dialog', { name: 'Apply for Overtime' });
  1066 |         await expect(modal).toBeVisible();
  1067 |         await modal.getByLabel('Date').fill(baseline.work_date);
  1068 |         await modal.getByRole('button', { name: baseline.minimum_hours.toFixed(1), exact: true }).click();
  1069 |         await modal.getByLabel('Reason').fill(reason);
  1070 |         const submitted = page.waitForResponse((response) =>
  1071 |           new URL(response.url()).pathname === '/api/v1/hr/self-service/overtime'
  1072 |           && response.request().method() === 'POST');
  1073 |         await modal.getByRole('button', { name: 'Submit for approval', exact: true }).click();
  1074 |         const response = await submitted;
  1075 |         const responseText = await response.text();
  1076 |         expect(response.status(), `Employee UI request should submit; response: ${responseText.slice(0, 500)}`).toBe(201);
  1077 |         const responseBody = JSON.parse(responseText) as { data?: { id?: string } };
  1078 |         expect(responseBody.data?.id, 'the UI-created overtime response should include its public record id').toBeTruthy();
  1079 |         journey.values.overtimeId = responseBody.data!.id!;
  1080 |       },
  1081 |       verify: async (page) => {
  1082 |         const row = page.getByRole('row').filter({ hasText: reason });
  1083 |         await expect(row).toHaveCount(1);
  1084 |         await expect(row).toContainText('Pending');
  1085 |         await expect(row.getByRole('button', { name: 'Cancel this overtime request' })).toBeVisible();
  1086 |         await expect(row.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  1087 |       },
  1088 |       assertInvariants: async () => {
  1089 |         const snapshot = await overtimeSnapshot(reason);
  1090 |         expect(snapshot).toHaveLength(1);
  1091 |         expect(snapshot[0]).toMatchObject({
  1092 |           employee_id: baseline.employee_id,
  1093 |           employee_no: baseline.employee_no,
  1094 |           department_id: baseline.department_id,
  1095 |           requester_email: employeeActor.email,
  1096 |           date: baseline.work_date,
  1097 |           hours_requested: baseline.minimum_hours.toFixed(1),
  1098 |           reason,
  1099 |           status: 'pending',
  1100 |           approved_by_email: null,
  1101 |           approved_at: null,
  1102 |           is_auto_detected: false,
  1103 |           attendance_count: 0,
  1104 |         });
  1105 |         const audits = await overtimeAuditRows(reason);
  1106 |         const createdAudit = audits.find((audit) => audit.action === 'created');
  1107 |         expect(createdAudit, 'the overtime request should have a creation audit entry').toBeDefined();
  1108 |         expect(createdAudit!.actor_email).toBe(employeeActor.email);
  1109 | 
  1110 |         const publicId = String(journey.values.overtimeId);
  1111 |         const expectedNotice = `attendance.ot_submitted|${departmentHeadActor.email.toLowerCase()}|/hr/attendance/overtime/${publicId}`;
  1112 |         await expect.poll(async () => (await overtimeNotificationRows(publicId)).map((notice) =>
  1113 |           `${notice.type}|${notice.recipient_email.toLowerCase()}|${notice.link_to}`).sort(), {
  1114 |           timeout: 15_000,
  1115 |           message: 'only the eligible same-department approver should receive the linked overtime submission notice',
  1116 |         }).toEqual([expectedNotice]);
  1117 |         const notices = await overtimeNotificationRows(publicId);
  1118 |         assertRecipientsExactly(notices.map((notice) => notice.recipient_email), [departmentHeadActor.email]);
  1119 |         expect(notices[0].link_to).toBe(`/hr/attendance/overtime/${publicId}`);
  1120 |         employee.diagnostics.assertClean('Employee submits an overtime request through self-service');
  1121 |       },
  1122 |     });
  1123 | 
  1124 |     await runProcessStep({
  1125 |       ...overtimeSteps[1],
  1126 |       page: departmentHead.page,
  1127 |       plannedSteps: overtimeSteps,
  1128 |       context: journey,
  1129 |       testInfo,
  1130 |       action: async (page) => {
  1131 |         await openOvertimeApprovalSignal(departmentHead);
  1132 |         const queueRow = page.getByRole('row').filter({ hasText: reason });
  1133 |         await expect(queueRow).toHaveCount(1);
  1134 |         await expect(queueRow).toContainText('Pending');
  1135 |         await queueRow.click();
  1136 |         await expect(page).toHaveURL(/\/hr\/attendance\/overtime\/[^/?]+(?:\?|$)/);
  1137 |         const heading = page.getByRole('heading', { name: /Overtime request/ });
  1138 |         await expect(heading).toContainText('Pending');
  1139 |         await expect(page.getByRole('button', { name: 'Approve', exact: true })).toBeEnabled();
  1140 |         await expect(page.getByRole('button', { name: 'Reject', exact: true })).toBeEnabled();
  1141 |         await expect(page.getByRole('button', { name: 'Cancel request', exact: true })).toBeEnabled();
  1142 |         await page.getByRole('button', { name: 'Approve', exact: true }).click();
  1143 |         const confirmation = page.getByRole('dialog', { name: 'Approve overtime request?' });
  1144 |         await expect(confirmation).toBeVisible();
  1145 |         const approved = page.waitForResponse((response) =>
  1146 |           /\/api\/v1\/attendance\/overtime-requests\/[^/]+\/approve$/.test(new URL(response.url()).pathname)
  1147 |           && response.request().method() === 'PATCH');
  1148 |         await confirmation.getByRole('button', { name: 'Approve', exact: true }).click();
  1149 |         const response = await approved;
  1150 |         const responseText = await response.text();
  1151 |         expect(response.status(), `Department Head UI approval should succeed; response: ${responseText.slice(0, 500)}`).toBe(200);
  1152 |       },
  1153 |       verify: async (page) => {
  1154 |         const heading = page.getByRole('heading', { name: /Overtime request/ });
  1155 |         await expect(heading).toContainText('Approved');
  1156 |         await expect(page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  1157 |         await expect(page.getByRole('button', { name: 'Reject', exact: true })).toHaveCount(0);
  1158 |         await expect(page.getByRole('button', { name: 'Cancel request', exact: true })).toHaveCount(0);
  1159 |         await expect(page.getByRole('button', { name: /^View all Overtime signals:/ })).toHaveCount(0);
  1160 | 
  1161 |         const observerRow = employee.page.getByRole('row').filter({ hasText: reason });
  1162 |         await captureAnnotatedStep(employee.page, 'employee', 'Employee sees approved overtime without refreshing', 3, testInfo);
  1163 |         await expect(observerRow, 'the already-open Employee overtime list should reflect the Department Head decision live')
  1164 |           .toHaveCount(1);
```