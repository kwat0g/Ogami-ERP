# HR03-MISSING-PUNCH — blocked at employee DTR live update

**Result:** The selected-role UI login setup passed. In the business journey, steps 1–4 passed, step 5 failed on a live-update assertion, and step 6 was blocked by the failed step. The attendance correction itself succeeded. The failure is classified as a Medium product defect, FND-HR-002, because an already-open Employee DTR did not refresh after HR corrected the row.

- **Run ID:** `7c85d871-f2f2-4fca-892f-e967e65efc90`
- **Actors:** Employee and HR Officer in separate real UI sessions; the Employee DTR remained open as the second-viewer observer.
- **Baseline-derived workday:** 2026-11-05, Day Shift 06:00–14:00 (Asia/Manila).
- **Execution:** Fresh dedicated E2E baseline; one Chromium worker; no retries; headless.
- **Playwright result:** 1 selected-role auth setup passed; 1 business journey failed; ~42.6 seconds.
- **Environment:** Isolated `ogami-erp-e2e` Compose project and E2E database only.

## Steps and outcomes

| Step | Actor | UI action | Observed result |
|---|---|---|---|
| 1 | Employee | Self-service Dashboard → Time Clock → Time in | Passed. The scheduled Day Shift was shown and the Employee created a `present` attendance row through the UI. |
| 2 | HR Officer | Sidebar → Human Resources → Attendance & DTR; select the work date and Missing time-out filter | Passed. HR found the newly created row through the list and filter. |
| 3 | Employee | Profile menu → DTR | Passed. The open DTR showed the selected date and 06:00 time-in with no time-out. This page remained open during HR's correction. |
| 4 | HR Officer | Open the attendance row → Correct record → fill Time out and run-scoped remarks → Save correction | Passed. The UI `PUT` returned HTTP 200, the dialog closed, and the corrected row disappeared from the Missing time-out list. The read-only assertion confirmed status `present`, time-in 06:00, time-out 14:00, 7.50 regular hours, 0.00 overtime, the run-scoped remarks, and an audit actor of the HR Officer. The filtered list showed “No attendance found.” |
| 5 | Employee observer | Inspect the already-open DTR without reloading | **Failed.** The date row still showed 06:00 and blank time-out/hours, rather than 2:00 PM and 7.5 hours. The web-first assertion retried for 8 seconds and failed. This is the runtime evidence for FND-HR-002. |
| 6 | Employee | Reload DTR and confirm the saved correction | Blocked by step 5 under the process runner. A manual reload was not performed; reload behavior remains unverified. |

## Verification and limits

- The successful correction was independently confirmed through a read-only database query after the UI save. It was attributed to the HR Officer account `e2e+hr_officer@ogami-e2e.test`.
- The UI-to-API action was a normal Employee punch and HR attendance correction. No transactional record was created or advanced through API/DB access.
- No finance, inventory, approval, or PDF effects are part of this attendance correction. The test asserted zero attendance notifications before the Employee observer step. The planned Mailpit before/after comparison occurs in the failed step's invariant and was not reached, so email delta is unverified.
- Employee live-DTR behavior is confirmed stale. Whether a full page reload then displays the correction is unverified because the runner correctly blocked the remaining step.
- The test remains enabled and failing on the live Employee DTR assertion. No application code was changed.

## Observed process

```mermaid
sequenceDiagram
    actor EMP as Employee
    participant CLOCK as Self-service Time Clock
    participant HR as HR Attendance & DTR
    participant DTR as Open Employee DTR
    participant API as Attendance API
    participant DB as Read-only verifier
    EMP->>CLOCK: Time in through Dashboard
    CLOCK->>API: UI punch request
    API-->>CLOCK: 201; row is present with time-in
    HR->>HR: Find row through date and Missing time-out filters
    EMP->>DTR: Open DTR; time-out remains blank
    HR->>HR: Correct record and save 14:00 time-out
    HR->>API: UI correction request
    API-->>HR: 200; correction persisted
    DB-->>HR: present, 06:00–14:00, 7.50 regular hours; HR audit actor
    HR-->>HR: Corrected row leaves Missing time-out list
    DTR-->>EMP: Still shows 06:00 with blank time-out/hours
    Note over EMP,DTR: Live Employee DTR assertion fails; reload step is blocked
```

## Artifacts

Five annotated walkthrough screenshots, Playwright failure screenshots, persisted journey context, error context, and a sanitized trace are archived in [run assets](assets/hr03-missing-punch-attempt-05/). The fifth annotated screenshot captures the stale Employee DTR after HR's successful correction. Trace cookies and session values were redacted before archival.
