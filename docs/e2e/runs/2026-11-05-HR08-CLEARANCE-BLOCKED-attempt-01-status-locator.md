# HR08-CLEARANCE-BLOCKED — attempt 01: test locator failed on the Employee detail page

**Result:** The UI-created separation and read-only business checks passed. The first process step then failed on a test locator that searched for the clearance status while HR was still on the Employee detail page. The out-of-order finalization step was blocked and did not run. This is a test issue, not an application finding.

- **Run ID:** `447315c3-1a29-49c4-ba38-35bd6a9f30ae`.
- **Environment:** Guarded E2E stack; fresh migration and baseline seeding; dedicated `ogami_e2e` database and allowlisted `https://nginx`; 1 headless Chromium worker, 0 retries. The PHP-test monitor remained clear during startup and the journey. The stack was stopped after the run, with named volumes preserved.
- **Runner result:** Authentication setup passed. The business test failed in its first step's final UI invariant; the second step was marked blocked. No out-of-order request was sent.

## Evidence

1. **HR started a separation through the Employee UI.** Read-only checks passed for the `in_progress` clearance, 11 pending items, `on_leave` employee, active account, `created` audit by HR, configured notification audience, and opted-in initiation email recipients. The Employee detail visibly showed the `On leave` badge. [Failure screenshot](assets/hr08-clearance-blocked-attempt-01/test-failed-2.png)
2. **The final UI invariant looked for “In progress” without leaving the Employee detail.** No clearance detail had been opened yet, so the locator found no such text. The screenshot and error context show HR remained on the Employee page after the expected initiation toast. [Sanitized Playwright error context](assets/hr08-clearance-blocked-attempt-01/error-context.md)
3. **The out-of-order attempt did not execute.** The second step would have navigated through Sidebar → Separation Clearance, checked that Finalize was absent, and sent a negative HR-session PATCH expected to return 422. It is untested by this attempt.

## Correction

Assert the Employee page's visible `On leave` status in step 1. In step 2, navigate through the Separation Clearance sidebar list to the actual clearance record before checking its `In progress` status and absent Finalize action. Keep the source-derived 422 and no-side-effect assertions. No application code changed.

## Artifacts

- [Journey context](assets/hr08-clearance-blocked-attempt-01/journey-context.json)
- Failure screenshots 1 and 2
- Sanitized `trace.zip` and Playwright error context

**Classification:** Test issue `TST-HR-080`, open pending a fresh-baseline rerun. No product defect or process gap was demonstrated.
