# INV01-TRANSFER — Full run

**Date:** 2026-11-04 UTC  
**Result:** Passed  
**Environment:** Dedicated `ogami-erp-e2e` Compose project; fresh `ogami_e2e` baseline  
**Playwright:** 2 tests passed (three selected actor logins plus the business journey), Chromium headless, one worker, no retries  
**Journey time:** 39.2 seconds  
**Journey run ID:** `26cf1712-93be-4f39-853b-4db383bcb33e`

## Actors and record

- `warehouse_staff` — created the prerequisite adjustment and transfer order.
- `warehouse_staff__2` — independently executed the transfer.
- `system_admin` — opened the transfer list/detail as a live observer.
- Transfer order: `TO-202611-0001`, 0.500 kg of RM-001, source `A-01`, destination `A-02`.
- The run-scoped reason began `E2E-26cf1712-93b`.

All transactional records were created or advanced through the UI in the owning actor's logged-in session. The prerequisite 1 kg inbound adjustment was also submitted through Stock Adjustments UI. Database access was read-only and used only for assertions.

## Steps and results

| # | Actor | UI action | Result and invariant checks |
|---|---|---|---|
| 1 | `system_admin` | Opened Warehouse → Inventory Transfers before any transfer existed. | Empty list shown; read-only query confirmed no transfer with this run's reason. Realtime status reached Live. |
| 2 | `warehouse_staff` | Posted a 1 kg, ₱120 RM-001 inbound adjustment through Warehouse → Stock Adjustments. | Low-value adjustment posted as approved. Read-only query confirmed the source location held 1 kg and the adjustment had exactly one stock movement. |
| 3 | `warehouse_staff` | Created a 0.500 kg transfer from `A-01` to `A-02`. | Order reached Pending. The creator saw the maker/checker message and no Execute button. Cancel remained available. The observer found the new order in the already-open list, opened it, and saw Pending without refreshing. Read-only checks confirmed 1 kg at source, 0 at destination, and no transfer movement. |
| 4 | `warehouse_staff__2` | Found the pending order in Inventory Transfers, reviewed the confirmation, and executed it. | Order reached Transferred; execute and cancel actions disappeared. The observer's open detail updated to Transferred without refresh. Read-only checks confirmed exactly one 0.500 kg movement, the independent executor as actor, and the source/destination balances moved to 0.500 kg each. |

## Final read-only state

| Field | Observed value |
|---|---|
| Transfer order | `TO-202611-0001`, `transferred`, 0.500 kg |
| Location balances | `A-01`: 0.500 kg; `A-02`: 0.500 kg |
| Transfer movement | Exactly 1 row; type `transfer`; 0.500 kg at ₱120.0000/kg; total ₱60.00 |
| Movement actor | `warehouse_staff__2` |
| Accounting | `journal_entry_id` null; GL handoff `not_required` for a location transfer |
| Transfer notifications | 0 |
| Transfer audit actors | Creator and executor both present |

Transfer creation/execution has no email or PDF dispatch in the traced controller/service/observer path. The entity emits a Reverb refresh signal; both the list and open observer detail were verified live.

## Screenshots

1. Observer starts from the transfer list: ![Empty Inventory Transfers list](assets/inv01-transfer-2026-11-04/001-observer-opens-inventory-transfers-before-work-exists.png)
2. UI-created stock prerequisite: ![Approved stock adjustment](assets/inv01-transfer-2026-11-04/002-warehouse-posts-1-kg-as-a-ui-created-stock-prerequisite.png)
3. Observer follows the pending hand-off: ![Observer sees pending transfer](assets/inv01-transfer-2026-11-04/003-observer-finds-the-pending-transfer-live.png)
4. Maker sees pending state and no Execute action: ![Transfer created by Warehouse maker](assets/inv01-transfer-2026-11-04/003-warehouse-maker-creates-a-pending-transfer-order.png)
5. Independent Warehouse actor executes: ![Independent executor confirms transfer](assets/inv01-transfer-2026-11-04/004-independent-warehouse-executor-finds-and-executes-the-transfer.png)
6. Observer confirms status/action update: ![Observer sees transferred state](assets/inv01-transfer-2026-11-04/004-observer-confirms-transfer-and-actions-close-live.png)

## Observed process

```mermaid
sequenceDiagram
    participant O as system_admin observer
    participant M as warehouse_staff maker
    participant E as warehouse_staff__2 executor
    participant UI as Inventory Transfers UI
    participant DB as Inventory ledger
    M->>UI: Post 1 kg prerequisite adjustment
    UI->>DB: Approved adjustment movement
    O->>UI: Open Inventory Transfers list
    M->>UI: Create 0.500 kg transfer A-01 → A-02
    UI-->>M: Pending; creator cannot Execute
    UI-->>O: Pending transfer appears live
    E->>UI: Find transfer through list
    E->>UI: Execute and confirm
    UI->>DB: One stock transfer movement
    UI-->>O: Transferred status appears live
```

## Attempt history and limits

The first three business attempts exposed test-only errors: SQL selected a nonexistent `full_code` column; the item-search option was scoped inside a modal although the SearchableSelect listbox is portalled to the document; and a generic `role=status` locator matched the live indicator and toast messages. The assertions were corrected to use the schema's `code` column, the page-level accessible option, and exact maker instruction text. None of those attempts reached transfer execution.

A fourth invocation completed the first three journey steps, including the live pending hand-off, but the command combined baseline seeding and browser execution and was terminated before the executor step completed. It is recorded as an environment interruption, not a pass or product failure. The final run kept setup and the single journey in separate stages of one active orchestration session and passed.

The earlier failed-run Playwright output directory was reused by identical test titles, so its first two traces/screenshots were overwritten before they could be archived. The attempt errors and correction are retained in this report; archived evidence is available for the third and interrupted attempts under `assets/inv01-transfer-attempt-03/` and `assets/inv01-transfer-attempt-04/`. The final passing run has the six annotated screenshots above and its `journey-context.json` in the final asset directory.

This journey covers transfer creation and execution. Pending-transfer cancellation, insufficient-stock rejection, duplicate submit, stale/concurrent execute, and non-authorized role API/UI denial remain separate planned checks.
