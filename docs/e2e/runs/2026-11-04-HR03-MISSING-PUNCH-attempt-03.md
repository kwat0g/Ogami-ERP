# HR03-MISSING-PUNCH — attempt 03

**Result:** The Employee time-in and HR missing-time-out list lookup passed. HR's filtered row rendered without the “Missing” marker, so the correction step and employee DTR checks were blocked.

- **Run ID:** `680611ac-9f26-4434-b577-134566d64e04`
- **Date / shift:** 2026-11-05 / Day Shift, 06:00–14:00 (Asia/Manila)
- **Observed:** The guarded Laravel clock had advanced to 2026-11-06, and the API returned the record through `missing_time_out=1`. The browser remained at the host date (2026-11-04 in UTC / Manila) and therefore rendered the selected 2026-11-05 record's empty time-out as an em dash rather than “Missing.” No correction was submitted. The UI list did show the record, time-in, zero computed hours, and Present status.
- **Cause / classification:** Test issue TST-HR-034. The test advanced server time without aligning the browser's `Date`; the UI's `today` calculation is client-side. No product defect inferred.
- **Correction:** Set each acting/observing page's fixed browser date to the same Manila instant used by the guarded clock. Playwright fixed time leaves timers active, so realtime delivery and query refresh continue.
- **Evidence:** [failure trace and screenshots](assets/hr03-missing-punch-attempt-03/trace.zip), [error context](assets/hr03-missing-punch-attempt-03/error-context.md), [journey context](assets/hr03-missing-punch-attempt-03/journey-context.json), and [step screenshot](assets/hr03-missing-punch-attempt-03/001-employee-records-time-in-for-a-scheduled-shift.png).

