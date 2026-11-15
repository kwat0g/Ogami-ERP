# PROD01-HAPPY — Attempt 16: warehouse location option detached during selection

**Date:** 2026-11-05 UTC  
**Outcome:** Three process steps passed; step 4 failed during the PKG-001 stock adjustment; 17 dependent steps blocked  
**Classification:** Test interaction issue under investigation, TST-PROD-009  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Fresh guarded E2E baseline, one auth-setup test with six selected role logins, headless Chromium, one worker. The five-minute host PHP-test quiet interval cleared before startup; no PHP-test interruption was detected during the run.

## Steps and actors

| Step | Actor | Result |
|---|---|---|
| Assign Production shift coverage to the E2E operator | HR Officer | Passed through Attendance & DTR → Shifts → Bulk assign; read-only assignment assertion passed |
| Post baseline RM-001 stock | Warehouse Staff | Passed through Stock Adjustments; stock, movement, and actor assertions passed |
| Post baseline RM-010 stock | Warehouse Staff | Passed through Stock Adjustments; stock, movement, and actor assertions passed |
| Post baseline PKG-001 stock | Warehouse Staff | Failed while selecting the visible Main Warehouse option; the adjustment form was not submitted |
| Create and run the stock work order, then complete production and outgoing quality | PPC Head, Production Manager, Production Employee, QC Inspector | Blocked; 17 dependent steps remain |

The fresh baseline and all six role logins succeeded. The HR shift assignment and the first two stock adjustments were created through their owning role's UIs. At the PKG-001 step, the page displayed the stock-adjustment form and the “MW-A-A-01 · Main Warehouse” option. The test's `locator.click()` timed out while waiting for that option to remain stable; Playwright reported that it detached from the DOM. The location field remained unselected, and no review or submit action occurred.

## Failure and next check

The failure occurred before the locator dispatched a click. The same location option interaction had succeeded for the two earlier stock items in this run. The component is `SearchableSelect`, which renders its options in a document-level portal. The present trace does not establish whether this was a test input mismatch or a UI touch-stability defect.

The test now uses Playwright `locator.tap()` in the touch-enabled context and asserts that the Location combobox displays the selected option text. This preserves the exact option and business path while matching the journey's tablet input. Attempt 17 must determine whether the touch action selects the location reliably; if it reproduces the detachment, classify it as an application/UI finding. No product code changed.

## Artifacts

- [Sanitized trace, journey context, error context, and screenshots](assets/prod01-happy-attempt-16/)
- Journey totals: **3 passed, 1 failed, 17 blocked**; one auth-setup test passed with the six selected role logins.
- Trace sanitation completed; the runner's final scan reported zero credential-like values remaining.

The failed step did not create a PKG-001 stock adjustment. No work order, production output, or quality inspection was created in this attempt.
