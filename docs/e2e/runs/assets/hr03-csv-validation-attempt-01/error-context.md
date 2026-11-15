# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-attendance.process.spec.ts >> HR03-CSV-VALIDATION: HR imports a valid DTR row and receives a row-level error for an unknown employee
- Location: e2e-process/hr-attendance.process.spec.ts:1512:1

# Error details

```
Error: expect(received).toMatchObject(expected)

- Expected  - 2
+ Received  + 6

  Object {
    "errors": Array [
      Object {
+       "message": "Time out (2026-11-05 14:00:00) must be after time in (2026-11-06 06:00:00).",
+       "row": 2,
+     },
+     Object {
        "message": "Unknown employee_no 'UNKNOWN-c95ceefb'.",
        "row": 3,
      },
    ],
-   "imported": 1,
-   "skipped": 1,
+   "imported": 0,
+   "skipped": 2,
    "total": 2,
  }
```

# Test source

```ts
  1503 |       await testInfo.attach('journey-cleanup-errors.txt', {
  1504 |         body: cleanupErrors.join('\n'),
  1505 |         contentType: 'text/plain',
  1506 |       });
  1507 |       if (primaryError === undefined) throw new Error(cleanupErrors.join('\n'));
  1508 |     }
  1509 |   }
  1510 | });
  1511 | 
  1512 | test('HR03-CSV-VALIDATION: HR imports a valid DTR row and receives a row-level error for an unknown employee', async ({ browser }, testInfo) => {
  1513 |   const actors = await loadActors();
  1514 |   const employeeActor = actorByName(actors, 'employee');
  1515 |   const hrActor = actorByName(actors, 'hr_officer');
  1516 |   const baseline = await punchBaseline(employeeActor.email);
  1517 |   const journey = createJourneyContext('HR03-CSV-VALIDATION');
  1518 |   const unknownEmployeeNo = `UNKNOWN-${journey.runId.slice(0, 8)}`;
  1519 |   const fileName = `${journey.values.runPrefix}-biometric-dtr.csv`;
  1520 |   const csv = [
  1521 |     'employee_no,date,time_in,time_out',
  1522 |     `${baseline.employee_no},${baseline.work_date},${baseline.shift_start},${baseline.shift_end}`,
  1523 |     `${unknownEmployeeNo},${baseline.work_date},${baseline.shift_start},${baseline.shift_end}`,
  1524 |   ].join('\n');
  1525 |   journey.values.employeeNo = baseline.employee_no;
  1526 |   journey.values.workDate = baseline.work_date;
  1527 |   journey.values.shift = baseline.shift_name;
  1528 |   journey.values.shiftStart = baseline.shift_start;
  1529 |   journey.values.shiftEnd = baseline.shift_end;
  1530 |   journey.values.unknownEmployeeNo = unknownEmployeeNo;
  1531 |   journey.values.csvFileName = fileName;
  1532 | 
  1533 |   const hr = await actorPage(browser, hrActor.actor, { timezoneId: 'Asia/Manila' });
  1534 |   const employee = await actorPage(browser, employeeActor.actor, { timezoneId: 'Asia/Manila' });
  1535 |   const journeyActorEmails = new Set([employeeActor.email, hrActor.email].map((email) => email.toLowerCase()));
  1536 |   const journeyActorMailIds = async () => (await listMailpitMessages(500))
  1537 |     .filter((message) => message.To.some((recipient) => journeyActorEmails.has(recipient.Address.toLowerCase())))
  1538 |     .map((message) => message.ID)
  1539 |     .sort();
  1540 |   const mailIdsBefore = await journeyActorMailIds();
  1541 |   const plannedSteps = csvImportSteps;
  1542 |   let primaryError: unknown;
  1543 | 
  1544 |   try {
  1545 |     const browserNow = localInstant(baseline.work_date, '12:00:00');
  1546 |     await hr.page.clock.setFixedTime(new Date(browserNow));
  1547 |     await employee.page.clock.setFixedTime(new Date(browserNow));
  1548 | 
  1549 |     await runProcessStep({
  1550 |       ...plannedSteps[0],
  1551 |       page: hr.page,
  1552 |       plannedSteps,
  1553 |       context: journey,
  1554 |       testInfo,
  1555 |       action: async () => {
  1556 |         await openAttendanceList(hr, baseline.work_date, baseline.employee_no);
  1557 |         await hr.page.getByRole('button', { name: 'Import DTR', exact: true }).click();
  1558 |         await expect(hr.page).toHaveURL(/\/hr\/attendance\/import(?:\?|$)/);
  1559 |         await expect(hr.page.getByRole('heading', { name: 'Import biometric DTR', exact: true })).toBeVisible();
  1560 |         await employee.page.goto('/dashboard');
  1561 |         await expect(employee.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  1562 |         await openSelfServiceDtr(employee, baseline.work_date);
  1563 |       },
  1564 |       verify: async () => {
  1565 |         await expect(hr.page.getByRole('button', { name: 'Import', exact: true })).toBeDisabled();
  1566 |         const employeeRow = employee.page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  1567 |         await expect(employeeRow, 'the Employee DTR should have no row before the CSV upload').toHaveCount(0);
  1568 |       },
  1569 |       assertInvariants: async () => {
  1570 |         const initial = await punchSnapshot(employeeActor.email, baseline.work_date);
  1571 |         expect(initial).toHaveLength(1);
  1572 |         expect(initial[0].attendance_id).toBeNull();
  1573 |         expect(initial[0].punches).toEqual([]);
  1574 |         expect(await journeyActorMailIds()).toEqual(mailIdsBefore);
  1575 |         hr.diagnostics.assertClean('HR opens the DTR import form through the attendance page');
  1576 |         employee.diagnostics.assertClean('Employee opens DTR as a second-viewer observer');
  1577 |       },
  1578 |     });
  1579 | 
  1580 |     await runProcessStep({
  1581 |       ...plannedSteps[1],
  1582 |       page: hr.page,
  1583 |       plannedSteps,
  1584 |       context: journey,
  1585 |       testInfo,
  1586 |       action: async (page) => {
  1587 |         await page.locator('input[type="file"][accept=".csv,text/csv"]').setInputFiles({
  1588 |           name: fileName,
  1589 |           mimeType: 'text/csv',
  1590 |           buffer: Buffer.from(csv, 'utf8'),
  1591 |         });
  1592 |         await expect(page.getByText(fileName, { exact: true })).toBeVisible();
  1593 |         const uploaded = page.waitForResponse((response) =>
  1594 |           new URL(response.url()).pathname === '/api/v1/attendance/attendances/import'
  1595 |           && response.request().method() === 'POST');
  1596 |         await page.getByRole('button', { name: 'Import', exact: true }).click();
  1597 |         const response = await uploaded;
  1598 |         const responseText = await response.text();
  1599 |         expect(response.status(), `HR biometric CSV upload should return its row summary; response: ${responseText.slice(0, 500)}`).toBe(200);
  1600 |         const body = JSON.parse(responseText) as {
  1601 |           data?: { total?: number; imported?: number; skipped?: number; errors?: Array<{ row: number; message: string }> };
  1602 |         };
> 1603 |         expect(body.data).toMatchObject({
       |                           ^ Error: expect(received).toMatchObject(expected)
  1604 |           total: 2,
  1605 |           imported: 1,
  1606 |           skipped: 1,
  1607 |           errors: [{ row: 3, message: `Unknown employee_no '${unknownEmployeeNo}'.` }],
  1608 |         });
  1609 |         journey.values.importSummary = body.data;
  1610 |       },
  1611 |       verify: async (page) => {
  1612 |         const summary = page.getByRole('heading', { name: 'Import summary', exact: true });
  1613 |         await expect(summary).toBeVisible();
  1614 |         const total = page.getByText('Total', { exact: true }).locator('xpath=..');
  1615 |         const imported = page.getByText('Imported', { exact: true }).locator('xpath=..');
  1616 |         const skipped = page.getByText('Skipped', { exact: true }).locator('xpath=..');
  1617 |         await expect(total).toContainText('2');
  1618 |         await expect(imported).toContainText('1');
  1619 |         await expect(skipped).toContainText('1');
  1620 |         await expect(page.getByText('Row 3', { exact: true })).toBeVisible();
  1621 |         await expect(page.getByText(`Unknown employee_no '${unknownEmployeeNo}'.`, { exact: true })).toBeVisible();
  1622 |       },
  1623 |       assertInvariants: async () => {
  1624 |         await assertImportedDtr(
  1625 |           employeeActor.email,
  1626 |           baseline.employee_no,
  1627 |           hrActor.email,
  1628 |           baseline.work_date,
  1629 |           baseline.shift_start,
  1630 |           baseline.shift_end,
  1631 |           unknownEmployeeNo,
  1632 |         );
  1633 |         expect(await journeyActorMailIds()).toEqual(mailIdsBefore);
  1634 |         hr.diagnostics.assertClean('HR receives a partial-success summary after CSV import');
  1635 |         employee.diagnostics.assertClean('Employee DTR remains an open realtime observer during CSV import');
  1636 |       },
  1637 |     });
  1638 | 
  1639 |     await runProcessStep({
  1640 |       ...plannedSteps[2],
  1641 |       page: employee.page,
  1642 |       plannedSteps,
  1643 |       context: journey,
  1644 |       testInfo,
  1645 |       action: async () => undefined,
  1646 |       verify: async (page) => {
  1647 |         const employeeRow = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  1648 |         await expect(employeeRow, 'attendance_changed should update the Employee DTR without reload')
  1649 |           .toHaveCount(1, { timeout: 20_000 });
  1650 |         await expect(employeeRow).toContainText('Present');
  1651 |         await expect(employeeRow).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_start)));
  1652 |         await expect(employeeRow).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
  1653 |         await expect(employeeRow).toContainText('7.5');
  1654 |       },
  1655 |       assertInvariants: async () => {
  1656 |         await assertImportedDtr(
  1657 |           employeeActor.email,
  1658 |           baseline.employee_no,
  1659 |           hrActor.email,
  1660 |           baseline.work_date,
  1661 |           baseline.shift_start,
  1662 |           baseline.shift_end,
  1663 |           unknownEmployeeNo,
  1664 |         );
  1665 |         employee.diagnostics.assertClean('Employee observes the imported DTR without refresh');
  1666 |         hr.diagnostics.assertClean('HR retains the partial-success import summary');
  1667 |       },
  1668 |     });
  1669 | 
  1670 |     await runProcessStep({
  1671 |       ...plannedSteps[3],
  1672 |       page: employee.page,
  1673 |       plannedSteps,
  1674 |       context: journey,
  1675 |       testInfo,
  1676 |       action: async (page) => {
  1677 |         await page.reload();
  1678 |         await expect(page.getByRole('heading', { name: 'Daily Time Record', exact: true })).toBeVisible();
  1679 |       },
  1680 |       verify: async (page) => {
  1681 |         const employeeRow = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  1682 |         await expect(employeeRow).toHaveCount(1);
  1683 |         await expect(employeeRow).toContainText('Present');
  1684 |         await expect(employeeRow).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_start)));
  1685 |         await expect(employeeRow).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
  1686 |         await expect(employeeRow).toContainText('7.5');
  1687 |       },
  1688 |       assertInvariants: async () => {
  1689 |         await assertImportedDtr(
  1690 |           employeeActor.email,
  1691 |           baseline.employee_no,
  1692 |           hrActor.email,
  1693 |           baseline.work_date,
  1694 |           baseline.shift_start,
  1695 |           baseline.shift_end,
  1696 |           unknownEmployeeNo,
  1697 |         );
  1698 |         expect(await journeyActorMailIds()).toEqual(mailIdsBefore);
  1699 |         employee.diagnostics.assertClean('Employee confirms the imported DTR persists after reload');
  1700 |         hr.diagnostics.assertClean('HR retains the import summary after the DTR side effect');
  1701 |       },
  1702 |     });
  1703 |   } catch (error) {
```