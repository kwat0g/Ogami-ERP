# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-leave.process.spec.ts >> HR04-REJECT-DEPT — Department Head rejection notifies Employee and stops the chain
- Location: e2e-process/hr-leave.process.spec.ts:887:1

# Error details

```
Error: department_head should receive its actionable leave badge

department_head should receive its actionable leave badge

expect(received).toBe(expected) // Object.is equality

Expected: 1
Received: 0

Call Log:
- Timeout 20000ms exceeded while waiting on the predicate
```

# Test source

```ts
  706 |         journey.values.employeeNo = balances[0].employee_no;
  707 |         journey.values.leaveRequestNo = number;
  708 |         journey.values.leaveRequestPublicId = publicId;
  709 |         journey.values.startDate = startDate;
  710 |         journey.values.endDate = endDate;
  711 |         journey.values.leaveType = 'VL';
  712 |         journey.values.reason = reason;
  713 |         await requestLink.click();
  714 |         await expect(employee.page.getByRole('heading', { name: new RegExp(number!) })).toBeVisible();
  715 |       },
  716 |       verify: async () => {
  717 |         const snapshot = await expectLeaveStatus(reason, 'pending_dept');
  718 |         expect(snapshot.leave_request_no).toBe(journey.values.leaveRequestNo);
  719 |         expect(snapshot.days).toBe('1.0');
  720 |         expect(snapshot.rejection_reason).toBeNull();
  721 |         expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used));
  722 |         expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining));
  723 |         expect(snapshot.on_leave_attendance_count).toBe(0);
  724 |         expect(await approvalRows(reason)).toEqual([
  725 |           expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'pending', approver_email: null }),
  726 |           expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'pending', approver_email: null }),
  727 |         ]);
  728 |         await expect(employee.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  729 |         await expect(employee.page.getByRole('button', { name: 'Cancel', exact: true })).toBeVisible();
  730 |       },
  731 |       assertInvariants: async () => {
  732 |         await expect.poll(async () => (await notificationRows(String(journey.values.leaveRequestPublicId)))
  733 |           .filter((row) => row.type === 'leave.submitted').map((row) => row.recipient_email).sort(), {
  734 |           timeout: 20_000,
  735 |           message: 'the submission notice should reach only the eligible Department Head audience',
  736 |         }).toEqual([...deptRecipients].sort());
  737 |         employee.diagnostics.assertClean('Employee submits a leave request through self-service');
  738 |       },
  739 |     });
  740 | 
  741 |     if (rejectionStage === 'hr_officer') {
  742 |       await runProcessStep({
  743 |         name: planned[1].name,
  744 |         actor: planned[1].actor,
  745 |         uiPath: planned[1].uiPath,
  746 |         expectedStatus: planned[1].expectedStatus,
  747 |         page: departmentHead.page,
  748 |         plannedSteps: planned,
  749 |         context: journey,
  750 |         testInfo,
  751 |         action: async () => {
  752 |           await expect.poll(async () => departmentHead.page.getByRole('button', {
  753 |             name: 'View all Leave Management signals: 1 Leave requests awaiting your approval', exact: true,
  754 |           }).count(), { timeout: 20_000, message: 'Department Head should receive the initial leave hand-off' }).toBe(1);
  755 |           await openLeaveApprovalFromBadge(departmentHead, 1);
  756 |           const row = departmentHead.page.getByRole('row').filter({ hasText: String(journey.values.leaveRequestNo) });
  757 |           await expect(row).toHaveCount(1);
  758 |           await expect(row).toContainText('Pending dept');
  759 |           await row.getByRole('button', { name: 'Approve', exact: true }).click();
  760 |           const confirmation = departmentHead.page.getByRole('dialog', { name: 'Approve leave request?' });
  761 |           await expect(confirmation).toBeVisible();
  762 |           await confirmation.getByRole('button', { name: 'Approve', exact: true }).click();
  763 |           await expect(row).toHaveCount(0);
  764 |         },
  765 |         verify: async () => {
  766 |           const snapshot = await expectLeaveStatus(reason, 'pending_hr');
  767 |           expect(snapshot.dept_approver_email).toBe(departmentHeadUser.email);
  768 |           expect(snapshot.hr_approver_email).toBeNull();
  769 |           expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used));
  770 |           expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining));
  771 |           expect(snapshot.on_leave_attendance_count).toBe(0);
  772 |           expect(await approvalRows(reason)).toEqual([
  773 |             expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'approved', approver_email: departmentHeadUser.email }),
  774 |             expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'pending', approver_email: null }),
  775 |           ]);
  776 |           await expect(employee.page.getByRole('heading').filter({ hasText: String(journey.values.leaveRequestNo) }))
  777 |             .toContainText('Pending hr');
  778 |         },
  779 |         assertInvariants: async () => {
  780 |           await expect.poll(async () => (await notificationRows(String(journey.values.leaveRequestPublicId)))
  781 |             .filter((row) => row.type === 'leave.pending_hr').map((row) => row.recipient_email).sort(), {
  782 |             timeout: 20_000,
  783 |             message: 'the Department Head approval should notify only eligible HR approvers',
  784 |           }).toEqual([...hrRecipients].sort());
  785 |           employee.diagnostics.assertClean('Employee observes Department Head approval live');
  786 |           departmentHead.diagnostics.assertClean('Department Head approves the first leave stage');
  787 |         },
  788 |       });
  789 |     }
  790 | 
  791 |     const rejectActor = rejectionStage === 'department_head' ? departmentHead : hr;
  792 |     const rejectActorUser = rejectionStage === 'department_head' ? departmentHeadUser : hrUser;
  793 |     const rejectStepIndex = rejectionStage === 'department_head' ? 1 : 2;
  794 |     await runProcessStep({
  795 |       name: planned[rejectStepIndex].name,
  796 |       actor: planned[rejectStepIndex].actor,
  797 |       uiPath: planned[rejectStepIndex].uiPath,
  798 |       expectedStatus: planned[rejectStepIndex].expectedStatus,
  799 |       page: rejectActor.page,
  800 |       plannedSteps: planned,
  801 |       context: journey,
  802 |       testInfo,
  803 |       action: async () => {
  804 |         await expect.poll(async () => rejectActor.page.getByRole('button', {
  805 |           name: 'View all Leave Management signals: 1 Leave requests awaiting your approval', exact: true,
> 806 |         }).count(), { timeout: 20_000, message: `${rejectionStage} should receive its actionable leave badge` }).toBe(1);
      |                                                                                                                  ^ Error: department_head should receive its actionable leave badge
  807 |         await openLeaveApprovalFromBadge(rejectActor, 1);
  808 |         const row = rejectActor.page.getByRole('row').filter({ hasText: String(journey.values.leaveRequestNo) });
  809 |         await expect(row).toHaveCount(1);
  810 |         await expect(row).toContainText(rejectionStage === 'department_head' ? 'Pending dept' : 'Pending hr');
  811 |         await expect(row.getByRole('button', { name: 'Reject', exact: true })).toBeEnabled();
  812 |         await row.getByRole('button', { name: 'Reject', exact: true }).click();
  813 |         const dialog = rejectActor.page.getByRole('dialog', { name: 'Reject leave request' });
  814 |         await expect(dialog).toBeVisible();
  815 |         await dialog.getByLabel('Reason for rejection').fill(rejectionReason);
  816 |         const responsePromise = rejectActor.page.waitForResponse((response) =>
  817 |           response.url().includes('/api/v1/leaves/requests/') && response.url().endsWith('/reject')
  818 |             && response.request().method() === 'PATCH',
  819 |         );
  820 |         await dialog.getByRole('button', { name: 'Confirm reject', exact: true }).click();
  821 |         const response = await responsePromise;
  822 |         expect(response.status()).toBe(200);
  823 |         await expect(row).toHaveCount(0);
  824 |       },
  825 |       verify: async () => {
  826 |         const snapshot = await expectLeaveStatus(reason, 'rejected');
  827 |         expect(snapshot.rejection_reason).toBe(rejectionReason);
  828 |         expect(snapshot.dept_approver_email).toBe(rejectionStage === 'department_head' ? null : departmentHeadUser.email);
  829 |         expect(snapshot.hr_approver_email).toBeNull();
  830 |         expect(Number(snapshot.balance_used)).toBe(Number(balances[0].used));
  831 |         expect(Number(snapshot.balance_remaining)).toBe(Number(balances[0].remaining));
  832 |         expect(snapshot.on_leave_attendance_count).toBe(0);
  833 |         if (rejectionStage === 'department_head') {
  834 |           expect(await approvalRows(reason)).toEqual([
  835 |             expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'rejected', approver_email: departmentHeadUser.email }),
  836 |             expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'skipped', approver_email: null }),
  837 |           ]);
  838 |         } else {
  839 |           expect(snapshot.dept_approver_email).toBe(departmentHeadUser.email);
  840 |           expect(await approvalRows(reason)).toEqual([
  841 |             expect.objectContaining({ step_order: 1, role_slug: 'department_head', action: 'approved', approver_email: departmentHeadUser.email }),
  842 |             expect.objectContaining({ step_order: 2, role_slug: 'hr_officer', action: 'rejected', approver_email: hrUser.email }),
  843 |           ]);
  844 |         }
  845 |         await expect(employee.page.getByRole('heading').filter({ hasText: String(journey.values.leaveRequestNo) }))
  846 |           .toContainText('Rejected');
  847 |         await expect(employee.page.getByText(rejectionReason, { exact: true })).toBeVisible();
  848 |         await expect(employee.page.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  849 |         await expect(employee.page.getByRole('button', { name: 'Cancel', exact: true })).toHaveCount(0);
  850 |       },
  851 |       assertInvariants: async () => {
  852 |         const publicId = String(journey.values.leaveRequestPublicId);
  853 |         const expectedTypes = rejectionStage === 'department_head'
  854 |           ? ['leave.rejected', 'leave.submitted']
  855 |           : ['leave.pending_hr', 'leave.rejected', 'leave.submitted'];
  856 |         await expect.poll(async () => (await notificationRows(publicId)).map((row) => row.type).sort(), {
  857 |           timeout: 20_000,
  858 |           message: 'rejection notifications should follow the workflow stage',
  859 |         }).toEqual(expectedTypes);
  860 |         const notices = await notificationRows(publicId);
  861 |         assertRecipientsExactly(notices.filter((row) => row.type === 'leave.submitted').map((row) => row.recipient_email), deptRecipients);
  862 |         assertRecipientsExactly(notices.filter((row) => row.type === 'leave.pending_hr').map((row) => row.recipient_email), rejectionStage === 'hr_officer' ? hrRecipients : []);
  863 |         assertRecipientsExactly(notices.filter((row) => row.type === 'leave.rejected').map((row) => row.recipient_email), [employeeUser.email]);
  864 |         expect(notices.every((row) => row.link_to?.includes(publicId))).toBe(true);
  865 |         const audits = await requestAudits(reason);
  866 |         const rejected = audits.find((audit) => audit.status === 'rejected');
  867 |         expect(rejected).toBeDefined();
  868 |         assertAuditActor({ actor_email: rejected?.actor_email ?? undefined }, rejectActorUser.email);
  869 |         await expect.poll(async () => rejectActor.page.getByRole('button', { name: /^View all Leave Management signals/ }).count()).toBe(0);
  870 |         await expect.poll(async () => hr.page.getByRole('button', { name: /^View all Leave Management signals/ }).count()).toBe(0);
  871 |         await expect.poll(async () => employee.page.getByRole('heading')
  872 |           .filter({ hasText: String(journey.values.leaveRequestNo) }).innerText())
  873 |           .toContain('Rejected');
  874 |         expect(await mailpitIdsFor(intendedRecipients)).toEqual(mailBefore);
  875 |         employee.diagnostics.assertClean('Employee sees the rejected leave and reason live');
  876 |         departmentHead.diagnostics.assertClean('Department Head leave rejection workflow');
  877 |         hr.diagnostics.assertClean('HR leave rejection workflow');
  878 |       },
  879 |     });
  880 | 
  881 |     await captureAnnotatedStep(employee.page, 'employee', 'Employee sees leave rejection reason and unchanged balance', planned.length + 1, testInfo);
  882 |   } finally {
  883 |     await Promise.all([employee.context.close(), departmentHead.context.close(), hr.context.close()]);
  884 |   }
  885 | }
  886 | 
  887 | test('HR04-REJECT-DEPT — Department Head rejection notifies Employee and stops the chain', async ({ browser }, testInfo) => {
  888 |   test.setTimeout(120_000);
  889 |   await runLeaveRejectionJourney(browser, testInfo, 'department_head');
  890 | });
  891 | 
  892 | test('HR04-REJECT-HR — HR rejection notifies Employee after the Department Head approval', async ({ browser }, testInfo) => {
  893 |   test.setTimeout(120_000);
  894 |   await runLeaveRejectionJourney(browser, testInfo, 'hr_officer');
  895 | });
  896 | 
```