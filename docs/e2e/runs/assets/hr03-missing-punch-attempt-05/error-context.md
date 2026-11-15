# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-attendance.process.spec.ts >> HR03-MISSING-PUNCH: employee time-in is corrected by HR and the open employee DTR updates live
- Location: e2e-process/hr-attendance.process.spec.ts:605:1

# Error details

```
Error: attendance_changed should refresh the open employee DTR without a manual reload

expect(locator).toContainText(expected) failed

Locator: getByRole('row').filter({ hasText: 'Nov 5, 2026' })
Expected substring: "2:00 PM"
Received string:    "Nov 5, 20266:00 AM———Present"
Timeout: 8000ms

Call log:
  - attendance_changed should refresh the open employee DTR without a manual reload getByRole('row').filter({ hasText: 'Nov 5, 2026' }) with timeout 8000ms
  - waiting for getByRole('row').filter({ hasText: 'Nov 5, 2026' })
    20 × locator resolved to <tr class="border-b border-subtle transition-colors duration-fast motion-reduce:transition-none relative group hover:bg-surface hover:z-10 h-row-compact">…</tr>
       - unexpected value "Nov 5, 20266:00 AM———Present"

```

```yaml
- row "Nov 5, 2026 6:00 AM — — — Present":
  - cell "Nov 5, 2026"
  - cell "6:00 AM"
  - cell "—"
  - cell "—"
  - cell "—"
  - cell "Present"
```

# Test source

```ts
  685 |         expect(snapshot.overtime_count).toBe(0);
  686 |         expect(snapshot.attendance_notification_count).toBe(0);
  687 |         hr.diagnostics.assertClean('HR finds the previous-day missing time-out through the DTR filters');
  688 |       },
  689 |     });
  690 | 
  691 |     await runProcessStep({
  692 |       ...missingPunchSteps[2],
  693 |       page: employee.page,
  694 |       plannedSteps: missingPunchSteps,
  695 |       context: journey,
  696 |       testInfo,
  697 |       action: async () => {
  698 |         await employee.page.clock.setFixedTime(new Date(localInstant(nextLocalDate(baseline.work_date), '00:01:00')));
  699 |         await openSelfServiceDtr(employee, baseline.work_date);
  700 |       },
  701 |       verify: async (page) => {
  702 |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  703 |         await expect(row).toHaveCount(1);
  704 |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_start)));
  705 |       },
  706 |       assertInvariants: async () => {
  707 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  708 |         expect(snapshot.attendance_status).toBe('present');
  709 |         expect(snapshot.time_out_local).toBeNull();
  710 |         employee.diagnostics.assertClean('Employee opens DTR while HR prepares the correction');
  711 |       },
  712 |     });
  713 | 
  714 |     await runProcessStep({
  715 |       ...missingPunchSteps[3],
  716 |       page: hr.page,
  717 |       plannedSteps: missingPunchSteps,
  718 |       context: journey,
  719 |       testInfo,
  720 |       action: async (page) => {
  721 |         const row = page.getByRole('row').filter({ hasText: baseline.employee_no });
  722 |         await row.click();
  723 |         const modal = page.getByRole('dialog', { name: 'Correct attendance record' });
  724 |         await expect(modal).toBeVisible();
  725 |         await expect(modal.getByLabel('Time in')).toHaveValue(baseline.shift_start.slice(0, 5));
  726 |         await expect(modal.getByLabel('Time out')).toHaveValue('');
  727 |         await modal.getByLabel('Time out').fill(baseline.shift_end.slice(0, 5));
  728 |         await modal.getByLabel('Remarks').fill(`E2E ${journey.runId}: correct missed time-out from shift record`);
  729 |         const correction = page.waitForResponse((response) =>
  730 |           response.url().match(/\/api\/v1\/attendance\/attendances\/[^/]+$/) !== null
  731 |           && response.request().method() === 'PUT');
  732 |         await modal.getByRole('button', { name: 'Save correction', exact: true }).click();
  733 |         const response = await correction;
  734 |         const body = await response.text();
  735 |         expect(response.status(), `HR correction submitted through the UI should succeed; response: ${body.slice(0, 500)}`).toBe(200);
  736 |       },
  737 |       verify: async (page) => {
  738 |         await expect(page.getByRole('dialog', { name: 'Correct attendance record' })).toHaveCount(0);
  739 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  740 |         expect(snapshot.attendance_status).toBe('present');
  741 |         expect(snapshot.time_in_local).toBe(localDateTime(baseline.work_date, baseline.shift_start));
  742 |         expect(snapshot.time_out_local).toBe(localDateTime(baseline.work_date, baseline.shift_end));
  743 |         expect(snapshot.regular_hours).toBe('7.50');
  744 |         expect(snapshot.overtime_hours).toBe('0.00');
  745 |         expect(snapshot.audits.some((audit) => audit.actor_email?.toLowerCase() === hrActor.email.toLowerCase())).toBe(true);
  746 |         journey.values.correctedAttendance = {
  747 |           status: snapshot.attendance_status,
  748 |           timeIn: snapshot.time_in_local,
  749 |           timeOut: snapshot.time_out_local,
  750 |           regularHours: snapshot.regular_hours,
  751 |           overtimeHours: snapshot.overtime_hours,
  752 |           correctionAuditActor: hrActor.email,
  753 |         };
  754 |         const row = page.getByRole('row').filter({ hasText: baseline.employee_no });
  755 |         await expect(row, 'a corrected record should leave the active Missing time-out filter').toHaveCount(0);
  756 |         await expect(page.getByText('No attendance found', { exact: true })).toBeVisible();
  757 |       },
  758 |       assertInvariants: async () => {
  759 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  760 |         expect(snapshot.attendance_status).toBe('present');
  761 |         expect(snapshot.time_in_local).toBe(localDateTime(baseline.work_date, baseline.shift_start));
  762 |         expect(snapshot.time_out_local).toBe(localDateTime(baseline.work_date, baseline.shift_end));
  763 |         expect(snapshot.regular_hours).toBe('7.50');
  764 |         expect(snapshot.overtime_hours).toBe('0.00');
  765 |         expect(snapshot.tardiness_minutes).toBe(0);
  766 |         expect(snapshot.undertime_minutes).toBe(0);
  767 |         expect(snapshot.punches).toHaveLength(1);
  768 |         expect(snapshot.attendance_notification_count).toBe(0);
  769 |         expect(snapshot.audits.some((audit) => audit.actor_email?.toLowerCase() === hrActor.email.toLowerCase())).toBe(true);
  770 |         hr.diagnostics.assertClean('HR saves the attendance correction through its own session');
  771 |       },
  772 |     });
  773 | 
  774 |     await runProcessStep({
  775 |       ...missingPunchSteps[4],
  776 |       page: employee.page,
  777 |       plannedSteps: missingPunchSteps,
  778 |       context: journey,
  779 |       testInfo,
  780 |       action: async () => undefined,
  781 |       verify: async (page) => {
  782 |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  783 |         await captureAnnotatedStep(page, 'employee', 'Employee DTR remains open after HR correction', 5, testInfo);
  784 |         await expect(row, 'attendance_changed should refresh the open employee DTR without a manual reload')
> 785 |           .toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
      |            ^ Error: attendance_changed should refresh the open employee DTR without a manual reload
  786 |         await expect(row).toContainText('7.5');
  787 |       },
  788 |       assertInvariants: async () => {
  789 |         const snapshot = await assertSnapshot(employeeActor.email, baseline.work_date, 1);
  790 |         expect(snapshot.time_out_local).toBe(localDateTime(baseline.work_date, baseline.shift_end));
  791 |         employee.diagnostics.assertClean('Employee observes HR correction in the already-open DTR');
  792 |         hr.diagnostics.assertClean('HR retains the corrected attendance list');
  793 |         expect((await listMailpitMessages(500)).map((message) => message.ID).sort()).toEqual(mailIdsBefore);
  794 |       },
  795 |     });
  796 | 
  797 |     await runProcessStep({
  798 |       ...missingPunchSteps[5],
  799 |       page: employee.page,
  800 |       plannedSteps: missingPunchSteps,
  801 |       context: journey,
  802 |       testInfo,
  803 |       action: async (page) => { await page.reload(); },
  804 |       verify: async (page) => {
  805 |         const row = page.getByRole('row').filter({ hasText: dateLabel(baseline.work_date) });
  806 |         await expect(row).toContainText(manilaTime(localInstant(baseline.work_date, baseline.shift_end)));
  807 |         await expect(row).toContainText('7.5');
  808 |         await expect(row).toContainText('Present');
  809 |       },
  810 |       assertInvariants: async () => {
  811 |         employee.diagnostics.assertClean('Employee confirms saved DTR after reloading');
  812 |       },
  813 |     });
  814 |   } catch (error) {
  815 |     primaryError = error;
  816 |     throw error;
  817 |   } finally {
  818 |     const cleanupErrors: string[] = [];
  819 |     try {
  820 |       if (clockFrozen) await clearClock(request);
  821 |     } catch (error) {
  822 |       cleanupErrors.push(`clock reset: ${error instanceof Error ? error.message : String(error)}`);
  823 |     } finally {
  824 |       const closeResults = await Promise.allSettled([employee.context.close(), hr.context.close()]);
  825 |       closeResults.forEach((result, index) => {
  826 |         if (result.status === 'rejected') {
  827 |           cleanupErrors.push(`${index === 0 ? 'employee' : 'HR'} context close: ${result.reason instanceof Error ? result.reason.message : String(result.reason)}`);
  828 |         }
  829 |       });
  830 |     }
  831 |     if (cleanupErrors.length > 0) {
  832 |       await testInfo.attach('journey-cleanup-errors.txt', {
  833 |         body: cleanupErrors.join('\n'),
  834 |         contentType: 'text/plain',
  835 |       });
  836 |       if (primaryError === undefined) throw new Error(cleanupErrors.join('\n'));
  837 |     }
  838 |   }
  839 | });
  840 | 
  841 | function nextLocalDate(date: string): string {
  842 |   const [year, month, day] = date.split('-').map(Number);
  843 |   const next = new Date(Date.UTC(year, month - 1, day + 1));
  844 |   return `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, '0')}-${String(next.getUTCDate()).padStart(2, '0')}`;
  845 | }
  846 | 
```