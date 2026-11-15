# HR03-OVERTIME-APPROVE — attempt 03

**Result:** Employee submission succeeded. The Department Head dashboard loaded after submission and received the current overtime badge count, but the journey stopped before approval because the test looked for a nested sidebar signal after its temporary Human Resources expansion had collapsed. This is a test-navigation failure, not evidence of stale backend badge data.

- **Run ID:** `17b2efaa-b235-45ce-a46a-ffe664cb6e8d`
- **Actors:** Employee and Department Head in separate browser contexts.
- **Baseline:** Fresh isolated E2E baseline created by the guarded runner; only the `ogami-erp-e2e` database and volumes were reset.
- **Execution:** One Chromium worker, no retries; selected-role auth setup passed and the business journey failed after 16.4 seconds.
- **Environment:** Isolated `ogami-erp-e2e` Compose project. The local/dev database was not used.

## Steps and outcomes

| Step | Actor | Outcome |
|---|---|---|
| Submit overtime request | Employee | Passed through the UI. The saved request remained `pending`; the expected Department Head notification recipient assertion passed. |
| Load Department dashboard | Department Head | The page was opened after the Employee submitted. The authorized `GET /api/v1/dashboards/badges` returned HTTP 200 with `overtime.count = 1`; the dashboard displayed the Pending Approvals row and the collapsed Human Resources sidebar showed count 1. |
| Open overtime signal and approve | Department Head | Not reached. The sidebar's temporary expansion collapsed before the helper found the nested signal button. No approval was performed. |

The broadcast worker log showed successful handling of `OvertimeRequestChanged` and `BadgesChanged` jobs during the run. The Department Head page was not open during the mutation, so this run does not prove that an already-open peer received the WebSocket event. It also does not verify the final API-reader assertion from attempt 02.

## Follow-up

The helper now waits for the Human Resources section to expand and hovers the visible Overtime link so the temporary section stays open, matching the established Leave approval helper. Its E2E TypeScript project compiles. No browser rerun was performed, and the Overtime approval, already-open peer refresh, and badge-menu flow remain unverified. The raw Playwright trace was removed after extracting the limited request/result evidence because it contained session cookies; no cookie values are retained in this report.
