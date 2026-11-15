# Order-to-Cash history slice

Implementation is in draft [PR #545](https://github.com/kwat0g/kwatog/pull/545), stacked on production execution history.

## What this layer creates

`order-to-cash-history` depends on `crm-pricing`, which supplies fictional customers, active products, accepted price agreements, and the Sales demo account. It creates one internal Sales order through `SalesOrderService::quoteOrder()` and `SalesOrderService::create()`:

- Status: `draft`
- Seed marker in notes: `DEMO-O2C-LIVE:SO-DRAFT-0001`
- Customer and product: first active customer/product pair with an active accepted price agreement for the planned delivery date
- Quantity: 500 pieces
- Delivery date: ten business days after `--as-of`
- Payment method and terms: bank transfer and the current customer master terms
- Created by: `sales.officer@accounts.ogami-demo.test`, at 09:15 Asia/Manila on the as-of day or the previous business day when the as-of anchor falls on a weekend/holiday

The service calculates the order number, agreement price, line total, VAT, order total, audit row, and timestamps. The layer refuses to overwrite or rewind a seed-owned order if someone has moved it past Draft; reset to the supported snapshot before replaying a scenario that has been continued in the UI.

## Live start

Log in as the Sales Officer, open CRM Sales Orders, find the draft with marker `DEMO-O2C-LIVE:SO-DRAFT-0001`, review its customer, agreed price, quantity, payment terms, and delivery date, then confirm it. Confirmation is the next live action and should emit the application's normal order-confirmation chain to MRP. The demo seed does not pre-confirm the order or fabricate linked work orders or purchase requests.

The UI click path has not yet been browser-verified. Check the Sales order list and confirmation action during the next integrated smoke run before presenting this as a rehearsed path.

## Current limits

This is one draft-stage transaction, not the complete O2C history. It does not yet contain portal awaiting-confirmation orders, delivery stages, delivery proof, invoices, collections, returns, or customer credit notes. No O2C status coverage claim is made.

The customer portal deliberately enforces available finished-stock checks. Current upstream production history leaves a partially executed work order and does not release outgoing-QC stock. The configured finish-first runtime and the demo's QC-required routings are also incompatible at the production-completion guard. These gates and the investigation are recorded as [F-036](FINDINGS.md). Do not bypass them with inventory, inspection, delivery, or invoice inserts.

## Verification

`OrderToCashHistoryLayerTest` passes (1 test, 12 assertions). It checks the Sales-created Draft state, current price-agreement link, line/subtotal/VAT/total reconciliation, Sales creator/audit attribution, controlled timestamp, and unchanged fingerprint on layer replay. It uses a rolled-back `ogami_test` transaction.

The guarded console smoke used the exact `ogami_test` database after `migrate:fresh --seed` and operational reference seeders. Its first attempt completed the initial layers, then stopped in CRM pricing because the array mailer had no sender. The retry with a reserved `.test` sender resumed idempotently and completed all nine selected/dependency layers in 14.05 seconds at 74.5 MiB peak; the identical replay took 7.26 seconds at 64.5 MiB. The clean-first total runtime was not measured because the first attempt stopped before completion.

Read-only reconciliation found one order, one line, and one Sales audit row: `SO-202611-0001`, `CH-001`, 500 pieces at ₱48.30, line/order total ₱24,150.00, 0.00 VAT, order date 2026-11-13 09:15 Asia/Manila, requested delivery date 2026-11-27, status `draft`. Pint and targeted PHPStan pass. The test and console commands did not target the demo database; the full PHP suite and system-wide `demo:verify` were not run because the complete demo dataset is not yet assembled.

The first guarded console attempt stopped in CRM pricing because the local array mailer had no `MAIL_FROM_ADDRESS`. It was rerun successfully with `no-reply@ogami-demo.test` and the demo runner's transport suppression; no external email was sent.
