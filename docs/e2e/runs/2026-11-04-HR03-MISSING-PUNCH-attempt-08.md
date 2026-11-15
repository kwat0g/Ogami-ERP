# HR03-MISSING-PUNCH — attempt 08

**Result:** Passed both selected Playwright tests (auth setup and the HR03 journey). The scenario completed in 20.7 seconds; total selected run time was 30.9 seconds.

- **Run ID:** a7f135a9-5bc5-44a6-a606-dfd346ec0aca
- **Started:** 2026-11-04 15:43:26.254 UTC
- **Actors:** Employee and HR Officer in separate browser contexts.
- **Baseline:** Reset isolated E2E database, seeded with 37 actors. The local dev database was not used.
- **Mutation:** Employee recorded time-in. HR corrected the missing time-out through Attendance & DTR; the update API returned HTTP 200 and the HR row left the active Missing time-out filter.
- **Peer observation:** The Employee's already-open DTR displayed the corrected 14:00 time-out and 7.5 regular hours without reload. The explicit reload step then passed and retained the correction. Read-only assertions confirmed present status, 06:00–14:00 local times, 7.50 regular hours, HR audit attribution, and zero attendance notifications.
- **Email isolation:** The test now compares messages addressed to the Employee and HR actors only. No new message was addressed to either actor. Unrelated recipients are excluded from this journey's no-email invariant.
- **Event evidence:** Read-only E2E outbox inspection found exactly one created and one updated AttendanceChanged row for the run, both published. The dedicated broadcasts worker logged both jobs as completed. No raw browser WebSocket frame was archived, so transport receipt was not independently captured.
- **Timing:** HR correction step completed at 15:43:40.646 UTC; the Employee observation step completed at 15:43:42.300 UTC, a 1.654-second step-bound interval. This is one sample between step boundaries, not exact event-to-render latency and not a p95/SLO measurement.
- **Limits:** This verifies one Employee/HR correction flow and the Employee DTR list. Separate record detail, action buttons, linked records, badges, dashboard, inbox, and broader module coverage remain unverified.

Artifacts: [screenshots and sanitized timeline](assets/hr03-missing-punch-attempt-08/).
