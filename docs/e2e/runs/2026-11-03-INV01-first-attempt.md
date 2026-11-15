# INV-01 first process run

**Date:** 2026-11-03 UTC  
**Environment:** Dedicated `ogami-erp-e2e` Compose project; database `ogami_e2e`  
**Process:** INV-01 — high-value inbound warehouse adjustment and Finance approval  
**Actors:** `warehouse_staff`, then `finance_officer` (Finance hand-off was not reached)  
**Result:** Step 1 created and submitted the adjustment through the Warehouse UI. A read-only assertion failed on numeric formatting; the remaining two steps were blocked.

## Run result

The focused run used headless Chromium, one worker, retries disabled, and the role-login setup passed for all 35 seeded E2E actors. In the business journey, the Warehouse UI action and pending-row verification succeeded. The step then failed because the database reader returned an unscaled zero (`"0"`) for a missing stock-level row while the assertion expected `"0.000"`. This is recorded as test issue [TST-INV-001](../FINDINGS.md#test-and-environment-issues), not an application finding. The query was corrected to normalize the numeric scale; the journey must restart from a fresh E2E baseline before any further run.

## Steps and actors

| # | Actor | UI path / action | Expected | Result |
|---:|---|---|---|---|
| 1 | `warehouse_staff` | Warehouse → Stock Adjustments → New adjustment → submit inbound RM-001, 250 kg at ₱120/kg | Pending Finance approval; no stock movement before approval; audit and recipient checks pass | UI creation and pending row succeeded. Read-only DB data showed pending status, ₱30,000 value, no movement ID, and zero on hand. The invariant failed only on the textual zero scale. Remaining step checks in this step were not reached. |
| 2 | `finance_officer` | Work → Notifications → adjustment approval notice → linked record | Finance finds work from the notification, sees Approve/Reject, requester cannot approve | Blocked because step 1's full invariant failed. |
| 3 | `finance_officer` | Linked adjustment row → Approve | Approved status; one stock movement for 250 kg; Warehouse observer sees the update live | Blocked. |

No process record was created or advanced through API or database writes. The single pending adjustment was created through the Warehouse UI. No approval, movement, or downstream process was attempted. The baseline is being reset before the corrected run so this partial record cannot affect it.

## Environment diagnosis

Earlier focused diagnostic runs stopped before form submission. The HTTPS E2E proxy initially did not forward Reverb's `/app/` WebSocket route, then held a stale Docker service address after Reverb was recreated. The E2E-only Nginx config now forwards `/app/` to Reverb using Docker DNS refresh. A read-only WebSocket probe returned `101 Switching Protocols` through HTTPS, and the journey reached the stock-adjustment invariant without a browser or network error. This is recorded as resolved environment issue `ENV-E2E-001`; no application behavior was changed.

## Evidence and limits

- The Warehouse role's seeded permissions include `inventory.adjust`; the actor had no approval permission.
- The test reached `/inventory/stock-adjustments` through the Warehouse sidebar item. Its heading appeared under a web-first assertion after route loading.
- The read-only DB result matched the expected pending adjustment fields except for textual scale on on-hand zero. Because the assertion failed before later invariants, threshold, audit actor, and notification-recipient assertions were not reached.
- Finance hand-off, approval, stock movement, observer update, and final side effects remain unverified.
- No retries, sleeps, API mutations, or application fixes were used.

