# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-leave.process.spec.ts >> HR04-CANCEL-PENDING-DEPT — Employee cancels before the Department Head acts
- Location: e2e-process/hr-leave.process.spec.ts:1309:1

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: 200
Received: 500
```

# Test source

```ts
  1141 |               && response.request().method() === 'PATCH',
  1142 |           );
  1143 |           await confirmation.getByRole('button', { name: 'Approve', exact: true }).click();
  1144 |           expect((await responsePromise).status()).toBe(200);
  1145 |         },
  1146 |         verify: async () => {
  1147 |           const snapshot = await assertSnapshotForStage('pending_hr');
  1148 |           expect(snapshot.dept_approver_email).toBe(departmentHeadUser.email);
  1149 |           expect(await approvalRows(reason)).toEqual([
  1150 |             expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'approved', approver_email: departmentHeadUser.email }),
  1151 |             expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'pending', approver_email: null }),
  1152 |           ]);
  1153 |           await expect(employee.page.getByRole('heading').filter({ hasText: requestNo })).toContainText('Pending hr');
  1154 |           await expect(departmentHead.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  1155 |           await expect(departmentHead.page.getByRole('button', { name: 'Reject', exact: true })).toHaveCount(0);
  1156 |         },
  1157 |         assertInvariants: async () => {
  1158 |           await noticeTypes(publicId, 'pending_hr');
  1159 |           expect(await mailpitIdsFor(intendedRecipients)).toEqual(mailBefore);
  1160 |           departmentHead.diagnostics.assertClean('Department Head approves the leave before cancellation');
  1161 |         },
  1162 |       });
  1163 | 
  1164 |       const hrStepIndex = 2;
  1165 |       if (cancellationStage === 'pending_hr') {
  1166 |         await runProcessStep({
  1167 |           ...planned[hrStepIndex],
  1168 |           page: hr.page,
  1169 |           plannedSteps: planned,
  1170 |           context: journey,
  1171 |           testInfo,
  1172 |           action: async () => openFromQueue(hr, 'pending_hr'),
  1173 |           verify: async () => {
  1174 |             await assertSnapshotForStage('pending_hr');
  1175 |             await expect(hr.page.getByRole('button', { name: 'Approve', exact: true })).toBeEnabled();
  1176 |             await expect(hr.page.getByRole('button', { name: 'Reject', exact: true })).toBeEnabled();
  1177 |             await expect(employee.page.getByRole('button', { name: 'Cancel', exact: true })).toBeVisible();
  1178 |           },
  1179 |           assertInvariants: async () => {
  1180 |             await noticeTypes(publicId, 'pending_hr');
  1181 |             hr.diagnostics.assertClean('HR receives and opens pending leave through its badge');
  1182 |           },
  1183 |         });
  1184 |       } else {
  1185 |         await runProcessStep({
  1186 |           ...planned[hrStepIndex],
  1187 |           page: hr.page,
  1188 |           plannedSteps: planned,
  1189 |           context: journey,
  1190 |           testInfo,
  1191 |           action: async () => {
  1192 |             await openFromQueue(hr, 'pending_hr');
  1193 |             await hr.page.getByRole('button', { name: 'Approve', exact: true }).click();
  1194 |             const confirmation = hr.page.getByRole('dialog', { name: 'Approve leave request?' });
  1195 |             await expect(confirmation).toBeVisible();
  1196 |             const responsePromise = hr.page.waitForResponse((response) =>
  1197 |               response.url().endsWith(`/api/v1/leaves/requests/${publicId}/approve-hr`)
  1198 |                 && response.request().method() === 'PATCH',
  1199 |             );
  1200 |             await confirmation.getByRole('button', { name: 'Approve', exact: true }).click();
  1201 |             expect((await responsePromise).status()).toBe(200);
  1202 |           },
  1203 |           verify: async () => {
  1204 |             const snapshot = await assertSnapshotForStage('approved');
  1205 |             expect(snapshot.hr_approver_email).toBe(hrUser.email);
  1206 |             expect(await approvalRows(reason)).toEqual([
  1207 |               expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'approved', approver_email: departmentHeadUser.email }),
  1208 |               expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'approved', approver_email: hrUser.email }),
  1209 |             ]);
  1210 |             await expect(employee.page.getByRole('heading').filter({ hasText: requestNo })).toContainText('Approved');
  1211 |             await expect(employee.page.getByRole('button', { name: 'Cancel', exact: true })).toBeVisible();
  1212 |             await expect(hr.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  1213 |           },
  1214 |           assertInvariants: async () => {
  1215 |             await noticeTypes(publicId, 'approved');
  1216 |             expect(await mailpitIdsFor(intendedRecipients)).toEqual(mailBefore);
  1217 |             hr.diagnostics.assertClean('HR approves the leave before the owner cancels it');
  1218 |           },
  1219 |         });
  1220 |       }
  1221 |     }
  1222 | 
  1223 |     const cancellationIndex = planned.length - 1;
  1224 |     await runProcessStep({
  1225 |       ...planned[cancellationIndex],
  1226 |       page: employee.page,
  1227 |       plannedSteps: planned,
  1228 |       context: journey,
  1229 |       testInfo,
  1230 |       action: async () => {
  1231 |         const cancelButton = employee.page.getByRole('button', { name: 'Cancel', exact: true });
  1232 |         await expect(cancelButton).toBeEnabled();
  1233 |         await cancelButton.click();
  1234 |         const confirmation = employee.page.getByRole('dialog', { name: 'Cancel leave request?' });
  1235 |         await expect(confirmation).toBeVisible();
  1236 |         const responsePromise = employee.page.waitForResponse((response) =>
  1237 |           response.url().endsWith(`/api/v1/leaves/requests/${publicId}/cancel`)
  1238 |             && response.request().method() === 'PATCH',
  1239 |         );
  1240 |         await confirmation.getByRole('button', { name: 'Yes, cancel', exact: true }).click();
> 1241 |         expect((await responsePromise).status()).toBe(200);
       |                                                  ^ Error: expect(received).toBe(expected) // Object.is equality
  1242 |       },
  1243 |       verify: async () => {
  1244 |         const snapshot = await expectLeaveStatus(reason, 'cancelled');
  1245 |         expect(snapshot.leave_request_no).toBe(requestNo);
  1246 |         expect(snapshot.cancelled_by_email).toBe(employeeUser.email);
  1247 |         expect(snapshot.cancelled_at).not.toBeNull();
  1248 |         expect(snapshot.rejection_reason).toBeNull();
  1249 |         expect(snapshot.dept_approver_email).toBe(cancellationStage === 'pending_dept' ? null : departmentHeadUser.email);
  1250 |         expect(snapshot.hr_approver_email).toBe(cancellationStage === 'approved' ? hrUser.email : null);
  1251 |         expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used));
  1252 |         expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining));
  1253 |         expect(snapshot.on_leave_attendance_count).toBe(0);
  1254 |         const history = await approvalHistoryRows(reason);
  1255 |         if (cancellationStage === 'pending_dept') {
  1256 |           expect(history).toEqual([
  1257 |             expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'superseded', approver_email: null, is_current: false }),
  1258 |             expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'superseded', approver_email: null, is_current: false }),
  1259 |           ]);
  1260 |         } else if (cancellationStage === 'pending_hr') {
  1261 |           expect(history).toEqual([
  1262 |             expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'approved', approver_email: departmentHeadUser.email, is_current: true }),
  1263 |             expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'superseded', approver_email: null, is_current: false }),
  1264 |           ]);
  1265 |         } else {
  1266 |           expect(history).toEqual([
  1267 |             expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'approved', approver_email: departmentHeadUser.email, is_current: true }),
  1268 |             expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'approved', approver_email: hrUser.email, is_current: true }),
  1269 |           ]);
  1270 |         }
  1271 |         const cancelAudit = (await requestAudits(reason)).find((audit) => audit.status === 'cancelled');
  1272 |         expect(cancelAudit).toBeDefined();
  1273 |         assertAuditActor({ actor_email: cancelAudit?.actor_email ?? undefined }, employeeUser.email);
  1274 |         const employeeHeading = employee.page.getByRole('heading').filter({ hasText: requestNo });
  1275 |         await expect(employeeHeading).toContainText('Cancelled');
  1276 |         await expect(employee.page.getByRole('button', { name: 'Cancel', exact: true })).toHaveCount(0);
  1277 |         await expect(employee.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  1278 |         await expect(employee.page.getByRole('button', { name: 'Reject', exact: true })).toHaveCount(0);
  1279 |         await expect(expectedObserver.page.getByRole('heading').filter({ hasText: requestNo })).toContainText('Cancelled');
  1280 |         await expect(expectedObserver.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  1281 |         await expect(expectedObserver.page.getByRole('button', { name: 'Reject', exact: true })).toHaveCount(0);
  1282 |       },
  1283 |       assertInvariants: async () => {
  1284 |         await noticeTypes(publicId, currentStatus());
  1285 |         expect(await mailpitIdsFor(intendedRecipients)).toEqual(mailBefore);
  1286 |         await Promise.all([openHumanResourcesMenu(departmentHead), openHumanResourcesMenu(hr)]);
  1287 |         await expect.poll(async () => departmentHead.page.getByRole('button', { name: /^View all Leave Management signals/ }).count()).toBe(0);
  1288 |         await expect.poll(async () => hr.page.getByRole('button', { name: /^View all Leave Management signals/ }).count()).toBe(0);
  1289 |         employee.diagnostics.assertClean('Employee cancels the request and sees restored final effects');
  1290 |         departmentHead.diagnostics.assertClean('Department Head sees the cancelled request live');
  1291 |         hr.diagnostics.assertClean('HR sees the cancelled request live');
  1292 |       },
  1293 |     });
  1294 |   } finally {
  1295 |     await Promise.all([employee.context.close(), departmentHead.context.close(), hr.context.close()]);
  1296 |   }
  1297 | }
  1298 | 
  1299 | test('HR04-REJECT-DEPT — Department Head rejection notifies Employee and stops the chain', async ({ browser }, testInfo) => {
  1300 |   test.setTimeout(120_000);
  1301 |   await runLeaveRejectionJourney(browser, testInfo, 'department_head');
  1302 | });
  1303 | 
  1304 | test('HR04-REJECT-HR — HR rejection notifies Employee after the Department Head approval', async ({ browser }, testInfo) => {
  1305 |   test.setTimeout(120_000);
  1306 |   await runLeaveRejectionJourney(browser, testInfo, 'hr_officer');
  1307 | });
  1308 | 
  1309 | test('HR04-CANCEL-PENDING-DEPT — Employee cancels before the Department Head acts', async ({ browser }, testInfo) => {
  1310 |   test.setTimeout(120_000);
  1311 |   await runLeaveCancellationJourney(browser, testInfo, 'pending_dept');
  1312 | });
  1313 | 
  1314 | test('HR04-CANCEL-PENDING-HR — Employee cancels after Department Head approval', async ({ browser }, testInfo) => {
  1315 |   test.setTimeout(120_000);
  1316 |   await runLeaveCancellationJourney(browser, testInfo, 'pending_hr');
  1317 | });
  1318 | 
  1319 | test('HR04-CANCEL-APPROVED — Employee cancels approved leave and restores balance and attendance', async ({ browser }, testInfo) => {
  1320 |   test.setTimeout(120_000);
  1321 |   await runLeaveCancellationJourney(browser, testInfo, 'approved');
  1322 | });
  1323 | 
```