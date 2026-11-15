# Order-to-Cash Demo History Plan

## Goal

Add a deterministic, idempotent Order-to-Cash demo layer that creates a believable Sales order through the current application service and parks it at the live confirmation step.

## Scope for this increment

- Depend on current CRM pricing and workforce layers so active customer agreements and the Sales actor exist.
- Create an internal Sales order through `SalesOrderService::quoteOrder` and `SalesOrderService::create`, with a fixed seed marker, controlled timestamps, and the seeded Sales actor.
- Keep it in the application's native `draft` state as the live-start scenario; the Sales officer can confirm it to launch the MRP chain.
- Check the stored sales-order totals, customer/product agreement, Sales audit actor, order line, and timestamp; rerun the layer and prove it does not duplicate or drift.
- Use the existing demo runner's side-effect scope for transport suppression.

## Explicit limits

This increment does not create a portal-submitted `awaiting_confirmation` order, delivery, proof-of-delivery file, invoice, collection, return, or credit note. The customer portal availability service requires mapped finished goods to come from accepted QC-released production output (or a stock allocation). The current production demo has a partial work order in progress but no released finished goods. Its runtime finish-first setting also cannot complete the current QC-required routing, so this layer does not create saleable stock by bypassing that gate. Dispatch requires validated shipment assignment and proof; confirmation creates inventory/finance side effects; and collection requires finance-authorized account/receipt details. Those paths need verified slices and are not to be fabricated with direct writes.

The current layer therefore covers Sales order entry and the live confirmation/MRP start. It does not claim full O2C status coverage. The lack of QC-released finished goods and the finish-first/routing incompatibility are recorded as limitations for the subsequent O2C fulfilment slice.

## Implementation steps

1. Add a focused feature test that exercises the configured layer, checks the real Sales order chain and replay idempotence, and confirms test DB rollback.
2. Run the focused test red before adding the layer.
3. Implement `order-to-cash-history` with a fixed seed marker, natural actor authorization through service boundary, controlled as-of timestamp, and no raw transactional inserts.
4. Register the layer after its actual dependency keys.
5. Run the focused test, formatter, targeted static analysis, guarded small seed and replay only against the allowlisted `ogami_test` database.
6. Reconcile order totals, status, audit provenance, and duplicate counts; record findings and limitations in `docs/demo-data/` and `PROGRESS.md`.

## Acceptance checks

- One order exists for the seed-owned marker, status `draft`.
- The linked Sales actor is authorized, and the customer/product has an active approved price agreement.
- Its line total equals the order subtotal and its VAT/total follow the current service calculation.
- Its `created_at` is controlled by the requested as-of date; audit actor is the Sales user.
- A second execution keeps the same order ID and database fingerprint.
- The layer passes the demo DB guard and changes no production/demo database during tests.

## Risks to investigate

- Customer portal order availability requires QC-released finished stock; use the current service's gates, never bypass them.
- Confirming a sales order emits durable chain events and starts MRP. It is intentionally left as the live action for this record.
- The currently configured default application clock remains the real run date; historical service calls must be wrapped with the demo context clock.
