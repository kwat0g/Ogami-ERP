# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-separation.process.spec.ts >> HR08-VOLUNTARY — voluntary separation through departmental clearance and final pay
- Location: e2e-process/hr-separation.process.spec.ts:414:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('Finalized', { exact: true })
Expected: visible
Error: strict mode violation: getByText('Finalized', { exact: true }) resolved to 2 elements:
    1) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-caption font-medium leading-tight whitespace-nowrap transition-colors duration-fast bg-success-bg text-success-fg">Finalized</span> aka locator('span').filter({ hasText: 'Finalized' })
    2) <div class="text-xs text-primary">Finalized</div> aka getByRole('region', { name: 'Document progress' }).getByText('Finalized')

Call log:
  - Expect "toBeVisible" getByText('Finalized', { exact: true }) with timeout 8000ms
  - waiting for getByText('Finalized', { exact: true })

```

# Test source

```ts
  617 |           }
  618 |         },
  619 |         assertInvariants: async () => {
  620 |           await assertRealtimeItem(adminObserver, item.label);
  621 |           const current = (await clearanceSnapshot(target.employee_no, remarks))[0];
  622 |           if (current.status === 'completed') {
  623 |             await expect(adminObserver.page.getByText('Completed', { exact: true }),
  624 |               'the second viewer should receive the completed status without refresh').toBeVisible();
  625 |           }
  626 |           acting.diagnostics.assertClean(`${owner.actor} signs ${item.item_key}`);
  627 |           adminObserver.diagnostics.assertClean(`live observer receives ${item.item_key} clearance update`);
  628 |         },
  629 |       });
  630 |     }
  631 | 
  632 |     const completed = (await clearanceSnapshot(target.employee_no, remarks))[0];
  633 |     const finalPayPlanIndex = plannedSteps.length - 2;
  634 |     await runProcessStep({
  635 |       ...plannedSteps[finalPayPlanIndex], page: hr.page, plannedSteps, context: journey, testInfo,
  636 |       action: async () => {
  637 |         await openClearanceFromList(hr, target.employee_no);
  638 |         await expect(hr.page.getByRole('heading', { name: completed.clearance_no, exact: true })).toBeVisible();
  639 |         await expect(hr.page.getByText('Completed', { exact: true })).toBeVisible();
  640 |         const financeRecord = await openFinanceClearanceForPermissionCheck(finance, target.employee_no, completed.clearance_no);
  641 |         expect(financeRecord.status).toBe('completed');
  642 |         await expect(finance.page.getByRole('button', { name: 'Compute final pay', exact: true }),
  643 |           'Finance may view the completed clearance but must not execute HR final-pay actions').toHaveCount(0);
  644 |         await expect(finance.page.getByRole('button', { name: 'Finalize', exact: true })).toHaveCount(0);
  645 |         await expect(hr.page.getByRole('button', { name: 'Compute final pay', exact: true })).toBeEnabled();
  646 |         const responseWait = hr.page.waitForResponse((response) => response.request().method() === 'POST'
  647 |           && /\/clearances\/[^/]+\/final-pay\/compute(?:\?|$)/.test(new URL(response.url()).pathname));
  648 |         await hr.page.getByRole('button', { name: 'Compute final pay', exact: true }).click();
  649 |         const response = await responseWait;
  650 |         expect(response.status(), 'HR final-pay computation should succeed after all clearance items').toBe(200);
  651 |       },
  652 |       verify: async () => {
  653 |         const snapshot = await waitForClearance(target.employee_no, remarks, 'completed');
  654 |         expect(snapshot.final_pay_computed).toBe(true);
  655 |         expect(snapshot.final_pay_amount).not.toBeNull();
  656 |         expect(snapshot.journal_entry_id).toBeNull();
  657 |         const net = Number(snapshot.final_pay_amount);
  658 |         expect(Number(snapshot.final_pay_breakdown?.net)).toBe(net);
  659 |         const audits = await clearanceAudits(snapshot.clearance_id);
  660 |         const computeAudit = audits.filter((audit) => audit.action === 'hr.clearance.final_pay_computed').at(-1);
  661 |         assertClearanceAuditActor(computeAudit, hrActor.email);
  662 |       },
  663 |       assertInvariants: async () => {
  664 |         await expect(hr.page.getByRole('button', { name: 'Finalize', exact: true })).toBeEnabled();
  665 |         await expect(hr.page.getByText(/Final pay breakdown/)).toBeVisible();
  666 |         const finalPay = Number((await clearanceSnapshot(target.employee_no, remarks))[0].final_pay_amount);
  667 |         await assertPhpCurrency(hr.page.getByText('Final pay', { exact: true }).locator('..'), finalPay);
  668 |         await expect(adminObserver.page.getByText('Completed', { exact: true })).toBeVisible();
  669 |         await assertRealtimeItem(adminObserver, checklist.at(-1)!.label);
  670 |         hr.diagnostics.assertClean('HR computes final pay after complete clearance');
  671 |       },
  672 |     });
  673 | 
  674 |     const finalPayComputed = (await clearanceSnapshot(target.employee_no, remarks))[0];
  675 |     await runProcessStep({
  676 |       ...plannedSteps[plannedSteps.length - 1], page: hr.page, plannedSteps, context: journey, testInfo,
  677 |       action: async () => {
  678 |         const finalize = hr.page.getByRole('button', { name: 'Finalize', exact: true });
  679 |         await expect(finalize).toBeEnabled();
  680 |         await finalize.click();
  681 |         const dialog = hr.page.getByRole('dialog', { name: 'Finalize separation?' });
  682 |         await expect(dialog).toBeVisible();
  683 |         const responseWait = hr.page.waitForResponse((response) => response.request().method() === 'PATCH'
  684 |           && /\/clearances\/[^/]+\/finalize(?:\?|$)/.test(new URL(response.url()).pathname));
  685 |         await dialog.getByRole('button', { name: 'Finalize', exact: true }).click();
  686 |         const response = await responseWait;
  687 |         expect(response.status(), 'HR finalization should post the final-pay journal').toBe(200);
  688 |       },
  689 |       verify: async () => {
  690 |         const snapshot = await waitForClearance(target.employee_no, remarks, 'finalized');
  691 |         expect(snapshot.employee_status).toBe('resigned');
  692 |         expect(snapshot.employee_user_active).toBe(false);
  693 |         expect(snapshot.final_pay_computed).toBe(true);
  694 |         expect(snapshot.final_pay_amount).toBe(finalPayComputed.final_pay_amount);
  695 |         expect(snapshot.journal_entry_id).not.toBeNull();
  696 |         expect(snapshot.finalized_by_email).toBe(hrActor.email);
  697 |         const journal = await journalSnapshot(snapshot.clearance_id);
  698 |         expect(journal).toHaveLength(1);
  699 |         expect(journal[0].status).toBe('posted');
  700 |         // Source-linked journal entries leave created_by null; the actor is
  701 |         // recorded as the poster in posted_by by the final-pay workflow.
  702 |         expect(journal[0].created_by_email).toBeNull();
  703 |         expect(journal[0].posted_by_email).toBe(hrActor.email);
  704 |         expect(journal[0].line_count).toBeGreaterThan(0);
  705 |         expect(Number(journal[0].total_debit)).toBe(Number(journal[0].total_credit));
  706 |         expect(Number(journal[0].line_debit)).toBe(Number(journal[0].line_credit));
  707 |         expect(Number(journal[0].line_debit)).toBe(Number(journal[0].total_debit));
  708 |         const history = await separationHistory(snapshot.employee_id);
  709 |         expect(history).toHaveLength(1);
  710 |         expect(history[0].status).toBe('finalized');
  711 |         expect(history[0].separation_reason).toBe('resigned');
  712 |         expect(history[0].actor_email).toBe(hrActor.email);
  713 |         const audits = await clearanceAudits(snapshot.clearance_id);
  714 |         assertClearanceAuditActor(audits.at(-1), hrActor.email);
  715 |       },
  716 |       assertInvariants: async () => {
> 717 |         await expect(hr.page.getByText('Finalized', { exact: true })).toBeVisible();
      |                                                                       ^ Error: expect(locator).toBeVisible() failed
  718 |         await expect(hr.page.getByText(/JE posted:/)).toBeVisible();
  719 |         await expect(adminObserver.page.getByText('Finalized', { exact: true }),
  720 |           'the second viewer should receive finalization without refresh').toBeVisible();
  721 |         await assertRealtimeItem(adminObserver, checklist.at(-1)!.label);
  722 |         hr.diagnostics.assertClean('HR finalizes voluntary separation');
  723 |         adminObserver.diagnostics.assertClean('observer receives clearance finalization');
  724 |       },
  725 |     });
  726 | 
  727 |     journey.values.finalPayAmount = (await clearanceSnapshot(target.employee_no, remarks))[0].final_pay_amount;
  728 |     journey.values.journal = (await journalSnapshot(finalPayComputed.clearance_id))[0];
  729 |   } finally {
  730 |     if (frozenClock) await clearClock(request);
  731 |     for (const session of contexts.values()) await session.context.close();
  732 |   }
  733 | });
  734 | 
  735 | function financeActorEmail(actors: E2EActor[]): string {
  736 |   return actorByName(actors, 'finance_officer').email.toLowerCase();
  737 | }
  738 | 
  739 | async function openFinanceClearanceForPermissionCheck(
  740 |   finance: ActorPage,
  741 |   employeeNo: string,
  742 |   clearanceNo: string,
  743 | ): Promise<{ status: string }> {
  744 |   // Sidebar.tsx renders the HR section as Payroll for finance_officer.
  745 |   await openClearanceFromList(finance, employeeNo, 'Payroll');
  746 |   await expect(finance.page.getByRole('heading', { name: clearanceNo, exact: true })).toBeVisible();
  747 |   await expect(finance.page.getByText('Completed', { exact: true })).toBeVisible();
  748 |   const snapshots = await readOnlyDatabaseJson<Array<{ status: string }>>(`
  749 |     SELECT status::text AS status FROM clearances WHERE clearance_no = ${sqlText(clearanceNo)}
  750 |   `);
  751 |   return snapshots[0] ?? { status: 'missing' };
  752 | }
  753 | 
```