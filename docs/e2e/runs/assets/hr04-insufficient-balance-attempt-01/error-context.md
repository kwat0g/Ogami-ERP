# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-leave.process.spec.ts >> HR04-INSUFFICIENT-BALANCE — Employee cannot submit more leave than the annual balance
- Location: e2e-process/hr-leave.process.spec.ts:1391:1

# Error details

```
Error: the form estimate should match server working-day calculation

expect(locator).toBeVisible() failed

Locator: getByRole('dialog', { name: 'File Leave Request' }).getByText('16', { exact: true })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - the form estimate should match server working-day calculation getByRole('dialog', { name: 'File Leave Request' }).getByText('16', { exact: true }) with timeout 8000ms
  - waiting for getByRole('dialog', { name: 'File Leave Request' }).getByText('16', { exact: true })

```

```yaml
- link "Skip to content":
  - /url: "#main-content"
- banner:
  - button "Toggle sidebar":
    - img
  - link "Ogami ERP Philippine Ogami Corporation":
    - /url: /dashboard
    - img "Ogami ERP"
    - text: Philippine Ogami Corporation
  - navigation "Breadcrumb":
    - link "Self-service":
      - /url: /self-service
    - img
    - text: Leaves
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications":
    - img
  - 'status "Real-time updates: Live. Last updated 12:17 PM"': Live
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E employee":
    - img "E2E employee": EE
    - text: E2E employee
    - img
- complementary "Sidebar":
  - navigation "Modules":
    - group "Work":
      - text: Work
      - list:
        - listitem:
          - link "Dashboard":
            - /url: /dashboard
            - img
            - text: Dashboard
        - listitem:
          - link "Action Center":
            - /url: /action-center
            - img
            - text: Action Center
        - listitem:
          - link "Approval Queue":
            - /url: /approvals
            - img
            - text: Approval Queue
        - listitem:
          - link "Notifications":
            - /url: /notifications
            - img
            - text: Notifications
- main:
  - heading "My Leave Requests" [level=1]
  - text: 0 total · 0 awaiting approval
  - img
  - heading "No leave requests yet" [level=3]
  - paragraph: File your first leave request to see it here.
  - button "New request":
    - img
    - text: New request
  - dialog "File Leave Request":
    - heading "File Leave Request" [level=2]
    - button "Close modal":
      - img
    - text: Leave type
    - combobox "Leave type":
      - option "— Select —"
      - option "Maternity Leave"
      - option "Paternity Leave"
      - option "Service Incentive Leave"
      - option "Sick Leave"
      - option "Special Leave for Women"
      - option "Solo Parent Leave"
      - option "VAWC Leave"
      - option "Vacation Leave" [selected]
    - text: "Balance: Vacation Leave 15.0 / 15.0 days remaining Start date"
    - textbox "Start date": 2026-11-06
    - text: End date
    - textbox "End date": 2026-11-24
    - text: Supporting document
    - button "Supporting document"
    - text: Optional. PDF or image, up to 10 MB. Half day
    - combobox "Half day":
      - option "Full day" [selected]
      - option "Morning (AM half-day)"
      - option "Afternoon (PM half-day)"
    - text: Estimated days to be deducted 16 days Reason (optional)
    - textbox "Reason (optional)": E2E-0d47f7e2-834 insufficient leave balance
    - button "Cancel"
    - button "Submit request"
```

# Test source

```ts
  1351 |         await noticeTypes(publicId, currentStatus());
  1352 |         expect(await mailpitIdsFor(intendedRecipients)).toEqual(mailBefore);
  1353 |         await Promise.all([openHumanResourcesMenu(departmentHead), openHumanResourcesMenu(hr)]);
  1354 |         await expect.poll(async () => departmentHead.page.getByRole('button', { name: /^View all Leave Management signals/ }).count()).toBe(0);
  1355 |         await expect.poll(async () => hr.page.getByRole('button', { name: /^View all Leave Management signals/ }).count()).toBe(0);
  1356 |         employee.diagnostics.assertClean('Employee cancels the request and sees restored final effects');
  1357 |         departmentHead.diagnostics.assertClean('Department Head sees the cancelled request live');
  1358 |         hr.diagnostics.assertClean('HR sees the cancelled request live');
  1359 |       },
  1360 |     });
  1361 |   } finally {
  1362 |     await Promise.all([employee.context.close(), departmentHead.context.close(), hr.context.close()]);
  1363 |   }
  1364 | }
  1365 | 
  1366 | test('HR04-REJECT-DEPT — Department Head rejection notifies Employee and stops the chain', async ({ browser }, testInfo) => {
  1367 |   test.setTimeout(120_000);
  1368 |   await runLeaveRejectionJourney(browser, testInfo, 'department_head');
  1369 | });
  1370 | 
  1371 | test('HR04-REJECT-HR — HR rejection notifies Employee after the Department Head approval', async ({ browser }, testInfo) => {
  1372 |   test.setTimeout(120_000);
  1373 |   await runLeaveRejectionJourney(browser, testInfo, 'hr_officer');
  1374 | });
  1375 | 
  1376 | test('HR04-CANCEL-PENDING-DEPT — Employee cancels before the Department Head acts', async ({ browser }, testInfo) => {
  1377 |   test.setTimeout(120_000);
  1378 |   await runLeaveCancellationJourney(browser, testInfo, 'pending_dept');
  1379 | });
  1380 | 
  1381 | test('HR04-CANCEL-PENDING-HR — Employee cancels after Department Head approval', async ({ browser }, testInfo) => {
  1382 |   test.setTimeout(120_000);
  1383 |   await runLeaveCancellationJourney(browser, testInfo, 'pending_hr');
  1384 | });
  1385 | 
  1386 | test('HR04-CANCEL-APPROVED — Employee cancels approved leave and restores balance and attendance', async ({ browser }, testInfo) => {
  1387 |   test.setTimeout(120_000);
  1388 |   await runLeaveCancellationJourney(browser, testInfo, 'approved');
  1389 | });
  1390 | 
  1391 | test('HR04-INSUFFICIENT-BALANCE — Employee cannot submit more leave than the annual balance', async ({ browser }, testInfo) => {
  1392 |   test.setTimeout(120_000);
  1393 |   const actors = await loadActors();
  1394 |   const employeeUser = actorByName(actors, 'employee');
  1395 |   const departmentHeadUser = actorByName(actors, 'department_head');
  1396 |   const hrUser = actorByName(actors, 'hr_officer');
  1397 |   const balances = await employeeBalanceBaseline(employeeUser.email);
  1398 |   expect(balances, 'the seeded Employee should resolve to one Vacation Leave balance row').toHaveLength(1);
  1399 |   expect(balances[0].balance_id, 'E2E baseline should include opening Vacation Leave entitlement').not.toBeNull();
  1400 |   expect(Number(balances[0].remaining), 'the E2E baseline must expose a measurable Vacation Leave balance').toBeGreaterThanOrEqual(0);
  1401 |   expect(balances[0].leave_request_count, 'fresh baseline should not contain leave transactions').toBe(0);
  1402 | 
  1403 |   const employee = await actorPage(browser, employeeUser.actor);
  1404 |   const departmentHead = await actorPage(browser, departmentHeadUser.actor);
  1405 |   const hr = await actorPage(browser, hrUser.actor);
  1406 |   const journey = createJourneyContext('HR04-INSUFFICIENT-BALANCE');
  1407 |   const startDate = await nextBusinessDay(employeeUser.email);
  1408 |   expect(Number(startDate.slice(0, 4)), 'the selected date must use the baseline leave-balance year').toBe(balances[0].balance_year);
  1409 |   const window = await dateExceedingLeaveBalance(startDate, Number(balances[0].balance_year), Number(balances[0].remaining));
  1410 |   expect(window.days, 'requested business days should exceed the employee annual leave balance').toBeGreaterThan(Number(balances[0].remaining));
  1411 |   const reason = `${String(journey.values.runPrefix)} insufficient leave balance`;
  1412 |   const deptRecipients = await eligibleRecipients(
  1413 |     'leave.submitted', 'leave.approve_dept', 'leave.submitted.notification_roles', balances[0].department_id,
  1414 |   );
  1415 |   const hrRecipients = await eligibleRecipients(
  1416 |     'leave.pending_hr', 'leave.approve_hr', 'leave.pending_hr.notification_roles',
  1417 |   );
  1418 |   expect(deptRecipients).toContain(departmentHeadUser.email);
  1419 |   expect(hrRecipients).toContain(hrUser.email);
  1420 |   const intendedRecipients = [...new Set([...deptRecipients, ...hrRecipients, employeeUser.email])];
  1421 |   const noticesBefore = await leaveNoticeIdsFor(intendedRecipients);
  1422 |   const mailBefore = await mailpitIdsFor(intendedRecipients);
  1423 |   const planned = [{
  1424 |     name: 'Employee attempts to submit Vacation Leave beyond the available balance',
  1425 |     actor: 'employee',
  1426 |     uiPath: 'Profile menu → Leaves → New request → Submit request',
  1427 |     expectedStatus: 'submission rejected with 422; no leave record, approval, audit, notification, email, balance or attendance mutation',
  1428 |   }];
  1429 | 
  1430 |   try {
  1431 |     await Promise.all([openSelfServiceLeaves(employee), openDashboard(departmentHead), openDashboard(hr)]);
  1432 |     await Promise.all([openHumanResourcesMenu(departmentHead), openHumanResourcesMenu(hr)]);
  1433 |     await expect(departmentHead.page.getByRole('button', { name: /^View all Leave Management signals/ })).toHaveCount(0);
  1434 |     await expect(hr.page.getByRole('button', { name: /^View all Leave Management signals/ })).toHaveCount(0);
  1435 | 
  1436 |     await runProcessStep({
  1437 |       ...planned[0],
  1438 |       page: employee.page,
  1439 |       plannedSteps: planned,
  1440 |       context: journey,
  1441 |       testInfo,
  1442 |       action: async () => {
  1443 |         await employee.page.getByRole('button', { name: /New request/ }).click();
  1444 |         const dialog = employee.page.getByRole('dialog', { name: 'File Leave Request' });
  1445 |         await expect(dialog).toBeVisible();
  1446 |         await dialog.getByRole('combobox', { name: /^Leave type/ }).selectOption({ label: 'Vacation Leave' });
  1447 |         await dialog.getByLabel(/^Start date/).fill(startDate);
  1448 |         await dialog.getByLabel(/^End date/).fill(window.date);
  1449 |         await employee.page.getByLabel('Reason (optional)', { exact: true }).fill(reason);
  1450 |         await expect(employee.page.getByText('Estimated days to be deducted')).toBeVisible();
> 1451 |         await expect(dialog.getByText(String(window.days), { exact: true }), 'the form estimate should match server working-day calculation').toBeVisible();
       |                                                                                                                                               ^ Error: the form estimate should match server working-day calculation
  1452 | 
  1453 |         const responsePromise = employee.page.waitForResponse((response) =>
  1454 |           response.url().includes('/api/v1/leaves/requests') && response.request().method() === 'POST',
  1455 |         );
  1456 |         await dialog.getByRole('button', { name: 'Submit request', exact: true }).click();
  1457 |         const response = await responsePromise;
  1458 |         expect(response.status(), 'insufficient balance should be a business-rule 422, not a server error').toBe(422);
  1459 |         const body = await response.json() as { message?: string };
  1460 |         expect(body.message).toContain('Insufficient leave balance for');
  1461 |         expect(body.message).toContain(String(balances[0].balance_year));
  1462 |         expect(body.message).toContain(`${window.days} requested`);
  1463 |         await expect(employee.page.getByText('Failed to submit leave request.', { exact: true })).toBeVisible();
  1464 |         journey.values.employeeNo = balances[0].employee_no;
  1465 |         journey.values.leaveType = 'VL';
  1466 |         journey.values.startDate = startDate;
  1467 |         journey.values.endDate = window.date;
  1468 |         journey.values.requestedDays = window.days;
  1469 |         journey.values.remainingDays = Number(balances[0].remaining);
  1470 |         journey.values.apiRejection = body.message;
  1471 |       },
  1472 |       verify: async () => {
  1473 |         await expect(employee.page.getByRole('dialog', { name: 'File Leave Request' })).toBeVisible();
  1474 |         await expect(employee.page.getByRole('button', { name: 'Submit request', exact: true })).toBeEnabled();
  1475 |         await expect(employee.page.getByRole('link', { name: /^View leave request / })).toHaveCount(0);
  1476 |       },
  1477 |       assertInvariants: async () => {
  1478 |         const afterBalances = await employeeBalanceBaseline(employeeUser.email);
  1479 |         expect(afterBalances).toHaveLength(1);
  1480 |         expect(afterBalances[0].used).toBe(balances[0].used);
  1481 |         expect(afterBalances[0].remaining).toBe(balances[0].remaining);
  1482 |         expect(afterBalances[0].leave_request_count).toBe(0);
  1483 |         const transactionRows = await readOnlyDatabaseJson<Array<{ requests: number; approvals: number; audits: number; leave_attendance: number }>>(`
  1484 |           SELECT
  1485 |             (SELECT COUNT(*)::int FROM leave_requests WHERE employee_id = ${balances[0].employee_id}) AS requests,
  1486 |             (SELECT COUNT(*)::int FROM approval_records WHERE approvable_type ILIKE '%LeaveRequest') AS approvals,
  1487 |             (SELECT COUNT(*)::int FROM audit_logs WHERE model_type ILIKE '%LeaveRequest') AS audits,
  1488 |             (SELECT COUNT(*)::int FROM attendances
  1489 |               WHERE employee_id = ${balances[0].employee_id}
  1490 |                 AND date BETWEEN ${sqlText(startDate)}::date AND ${sqlText(window.date)}::date
  1491 |                 AND status = 'on_leave' AND deleted_at IS NULL) AS leave_attendance
  1492 |         `);
  1493 |         expect(transactionRows).toEqual([{ requests: 0, approvals: 0, audits: 0, leave_attendance: 0 }]);
  1494 |         expect(await leaveNoticeIdsFor(intendedRecipients)).toEqual(noticesBefore);
  1495 |         expect(await mailpitIdsFor(intendedRecipients)).toEqual(mailBefore);
  1496 |         await expect(departmentHead.page.getByRole('button', { name: /^View all Leave Management signals/ })).toHaveCount(0);
  1497 |         await expect(hr.page.getByRole('button', { name: /^View all Leave Management signals/ })).toHaveCount(0);
  1498 |         employee.diagnostics.assertClean('Employee receives a controlled insufficient-balance refusal');
  1499 |         departmentHead.diagnostics.assertClean('Department Head receives no leave approval hand-off');
  1500 |         hr.diagnostics.assertClean('HR receives no leave approval hand-off');
  1501 |       },
  1502 |     });
  1503 |     await captureAnnotatedStep(employee.page, 'employee', 'Employee sees the insufficient-balance response with no request created', 1, testInfo);
  1504 |   } finally {
  1505 |     await Promise.all([employee.context.close(), departmentHead.context.close(), hr.context.close()]);
  1506 |   }
  1507 | });
  1508 | 
```