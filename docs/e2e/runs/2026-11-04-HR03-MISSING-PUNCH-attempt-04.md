# HR03-MISSING-PUNCH — attempt 04

**Result:** The first three process steps passed. HR submitted a correction through its own UI session and received HTTP 200. The test stopped on an incorrect expectation that the corrected row would remain visible in a list filtered to Missing time-out; employee observer checks were blocked.

- **Run ID:** `182546b0-aeb0-4881-852e-d37a23388dfe`
- **Date / shift:** 2026-11-05 / Day Shift, 06:00–14:00 (Asia/Manila)
- **UI action:** HR selected the Employee row, filled Time out and a run-scoped remark, and selected Save correction. The modal closed after the successful UI `PUT`.
- **Read-only DB result:** Attendance `present`, time-in 06:00, time-out 14:00, regular hours 7.50, overtime 0.00; audit entries identify `e2e+hr_officer@ogami-e2e.test` as the correction actor. The read-only check used the isolated E2E reader.
- **Incorrect assertion:** The test expected “2:00 PM” in the row while the active filter specifically selects records with no time-out. The correct UI expectation is that the corrected row leaves that filtered list.
- **Evidence:** [trace, screenshots and context](assets/hr03-missing-punch-attempt-04/); the trace records the UI `PUT` (HTTP 200) and subsequent filtered list `GET` (HTTP 200 with zero rows). The visible-row expectation stopped the journey before the employee realtime check.
- **Classification:** Test issue TST-HR-035. This attempt does not establish a product defect; the next clean-baseline run asserts the correct row-removal outcome and captures the read-only state before that UI assertion.

