# HR03-PUNCH — full run

**Result:** Passed, 2/2 Playwright tests (selected-role real-UI sign-in and the business journey). The business journey took 20.0 seconds; total Playwright duration was 30.4 seconds. It ran on a fresh dedicated E2E baseline with one worker and no retries.

- **Run ID:** `ebc00bc0-0c97-45fd-837b-4227f971709b`
- **Actors:** Employee and HR Officer in separate browser contexts and their own seeded sessions.
- **Baseline-derived input:** Employee `E2E-2FDC0177057D3A5`, work date 2026-11-05, Day Shift 06:00–14:00, and plant geofence settings read before the journey. The gated E2E clock supplied the Manila workday and shift-end time.
- **UI route:** Employee Dashboard → Time Clock; HR sidebar → Human Resources → Attendance & DTR; Employee Profile menu → DTR.

## Steps and outcomes

| Step | Actor | UI action | Observed result |
|---|---|---|---|
| 1 | Employee | Open Self-service Time Clock | Day Shift displayed; no open punch; Time in enabled. |
| 2 | HR Officer | Open Attendance & DTR and filter to 2026-11-05 | No employee row existed before the punch. |
| 3 | Employee; HR Officer observes | Select Time in | UI returned HTTP 201; the record became `present` with a 06:00 in-punch. The HR row appeared without refresh. |
| 4 | Employee; HR Officer observes | After the gated clock advanced to shift end, select Time out | UI returned HTTP 201; the out-punch was 14:00, the HR row updated without refresh, and regular time totaled 7.50 hours. |
| 5 | Employee | Open the DTR from the Profile menu | The employee DTR showed the same date, 06:00 in, 14:00 out, 7.5 hours, and Present status. |

## Verified effects

- Read-only database assertions confirmed one attendance row with status `present`, local `time_in` 06:00 and `time_out` 14:00, two ordered punch rows, 7.50 regular hours, 0.00 overtime hours, and zero tardiness/undertime.
- Both punches matched the baseline plant coordinates, were within the configured geofence radius, and met the configured accuracy limit. The UI rendered Manila-local time and the plant-centre distance.
- Four audit entries were attributed to the Employee actor. Attendance notification count remained zero, and Mailpit message IDs and Employee-recipient messages were unchanged.
- The HR observer received both row changes without refresh. Employee and HR page diagnostics remained clean; no unexpected console, uncaught, failed-request, or 5xx errors were reported.
- No financial, inventory, approval, badge, or email side effects were expected or observed.

## Observed process

```mermaid
sequenceDiagram
    actor EMP as Employee
    participant SELF as Self-service Time Clock
    participant HR as HR Attendance & DTR
    participant API as Attendance punch endpoint
    participant DB as Read-only verifier
    EMP->>SELF: Open Time Clock from Dashboard
    HR->>HR: Filter Attendance & DTR to work date
    EMP->>SELF: Time in at 06:00
    SELF->>API: UI punch request
    API-->>SELF: 201; attendance present
    HR-->>HR: Row appears without refresh
    Note over EMP,SELF: E2E clock advances to Day Shift end
    EMP->>SELF: Time out at 14:00
    SELF->>API: UI punch request
    API-->>SELF: 201; DTR totals recomputed
    HR-->>HR: Out time and 7.50 hours appear without refresh
    EMP->>SELF: Open DTR from Profile menu
    DB-->>HR: Read-only assertion of punches, audit, GPS and side effects
    SELF-->>EMP: Same 06:00–14:00 Present row
```

The annotated walkthrough, persisted journey context, and detailed step snapshots are in [run assets](assets/hr03-punch-full-run/). Attempts 01 and 02 exposed E2E verifier issues TST-HR-030 and TST-HR-031; both are resolved by this clean-baseline pass. The attached-runner interruption is separately recorded [here](2026-11-04-HR03-PUNCH-interrupted-attempt.md).

