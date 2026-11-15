# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-leave.process.spec.ts >> HR04-CANCEL-APPROVED — Employee cancels approved leave and restores balance and attendance
- Location: e2e-process/hr-leave.process.spec.ts:1320:1

# Error details

```
Error: expect(received).toEqual(expected) // deep equality

- Expected  - 3
+ Received  + 1

- Array [
-   "leave.submitted",
- ]
+ Array []
```

# Test source

```ts
  889  |         assertAuditActor({ actor_email: rejected?.actor_email ?? undefined }, rejectActorUser.email);
  890  |         await expect.poll(async () => rejectActor.page.getByRole('button', { name: /^View all Leave Management signals/ }).count()).toBe(0);
  891  |         await expect.poll(async () => hr.page.getByRole('button', { name: /^View all Leave Management signals/ }).count()).toBe(0);
  892  |         await expect.poll(async () => employee.page.getByRole('heading')
  893  |           .filter({ hasText: String(journey.values.leaveRequestNo) }).innerText())
  894  |           .toContain('Rejected');
  895  |         expect(await mailpitIdsFor(intendedRecipients)).toEqual(mailBefore);
  896  |         employee.diagnostics.assertClean('Employee sees the rejected leave and reason live');
  897  |         departmentHead.diagnostics.assertClean('Department Head leave rejection workflow');
  898  |         hr.diagnostics.assertClean('HR leave rejection workflow');
  899  |       },
  900  |     });
  901  | 
  902  |     await captureAnnotatedStep(employee.page, 'employee', 'Employee sees leave rejection reason and unchanged balance', planned.length + 1, testInfo);
  903  |   } finally {
  904  |     await Promise.all([employee.context.close(), departmentHead.context.close(), hr.context.close()]);
  905  |   }
  906  | }
  907  | 
  908  | type LeaveCancellationStage = 'pending_dept' | 'pending_hr' | 'approved';
  909  | type PlannedLeaveStep = { name: string; actor: string; uiPath: string; expectedStatus: string };
  910  | 
  911  | async function runLeaveCancellationJourney(
  912  |   browser: Parameters<typeof actorPage>[0],
  913  |   testInfo: import('@playwright/test').TestInfo,
  914  |   cancellationStage: LeaveCancellationStage,
  915  | ): Promise<void> {
  916  |   const actors = await loadActors();
  917  |   const employeeUser = actorByName(actors, 'employee');
  918  |   const departmentHeadUser = actorByName(actors, 'department_head');
  919  |   const hrUser = actorByName(actors, 'hr_officer');
  920  |   const balances = await employeeBalanceBaseline(employeeUser.email);
  921  |   expect(balances, 'the seeded Employee should resolve to one Vacation Leave balance row').toHaveLength(1);
  922  |   expect(balances[0].balance_id, 'E2E baseline should include opening Vacation Leave entitlement').not.toBeNull();
  923  |   expect(Number(balances[0].remaining), 'opening leave balance must cover one day').toBeGreaterThanOrEqual(1);
  924  |   expect(balances[0].leave_request_count, 'fresh baseline should not contain leave transactions').toBe(0);
  925  | 
  926  |   const employee = await actorPage(browser, employeeUser.actor);
  927  |   const departmentHead = await actorPage(browser, departmentHeadUser.actor);
  928  |   const hr = await actorPage(browser, hrUser.actor);
  929  |   const variant = `HR04-CANCEL-${cancellationStage.replace('_', '-').toUpperCase()}`;
  930  |   const journey = createJourneyContext(variant);
  931  |   const startDate = await nextBusinessDay(employeeUser.email);
  932  |   const reason = `${String(journey.values.runPrefix)} leave cancellation journey`;
  933  |   const deptRecipients = await eligibleRecipients(
  934  |     'leave.submitted', 'leave.approve_dept', 'leave.submitted.notification_roles', balances[0].department_id,
  935  |   );
  936  |   const hrRecipients = await eligibleRecipients(
  937  |     'leave.pending_hr', 'leave.approve_hr', 'leave.pending_hr.notification_roles',
  938  |   );
  939  |   expect(deptRecipients).toContain(departmentHeadUser.email);
  940  |   expect(hrRecipients).toContain(hrUser.email);
  941  |   const intendedRecipients = [...new Set([...deptRecipients, ...hrRecipients, employeeUser.email])];
  942  |   const mailBefore = await mailpitIdsFor(intendedRecipients);
  943  | 
  944  |   const planned: PlannedLeaveStep[] = [{
  945  |     name: 'Employee files a Vacation Leave request',
  946  |     actor: 'employee',
  947  |     uiPath: 'Profile menu → Leaves → New request',
  948  |     expectedStatus: 'pending_dept; balance and attendance unchanged',
  949  |   }];
  950  |   if (cancellationStage === 'pending_dept') {
  951  |     planned.push({
  952  |       name: 'Department Head follows the live badge and opens the pending request',
  953  |       actor: 'department_head',
  954  |       uiPath: 'Dashboard → Human Resources → Leave Management signal → Awaiting my action → request detail',
  955  |       expectedStatus: 'pending_dept; Approve/Reject visible; no decision yet',
  956  |     });
  957  |   } else {
  958  |     planned.push({
  959  |       name: 'Department Head follows the badge and approves',
  960  |       actor: 'department_head',
  961  |       uiPath: 'Dashboard → Human Resources → Leave Management signal → Awaiting my action → Approve',
  962  |       expectedStatus: 'pending_hr; HR receives actionable work',
  963  |     });
  964  |     planned.push({
  965  |       name: cancellationStage === 'pending_hr'
  966  |         ? 'HR follows the badge and opens the pending request'
  967  |         : 'HR follows the badge and approves',
  968  |       actor: 'hr_officer',
  969  |       uiPath: cancellationStage === 'pending_hr'
  970  |         ? 'Dashboard → Human Resources → Leave Management signal → Awaiting my action → request detail'
  971  |         : 'Dashboard → Human Resources → Leave Management signal → Awaiting my action → Approve',
  972  |       expectedStatus: cancellationStage === 'pending_hr'
  973  |         ? 'pending_hr; Approve/Reject visible; no decision yet'
  974  |         : 'approved; one day consumed and attendance marked on_leave',
  975  |     });
  976  |   }
  977  |   planned.push({
  978  |     name: `Employee cancels while ${cancellationStage}`,
  979  |     actor: 'employee',
  980  |     uiPath: 'Profile menu → Leaves → request detail → Cancel → Yes, cancel',
  981  |     expectedStatus: 'cancelled; pending approvals retired or approved balance/attendance reversed',
  982  |   });
  983  | 
  984  |   const noticeTypes = async (publicId: string, expectedStage: LeaveCancellationStage): Promise<void> => {
  985  |     const notices = await notificationRows(publicId);
  986  |     const expectedTypes = ['leave.submitted'];
  987  |     if (expectedStage !== 'pending_dept') expectedTypes.push('leave.pending_hr');
  988  |     if (expectedStage === 'approved') expectedTypes.push('leave.approved');
> 989  |     expect(notices.map((notice) => notice.type).sort()).toEqual(expectedTypes.sort());
       |                                                         ^ Error: expect(received).toEqual(expected) // deep equality
  990  |     assertRecipientsExactly(
  991  |       notices.filter((notice) => notice.type === 'leave.submitted').map((notice) => notice.recipient_email),
  992  |       deptRecipients,
  993  |     );
  994  |     assertRecipientsExactly(
  995  |       notices.filter((notice) => notice.type === 'leave.pending_hr').map((notice) => notice.recipient_email),
  996  |       expectedStage === 'pending_dept' ? [] : hrRecipients,
  997  |     );
  998  |     assertRecipientsExactly(
  999  |       notices.filter((notice) => notice.type === 'leave.approved').map((notice) => notice.recipient_email),
  1000 |       expectedStage === 'approved' ? [employeeUser.email] : [],
  1001 |     );
  1002 |     expect(notices.every((notice) => notice.link_to?.includes(publicId))).toBe(true);
  1003 |   };
  1004 | 
  1005 |   const currentStatus = (): LeaveCancellationStage => cancellationStage === 'pending_dept'
  1006 |     ? 'pending_dept'
  1007 |     : cancellationStage === 'pending_hr' ? 'pending_hr' : 'approved';
  1008 |   const statusLabel = (status: string): string => ({
  1009 |     pending_dept: 'Pending dept',
  1010 |     pending_hr: 'Pending hr',
  1011 |     approved: 'Approved',
  1012 |     cancelled: 'Cancelled',
  1013 |   })[status] ?? status;
  1014 |   const expectedObserver = cancellationStage === 'pending_dept' ? departmentHead : hr;
  1015 |   let publicId = '';
  1016 |   let requestNo = '';
  1017 | 
  1018 |   const openFromQueue = async (actor: ActorPage, status: string): Promise<void> => {
  1019 |     await openLeaveApprovalFromBadge(actor, 1);
  1020 |     await actor.page.getByRole('button', { name: 'Kanban view', exact: true }).click();
  1021 |     const requestLink = actor.page.getByRole('link').filter({ hasText: requestNo });
  1022 |     await expect(requestLink).toHaveCount(1);
  1023 |     await requestLink.click();
  1024 |     await expect(actor.page).toHaveURL(new RegExp(`/hr/leaves/${publicId}$`));
  1025 |     await expect(actor.page.getByRole('heading').filter({ hasText: requestNo })).toContainText(statusLabel(status));
  1026 |   };
  1027 | 
  1028 |   const assertSnapshotForStage = async (status: string): Promise<LeaveSnapshot> => {
  1029 |     const snapshot = await expectLeaveStatus(reason, status);
  1030 |     expect(snapshot.leave_request_no).toBe(requestNo);
  1031 |     expect(snapshot.cancelled_by_email).toBeNull();
  1032 |     expect(snapshot.cancelled_at).toBeNull();
  1033 |     if (status === 'pending_dept') {
  1034 |       expect(snapshot.dept_approver_email).toBeNull();
  1035 |       expect(snapshot.hr_approver_email).toBeNull();
  1036 |     } else {
  1037 |       expect(snapshot.dept_approver_email).toBe(departmentHeadUser.email);
  1038 |       expect(snapshot.hr_approver_email).toBe(status === 'approved' ? hrUser.email : null);
  1039 |     }
  1040 |     const hasApproval = status === 'approved';
  1041 |     expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used) + (hasApproval ? 1 : 0));
  1042 |     expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining) - (hasApproval ? 1 : 0));
  1043 |     expect(snapshot.on_leave_attendance_count).toBe(hasApproval ? 1 : 0);
  1044 |     return snapshot;
  1045 |   };
  1046 | 
  1047 |   try {
  1048 |     await Promise.all([openSelfServiceLeaves(employee), openDashboard(departmentHead), openDashboard(hr)]);
  1049 |     const cleanRequests = await readOnlyDatabaseJson<Array<{ request_count: number }>>(`
  1050 |       SELECT COUNT(*)::int AS request_count
  1051 |         FROM leave_requests request
  1052 |        WHERE request.employee_id = ${balances[0].employee_id}
  1053 |     `);
  1054 |     expect(cleanRequests[0]?.request_count).toBe(0);
  1055 | 
  1056 |     await runProcessStep({
  1057 |       ...planned[0],
  1058 |       page: employee.page,
  1059 |       plannedSteps: planned,
  1060 |       context: journey,
  1061 |       testInfo,
  1062 |       action: async () => {
  1063 |         await employee.page.getByRole('button', { name: /New request/ }).click();
  1064 |         const dialog = employee.page.getByRole('dialog', { name: 'File Leave Request' });
  1065 |         await expect(dialog).toBeVisible();
  1066 |         await dialog.getByRole('combobox', { name: /^Leave type/ }).selectOption({ label: 'Vacation Leave' });
  1067 |         await dialog.getByLabel(/^Start date/).fill(startDate);
  1068 |         await dialog.getByLabel(/^End date/).fill(startDate);
  1069 |         await employee.page.getByLabel('Reason (optional)', { exact: true }).fill(reason);
  1070 |         const responsePromise = employee.page.waitForResponse((response) =>
  1071 |           response.url().includes('/api/v1/leaves/requests') && response.request().method() === 'POST',
  1072 |         );
  1073 |         await employee.page.getByRole('button', { name: 'Submit request', exact: true }).click();
  1074 |         const response = await responsePromise;
  1075 |         expect(response.status()).toBe(201);
  1076 |         const requestLink = employee.page.getByRole('link', { name: /^View leave request / });
  1077 |         await expect(requestLink).toHaveCount(1);
  1078 |         requestNo = (await requestLink.getAttribute('aria-label'))?.replace(/^View leave request /, '') ?? '';
  1079 |         const href = await requestLink.getAttribute('href');
  1080 |         publicId = href?.split('/').at(-1) ?? '';
  1081 |         expect(requestNo).toBeTruthy();
  1082 |         expect(href).toMatch(/^\/self-service\/leaves\//);
  1083 |         journey.values.employeeNo = balances[0].employee_no;
  1084 |         journey.values.leaveRequestNo = requestNo;
  1085 |         journey.values.leaveRequestPublicId = publicId;
  1086 |         journey.values.startDate = startDate;
  1087 |         journey.values.endDate = startDate;
  1088 |         journey.values.leaveType = 'VL';
  1089 |         journey.values.reason = reason;
```