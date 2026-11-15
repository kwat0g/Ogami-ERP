# INV01-TRANSFER-CANCEL — Full run

**Date:** 2026-11-04 UTC  
**Result:** Passed  
**Environment:** Dedicated `ogami-erp-e2e` Compose project; freshly reseeded `ogami_e2e` baseline  
**Playwright:** 2 tests passed (real UI sign-in for the two selected actors and the Chromium journey), one worker, no retries  
**Journey time:** 40.5 seconds  
**Journey run ID:** `7d8c2fd7-2288-419d-827d-6813f942bf62`

## Actors and record

- `warehouse_staff` — created UI-only prerequisite stock, created the transfer, then cancelled it as its maker.
- `system_admin` — opened the transfer list and detail as a second, read-only observer.
- Transfer order: `TO-202611-0001`, 0.500 kg RM-001 from `A-01` to `A-02`.
- Run-scoped reason began `E2E-7d8c2fd7-228`.

The test reset the E2E database before the journey. Both transactional records (the 1 kg inbound adjustment and the transfer) were created through their UI forms. The transfer cancellation was performed in the creator's own authenticated session. Database access was read-only and used only for assertions.

## Steps and results

| # | Actor | UI action | Result and invariant checks |
|---|---|---|---|
| 1 | `system_admin` | Opened Warehouse → Inventory Transfers before work existed. | Empty state displayed; read-only query confirmed no matching transfer. Realtime connection reached Live. |
| 2 | `warehouse_staff` | Posted a 1 kg RM-001 inbound adjustment through Warehouse → Stock Adjustments. | Low-value adjustment was approved by the UI path; read-only assertion confirmed 1 kg at source and one adjustment movement. |
| 3 | `warehouse_staff` | Created a 0.500 kg transfer from `A-01` to `A-02`. | Transfer reached Pending. Maker saw the maker/checker instruction, no Execute action, and an enabled Cancel action. The observer found it in the already-open transfer list and saw Pending without refresh. Assertions confirmed 1 kg at source, 0 at destination, zero transfer movements, and zero transfer notifications. |
| 4 | `warehouse_staff` | Chose Cancel, reviewed the “No stock movement will be recorded” confirmation, and confirmed cancellation. | Transfer reached Cancelled; Cancel and Execute actions disappeared. The open observer detail updated without refresh. Read-only assertions confirmed unchanged location balances, zero transfer movements, zero transfer notifications, and the Warehouse maker as the audit actor. |

## Final read-only state

| Field | Observed value |
|---|---|
| Transfer order | `TO-202611-0001`, `cancelled`, 0.500 kg |
| Location balances | `A-01`: 1.000 kg; `A-02`: 0.000 kg |
| Transfer movements | 0 |
| Transfer notifications | 0 |
| Transfer audit actor | `warehouse_staff` |
| Accounting | No transfer movement or GL journal was created |

The transfer cancellation path produced an audit entry and a live status change. No email or PDF behavior was found in the exercised transfer path; this journey did not claim email/PDF coverage.

## Annotated walkthrough

1. Observer opens the empty transfer list: ![Empty transfer list](assets/inv01-transfer-cancel-2026-11-04/walkthrough/001-observer-opens-inventory-transfers-before-work-exists.png)
2. Warehouse creates stock through the adjustment form: ![UI-created stock prerequisite](assets/inv01-transfer-cancel-2026-11-04/walkthrough/002-warehouse-posts-1-kg-as-a-ui-created-stock-prerequisite.png)
3. Maker creates the pending transfer: ![Pending transfer](assets/inv01-transfer-cancel-2026-11-04/walkthrough/003-warehouse-maker-creates-a-pending-transfer-order.png)
4. Observer finds Pending in its already-open list: ![Observer sees Pending](assets/inv01-transfer-cancel-2026-11-04/walkthrough/003-observer-finds-pending-transfer-before-cancellation.png)
5. Maker cancels the pending transfer: ![Maker cancels transfer](assets/inv01-transfer-cancel-2026-11-04/walkthrough/004-warehouse-maker-cancels-the-pending-transfer.png)
6. Observer sees Cancelled without refresh: ![Observer sees Cancelled](assets/inv01-transfer-cancel-2026-11-04/walkthrough/004-observer-confirms-cancellation-without-stock-movement.png)

The run's typed context and step timestamps are archived at [journey-context.json](assets/inv01-transfer-cancel-2026-11-04/journey-context.json).

## Observed process

```mermaid
sequenceDiagram
    participant O as system_admin observer
    participant W as warehouse_staff maker
    participant UI as Inventory Transfers UI
    participant DB as Inventory ledger
    W->>UI: Post 1 kg prerequisite through Stock Adjustments
    UI->>DB: Approved adjustment movement
    O->>UI: Open Inventory Transfers list
    W->>UI: Create 0.500 kg transfer A-01 → A-02
    UI-->>W: Pending; Execute unavailable, Cancel available
    UI-->>O: Pending transfer appears live
    W->>UI: Cancel and confirm
    UI->>DB: Status cancelled; audit actor recorded; no transfer movement
    UI-->>O: Cancelled status appears without refresh
```

## Attempts and limits

The first invocation stopped at Playwright compilation because `openTransfers` was scoped inside the sibling transfer-execution test. The helper was moved to the shared spec scope; `npm run typecheck:e2e` and the final fresh-baseline journey then passed. No browser journey was started by the failed compile attempt.

Insufficient-stock rejection, duplicate submission, stale/concurrent cancellation, role/scope denials, and the legacy `completed` enum state remain untested or unreachable and are tracked separately in the process catalog.
