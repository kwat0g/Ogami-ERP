# HR04-INSUFFICIENT-BALANCE — full run

**Result:** Passed, 2/2 Playwright tests (selected-role UI setup and the business journey). The isolated baseline had 37 seeded E2E actors and no process transactions. Journey duration was 20.5 seconds; total command duration was 38.0 seconds.

- **Run ID:** `455fb2f1-f2f9-4974-bccb-ad9c2bdb7c7a`
- **Actors:** Employee acted through the Employee session; Department Head and HR were logged in separately and observed no approval badge.
- **UI path:** Profile menu → Leaves → New request → select Vacation Leave → enter dates and reason → Submit request.
- **Input:** November 6–24, 2026; 16 business days requested against the seeded 15.0-day balance.
- **Outcome:** The Employee UI POST received HTTP 422 with `Insufficient leave balance for 2026 (15.0 remaining; 16 requested).` The form remained open and the UI displayed `Failed to submit leave request.`
- **Read-only invariants:** No leave request, approval row, leave-request audit, active leave attendance, new leave notification, or email was created. `used` and `remaining` balances were unchanged. Department Head and HR approval badges remained at zero. The exact expected 422 console resource message was consumed once; other page diagnostics remained enabled.
- **Finding:** UX-HR-004 records that the UI hides the specific business-rule explanation behind a generic toast. It does not block correct refusal or data integrity.
- **Test issues resolved:** TST-HR-028 (unit-aware form estimate locator) and TST-HR-029 (one-use expected 422 diagnostic allowance) are resolved after clean-baseline runs.

## Observed process

```mermaid
sequenceDiagram
    actor EMP as Employee
    participant UI as Self-service Leaves
    participant API as Leave request API
    participant E2E as E2E read-only assertions
    participant DB as PostgreSQL
    EMP->>UI: Submit 16-day Vacation Leave request (15.0 remaining)
    UI->>API: POST request through the UI
    API-->>UI: 422 insufficient balance
    UI-->>EMP: Generic failure toast; form stays open
    E2E->>DB: Read-only no-side-effect assertions
    DB-->>E2E: No transaction rows; leave balance unchanged
```

The annotated step screenshot and persisted journey context are in [run assets](runs/assets/hr04-insufficient-balance-full-run/).
