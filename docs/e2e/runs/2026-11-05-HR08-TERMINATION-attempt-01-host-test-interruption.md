# HR08-TERMINATION — attempt 01: process steps completed; guarded run interrupted

**Overall result:** Environment interruption; this is not counted as a completed variant. The journey context records all 16 ordered process steps as passed, including finalization, but the host-test monitor detected a PHP test during the guarded Playwright invocation and terminated that invocation with exit code 91. The expected Playwright summary was not emitted, so the run cannot be treated as a clean end-to-end result.

- **Run ID:** `61760ec1-512a-4462-ac84-1d49ba4e8e65`; clearance `CLR-202611-0001`.
- **Environment:** Guarded E2E stack; fresh migrations and E2E reference baseline; dedicated `ogami_e2e` database; allowlisted `https://nginx`; headless Chromium; one worker; zero retries. The supervisor observed a clean 300-second PHP-test quiet interval before startup. It later detected a PHP test during the journey, sent termination to the runner process group, and stopped the E2E stack. No E2E containers remain running. The E2E volume is disposable and will be reset before a clean rerun.
- **Journey evidence:** The context records all 16 named steps as passed from 17:18:06 to 17:20:31 UTC. Authentication setup had passed for the eight selected actors. The outer runner exited 91 after the guard event; there is no final Playwright pass/fail summary.

## Ordered steps recorded as passed

1. HR initiated termination through the Employee UI; clearance and configured notification checks passed. [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/001-hr-initiates-termination-separation-from-the-employee-record.png)
2. Finance followed the actual separation notification to the clearance. [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/002-finance-follows-the-real-separation-notification.png)
3. System Administrator opened the separate observer view. [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/003-system-administrator-opens-a-separate-live-observer-view.png)
4. Production Department Head signed “Tools returned.” [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/004-department-head-signs-production-tools-returned.png)
5. Production Department Head signed “PPE returned.” [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/005-department-head-signs-production-ppe-returned.png)
6. Warehouse Staff signed “Materials returned.” [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/006-warehouse-staff-signs-warehouse-materials-returned.png)
7. Maintenance Technician signed “No pending maintenance work.” [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/007-maintenance-tech-signs-maintenance-no-pending-maintenance-work.png)
8. Finance Officer signed “No outstanding cash advance.” [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/008-finance-officer-signs-finance-no-outstanding-cash-advance.png)
9. Finance Officer signed “No outstanding company loan.” [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/009-finance-officer-signs-finance-no-outstanding-company-loan.png)
10. HR Officer signed “Company ID returned.” [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/010-hr-officer-signs-hr-company-id-returned.png)
11. HR Officer signed “201 file complete.” [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/011-hr-officer-signs-hr-201-file-complete.png)
12. HR Officer signed “Exit interview done.” [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/012-hr-officer-signs-hr-exit-interview-done.png)
13. HR signed “IT equipment returned” as the configured fallback after the IT Department Head scope check. [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/013-hr-fallback-signs-it-it-equipment-returned.png)
14. HR signed “System accounts disabled”; the final checklist item completed and deactivation checks passed. [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/014-hr-fallback-signs-it-system-accounts-disabled.png)
15. HR computed final pay after clearance completion; Finance remained read-only. [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/015-hr-computes-final-pay-after-clearance-completion.png)
16. HR finalized the separation and posted final pay; the step context records the terminal employee-status, employment-history, audit, journal-balance, and live-observer assertions as passed. [Screenshot](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/016-hr-finalizes-and-posts-final-pay.png)

The actor-owned business actions were made through their own UI sessions. The test context also records its read-only state assertions. Because the guarded invocation overlapped a detected PHP test and did not produce its final runner result, this attempt remains environment-interrupted and requires a clean-baseline run before HR08-TERMINATION is counted complete. No product defect or process gap is inferred from this interruption.

## Mermaid walkthrough

```mermaid
sequenceDiagram
    actor HR as HR Officer
    actor FIN as Finance Officer
    actor DH as Production Department Head
    actor WH as Warehouse Staff
    actor MT as Maintenance Technician
    actor SA as System Administrator observer
    HR->>HR: Initiate termination through Employee UI
    HR-->>FIN: Separation notice and notification
    FIN->>FIN: Follow notification to clearance
    SA->>SA: Open independent observer view
    DH->>DH: Sign Production checklist items
    WH->>WH: Sign Warehouse item
    MT->>MT: Sign Maintenance item
    FIN->>FIN: Sign Finance checklist items
    HR->>HR: Sign HR and IT fallback items
    HR->>HR: Compute final pay
    HR->>HR: Finalize and post final-pay journal
    Note over HR,SA: All 16 step contexts passed; outer runner interrupted by PHP-test guard
```

## Artifacts

- [Journey context](assets/hr08-termination-attempt-01-host-test-interruption/journey-context.json)
- [All 16 annotated walkthrough screenshots](assets/hr08-termination-attempt-01-host-test-interruption/walkthrough/)
- No sanitized trace or Playwright summary was produced before the runner was terminated; video is disabled.

**Classification:** Environment interruption under `ENV-E2E-008`. No application code changed. Rerun only after a fresh isolated baseline and guarded PHP-test quiet interval.
