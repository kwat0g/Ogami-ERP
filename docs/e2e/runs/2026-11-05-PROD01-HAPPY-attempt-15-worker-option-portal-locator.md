# PROD01-HAPPY — Attempt 15: worker option locator was scoped outside its portal

**Date:** 2026-11-05 UTC  
**Outcome:** Five process steps passed; step 6 failed on a test locator; 15 dependent steps blocked  
**Classification:** Test issue TST-PROD-008  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Fresh guarded E2E baseline, six real-UI actor logins, headless Chromium with tablet/touch emulation, one worker. No host PHP-test interruption was detected.

## Steps and actors

| Step | Actor | Result |
|---|---|---|
| Assign Production shift coverage to the E2E operator | HR Officer | Passed through Attendance & DTR → Shifts → Bulk assign |
| Post baseline RM-001 stock | Warehouse Staff | Passed through Stock Adjustments; status, movement, balance, and audit assertions passed |
| Post baseline RM-010 stock | Warehouse Staff | Passed through Stock Adjustments; exact movement and balance assertions passed |
| Post baseline PKG-001 stock | Warehouse Staff | Passed through Stock Adjustments; exact movement and balance assertions passed |
| Create the WB-001 stock work order | PPC Head | Passed through Work Orders UI; status, material-plan count, zero shortage requests, and unique number assertions passed |
| Find and confirm the work order with an assigned operator | Production Manager | Failed when the test locator found zero options inside the dialog; 15 later steps were blocked |

The baseline, six role sign-ins, and first five business steps passed. The shift assignment, opening stock, and planned work order were created through the respective role UIs. The Production Manager navigated through the Work Orders signal, opened the confirmation dialog, selected manual crew assignment, and searched for the seeded operator by employee number. No crew or schedule confirmation was submitted.

## Failure and correction

The assertion used `review.getByRole('listbox', { name: 'Add worker' })`, where `review` is the confirmation dialog. The option list exists in the accessible UI but `SearchableSelect` renders it with `createPortal` outside that dialog's DOM subtree. The sanitized trace snapshot captured the typed employee number and exactly one matching option in the page-level `Add worker` listbox. The failure was therefore caused by the test's ancestor scope, not by employee eligibility or an absent UI option.

The test now locates the exact listbox from `manager.page`, then retains the exact-one-option and enabled assertions before clicking. This does not broaden the match or weaken the process check. A fresh-baseline run must verify the correction.

## Artifacts

- [Sanitized trace, journey context, screenshots, and five-step walkthrough](assets/prod01-happy-attempt-15/)
- Journey totals: **5 passed, 1 failed, 15 blocked**; one auth-setup test passed with six selected role logins.
- Trace sanitation completed; the runner's final scan reported zero credential-like values remaining.

No product defect is inferred from this attempt. No application code changed.
