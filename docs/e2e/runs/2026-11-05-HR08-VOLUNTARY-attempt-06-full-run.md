# HR08-VOLUNTARY — attempt 06: voluntary separation and final pay passed end to end

**Result:** Passed all 16 ordered steps in one headless Chromium journey. HR, Finance, Department Head, Warehouse Staff and Maintenance Technician acted through their own authenticated UI sessions. The System Administrator used a separate read-only observer session. Every transactional action was performed through the UI; API and database access was read-only verification and negative scope checking.

- **Run:** `f9c7c82f-3da3-4c90-b98f-530e5431e440`; clearance `CLR-202611-0001`; isolated employee `E2E-2FDC0177057D3A5`.
- **Environment:** Guarded E2E stack and fresh seeded baseline; dedicated `ogami_e2e` database and allowlisted `https://nginx`; one headless Chromium worker, one journey, zero retries. Run context spans 16:10:46.538–16:13:10.594 UTC on 2026-11-05. The effective date was 2026-11-06 in the controlled E2E clock.
- **Test result:** 1 authentication setup passed; 1 business journey passed; 16 of 16 named process steps passed. No failure trace was produced because the journey passed.

## Steps and evidence

1. **HR Officer initiated separation from the Employee record.** The UI created the clearance in `in_progress`, moved the employee from `active` to `on_leave`, kept the account active, and exposed the generated clearance. Read-only checks verified the record, audit actor, exact notification audience, and queued email recipients. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/001-hr-initiates-voluntary-separation-from-the-employee-record.png)
2. **Finance Officer followed the actual separation notification.** Finance opened the clearance from its in-app notification and the linked record; the expected state and recipient checks passed. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/002-finance-follows-the-real-separation-notification.png)
3. **System Administrator opened a separate observer view.** The observer reached the clearance through the Separation Clearance list and stayed on that record during subsequent owner actions. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/003-system-administrator-opens-a-separate-live-observer-view.png)
4. **Production Department Head signed “Tools returned.”** The actor found the task through the sidebar clearance signal and signed in the Department Head session. Item signer/time and audit attribution were verified; the clearance remained `in_progress`. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/004-department-head-signs-production-tools-returned.png)
5. **Production Department Head signed “PPE returned.”** The second Production checklist item was signed through the same owner path and verified. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/005-department-head-signs-production-ppe-returned.png)
6. **Warehouse Staff signed “Materials returned.”** Warehouse found the pending task through its own sidebar signal and signed; item, actor and audit checks passed. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/006-warehouse-staff-signs-warehouse-materials-returned.png)
7. **Maintenance Technician signed “No pending maintenance work.”** The Technician found and signed the Maintenance-owned task in the Technician session. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/007-maintenance-tech-signs-maintenance-no-pending-maintenance-work.png)
8. **Finance Officer signed “No outstanding cash advance.”** Finance found the task through its pending-clearance signal and signed as the Finance owner. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/008-finance-officer-signs-finance-no-outstanding-cash-advance.png)
9. **Finance Officer signed “No outstanding company loan.”** The second Finance checklist item was signed through the owner session and verified. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/009-finance-officer-signs-finance-no-outstanding-company-loan.png)
10. **HR Officer signed “Company ID returned.”** HR found the item through its pending-clearance signal and signed. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/010-hr-officer-signs-hr-company-id-returned.png)
11. **HR Officer signed “201 file complete.”** The HR owner action and checklist audit passed. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/011-hr-officer-signs-hr-201-file-complete.png)
12. **HR Officer signed “Exit interview done.”** The final HR-owned item was signed and verified. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/012-hr-officer-signs-hr-exit-interview-done.png)
13. **IT Department Head scope was checked; HR signed “IT equipment returned” as fallback.** The IT Department Head session could not find the Production employee's clearance through the IT scope. HR then found the pending clearance through the real sidebar signal and signed the IT-owned item as the configured all-department fallback. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/013-hr-fallback-signs-it-it-equipment-returned.png)
14. **IT Department Head scope was checked again; HR signed “System accounts disabled” as fallback.** The last checklist item completed the clearance and queued account deactivation. The HR view and open System Administrator observer updated without refresh; read-only checks confirmed all 11 items and the employee account state. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/014-hr-fallback-signs-it-system-accounts-disabled.png)
15. **HR Officer computed final pay.** HR used the clearance's Compute action after completion. The displayed amount was ₱27,272.73; the final-pay breakdown was verified and no journal was posted before finalization. Finance's record view had no final-pay action. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/015-hr-computes-final-pay-after-clearance-completion.png)
16. **HR Officer finalized separation and posted final pay.** HR used Finalize and confirmed through the UI. The clearance became `finalized`, the employee became `resigned`, the account was inactive, the employment history and HR audit were written, and the source-linked journal was posted and balanced. Read-only journal checks confirmed `created_by` is null under source-linked posting policy and `posted_by` is the HR actor. The HR and observer views showed the final state without refresh. [Screenshot](assets/hr08-voluntary-attempt-06/walkthrough/016-hr-finalizes-and-posts-final-pay.png)

## Verified side effects and limits

- Observed lifecycle edges: clearance `in_progress → completed → finalized`; employee `active → on_leave → resigned`; account active → inactive after clearance completion/finalization processing.
- All 11 configured checklist items had the expected owner/fallback signer and audit actor. The clearance stayed open until the last item; the existing session refreshed status and action availability in real time.
- The intended Finance/HR notification audience and queued email delivery were checked; Finance followed its notification link. No unrelated recipient was found. No PDF is generated by this separation/final-pay UI path.
- Final-pay amount formatting, breakdown, posted journal lines/totals, employment history, account state, and HR audit actor were checked read-only. Finance could view the record but had no compute/finalize action.
- The IT Department Head visibility result confirms the code-derived scope gap at runtime. HR fallback completed both IT items. Whether the IT owner should see clearance tasks for employees in other departments remains pending business-owner confirmation under `GAP-HR-08-01`.
- This was the voluntary path only. Termination, missing property, blocked clearance, cancellation/reopen, invalid transitions and direct API authorization denials remain untested in their own planned variants/Phase 4 checks.
- E2E diagnostics completed without unexpected console, page, or HTTP 5xx errors. Passing runs do not retain failure traces.

## Mermaid walkthrough

```mermaid
sequenceDiagram
    actor HR as HR Officer
    actor FIN as Finance Officer
    actor DH as Production Department Head
    actor WH as Warehouse Staff
    actor MT as Maintenance Technician
    actor ITDH as IT Department Head
    actor SA as System Administrator observer
    HR->>HR: Initiate separation through Employee UI
    HR-->>FIN: Queued notice and in-app notification
    FIN->>FIN: Follow notification to clearance
    SA->>SA: Open independent observer view
    DH->>DH: Sign two Production checklist items
    WH->>WH: Sign Warehouse item
    MT->>MT: Sign Maintenance item
    FIN->>FIN: Sign two Finance items
    HR->>HR: Sign three HR items
    ITDH-->>HR: Clearance not visible in IT department scope
    HR->>HR: Sign two IT items as fallback
    Note over HR,SA: Clearance completed; account deactivation queued; observer updates live
    HR->>HR: Compute final pay (no journal yet)
    HR->>HR: Finalize and post balanced journal
    SA-->>SA: Observe Finalized state without refresh
```

## Artifacts

- [Sanitized journey context](assets/hr08-voluntary-attempt-06/journey-context.json)
- [All 16 annotated walkthrough screenshots](assets/hr08-voluntary-attempt-06/walkthrough/)
- No failure trace or video was generated because the journey passed and video is disabled.

**Classification:** Journey pass. TST-HR-076–079 are resolved as test-code issues; attempts 01–05 remain archived failed history and were not counted as successful journeys. No application code changed.
