# PROD01-HAPPY — Attempt 17: locator tap auto-scroll hid the location option

**Date:** 2026-11-05 UTC  
**Outcome:** Two process steps passed; step 3 failed while selecting the RM-010 location; 18 dependent steps blocked  
**Classification:** Test interaction issue under investigation, TST-PROD-009  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Fresh guarded E2E baseline, one auth-setup test with six selected role logins, headless Chromium, one worker. The five-minute host PHP-test quiet interval cleared before startup; no PHP-test interruption was detected during the run.

## Steps and actors

| Step | Actor | Result |
|---|---|---|
| Assign Production shift coverage to the E2E operator | HR Officer | Passed through Attendance & DTR → Shifts → Bulk assign; read-only assignment assertion passed |
| Post baseline RM-001 stock | Warehouse Staff | Passed through Stock Adjustments; stock, movement, and actor assertions passed |
| Post baseline RM-010 stock | Warehouse Staff | Failed while selecting the visible Main Warehouse option; the adjustment form was not submitted |
| Post baseline PKG-001 stock and run the stock work order through production and outgoing quality | Warehouse Staff, PPC Head, Production Manager, Production Employee, QC Inspector | Blocked; 18 dependent steps remain |

The clean baseline and all six role logins succeeded. The shift assignment and RM-001 adjustment were created through the owning role's UI. The RM-010 form displayed its item and the exact “MW-A-A-01 · Main Warehouse” option. `locator.tap()` saw the option visible and stable, then its automatic scroll action caused Playwright to report the option no longer visible and subsequently detached. The screenshot shows the dropdown closed and the Location field still blank. The test did not review or submit the RM-010 adjustment.

## Failure and next check

Attempt 16's ordinary locator click had also failed to select the PKG-001 location. Attempt 17 used a real `locator.tap()` in the touch-enabled browser, but its auto-scroll behavior moved/repositioned the portal option before dispatching the tap. This run therefore still does not establish whether a user tapping the visible option can select it.

The test now checks that the accessible option is in the viewport, reads its bounding box, and sends `page.touchscreen.tap()` at the center of that visible option. It retains the exact role/text locator and asserts that the Location combobox displays the selected text. If the popup still closes or the field stays blank after this direct touch input, record a production UI defect and keep `PROD01-HAPPY` blocked at the Warehouse location step. No application code changed.

## Artifacts

- [Sanitized trace, journey context, error context, and screenshots](assets/prod01-happy-attempt-17/)
- Journey totals: **2 passed, 1 failed, 18 blocked**; one auth-setup test passed with the six selected role logins.
- Trace sanitation completed; the runner's final scan reported zero credential-like values remaining.

The failed step did not create an RM-010 stock adjustment. No PKG-001 balance, work order, production output, or quality inspection was created in this attempt.
