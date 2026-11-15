# Opening stock balances

The `opening-stock-balances` layer establishes an inventory position at the start of the approved 18-month demo history. It targets only item codes created by the current demo catalog and seeds stock through `StockMovementService` using `StockMovementType::Opening`; it does not insert stock levels or movements directly.

## Scope and quantities

| Scale | Raw-material-classified items, including process consumables | Packaging | Finished goods | Spare parts | Catalog items covered |
|---|---:|---:|---:|---:|---:|
| small | 18 | 4 | 16 | 20 | 58 |
| demo | 18 | 4 | 16 | 100 | 138 |
| full | 18 | 4 | 16 | 140 | 178 |

These counts are the current layer's explicit allowlist and scale plans, not a count of every inventory item in the database. A few planned zero balances are intentional, and the deterministic reorder/safety-stock bands also create below-safety, low, healthy, and overstock examples. Finished-good quantities use a stable spread of production lot sizes. Piece-based items are rounded to whole base units because `UomConversionService` rejects fractional piece quantities.

The current opening date is `--as-of` minus 18 calendar months, at the start of that day. Every movement, stock-level audit, and audit-log row is written under the context's controlled clock. The layer uses active location `A-01` for raw materials, packaging, and consumables; `C-01` for finished goods; and `D-01` for spare parts. The actor is the active `warehouse_staff` account.

Opening quantities are illustrative demo positions derived from each current item's reorder point and safety stock, not a physical count or Ogami stock valuation. The opening cost uses the current catalog standard cost; costs and quantities remain synthetic assumptions documented with the relevant catalog layers. The `RM-005` balance is split into two traceable lots: 10% expires 14 days after the as-of date, and the remainder expires 180 days after it. Other opening lots have no expiry because the current item catalog does not define shelf life for them.

The small-scale deliberate zero-start examples include `RM-003`, `PKG-004`, `SP-020`, and `BC-001`. The deterministic stock-health distribution also produces zero balances for `SP-002` and `SP-014` at small scale. The verifier/test reports the actual list rather than treating only the four scenario codes as zero-stock examples. This layer does not seed negative stock.

## Domain and accounting boundary

Each positive opening quantity uses `StockMovementService::move()` with the current opening movement type, lot, expiry, cost basis, warehouse actor, controlled time, stable idempotency key, and payload fingerprint. That service creates the stock movement and updates the stock level / weighted-average cost. The layer suppresses historical in-app notifications. Existing stock or movement history without this layer's idempotency keys causes a fail-closed error rather than being stacked with another opening balance.

The normal `StockMovementCompleted` outbox listener also runs `AutoReplenishmentService`. In the small smoke data, it generated 16 draft auto-replenishment PRs dated at the history start for the input items whose opening position was at or below reorder point. They are attributed to the configured system actor, each has one item line, and the historical notification scope leaves the notifications table empty. These are domain-generated purchasing records, not manually inserted rows. Later purchasing history must process them or deliberately supersede them; they must not remain as unexplained 18-month-old drafts in the final dataset.

The current `MovementGlPostingService` explicitly classifies opening movements as `not_required`; therefore this stock layer does not create journal entries. Migration `0457_drop_performance_review_tables.php` records that the former combined go-live opening-balance workflow (journal entry plus opening stock receipt) was removed. No current opening-balance page or dedicated combined service remains. Do not present these synthetic stock values as reconciled accounting inventory. Accounting journal history must come from the current journal/document workflows; a finance-approved opening-equity counterpart is not inferred by this layer.

Opening zero balances are represented by the absence of an opening movement and stock-level row. This avoids manufacturing zero-quantity ledger entries. Future transaction layers may create stock for these item codes through their own domain workflows.

## Verification

`OpeningStockBalancesLayerTest` checks the controlled opening date, warehouse actor, positive cost and quantity, lot/expiry spread, `not_required` GL status, movement audit attribution, nonnegative balances, stock-level-to-opening-ledger equality, and a no-drift rerun. A guarded small console smoke is followed by read-only database reconciliation of both opening movements and listener-generated PRs; measured counts and runtime are recorded in `PROGRESS.md` after a completed run. The `RefreshDatabase` test transaction does not run the same after-commit listeners, so console smoke is the evidence for the 16 derived PRs.
