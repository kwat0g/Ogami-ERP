# Purchasing receiving and payables history

## Scope

The `purchasing-receiving-history` layer covers the current GRN, incoming inspection, supplier-invoice, AP bill, and supplier-payment workflows. It depends on `purchasing-history` and uses the PO scenarios it creates. Transactional records are created through the current `GrnService`, `InspectionService`, `NcrService`, `SupplierInvoiceService`, and `BillService` paths. The layer uses the supplied `--as-of` instant in Asia/Manila, skips weekends and active dates in the app's holiday table, and uses deterministic scenario keys; historical notifications are suppressed, while the current pending-QC receipt remains visible to its intended in-app audience.

## Small-scale scenario distribution

| Workflow | Seeded scenario coverage |
| --- | --- |
| Goods receipts | One expected-receipt draft; five accepted receipts; one partially accepted receipt; one rejected receipt; one receipt pending incoming QC |
| Incoming QC | Five passing inspections; two failed inspections linked to supplier-return dispositions; one inspection awaiting live QC work |
| Supplier invoices and bills | Four supplier invoice submissions; a draft bill for the partially accepted receipt; one cancelled draft bill; unpaid, partially paid, paid, and payment-voided bill scenarios |
| Payments | Posted payments for partial and full settlement; one posted payment subsequently voided through the reversal workflow |
| Inventory and accounting effects | Accepted receipt quantities flow through GRN/QC settlement into the stock ledger; accepted/partial-accepted billable receipts stage supplier bills; posting and payment use the app's journals |

The source PO scenarios are the current `PO-SENT` and `PO-ACKNOWLEDGED` records from Purchasing history. The receiving quantity is bounded by those PO lines. Inspection samples follow the active item inspection plan, and failure evidence uses either the app's checklist capture or tolerance limits. Partial acceptance is supported by the app's MRB/sort disposition flow and includes a recovery-pass reference.

All emails and portal actors come from the upstream synthetic `.test` workforce and supplier portal records. Supplier invoice numbers and receiving lot references are explicitly synthetic. The layer does not add real contact or government-identifying data.

## Intentionally open coverage

- `BillPaymentStatus::PendingApproval` and `Rejected` are not represented here. The current bill-payment workflow routes payments above its configured ₱50,000 threshold for VP approval, while the acknowledged PO available to this layer is a low-quantity PO. A pending/rejected payment scenario needs a high-value PO that can legally reach supplier acknowledgement and receipt; the layer does not inflate a PO or bypass its purchase and approval flow.
- AP aging beyond the due date is not represented. The upstream acknowledged PO is recent, and this layer's open/unpaid bills therefore demonstrate current AP state rather than overdue aging. A later dated procurement/payables history layer must create the older source PO and bill chain before it can seed overdue balances plausibly.
- Supplier debit notes, returns after receipt, and invoice mismatch/rejection cases are not added because the receiving/AP path here does not yet build those complete linked document chains.
- The date window is a small in-flight receiving sample, not the planned 12–24-month purchasing/receiving trend history. Those month-by-month transactions remain a later history unit.
- The upstream Purchasing history still applies calendar-day offsets to some PO approval, transmission, and supplier-response events. With the Sunday as-of test anchor, that produced weekend approval/dispatch dates; this receiving layer does not rewrite upstream actions. F-032 records the specific dates and a follow-up schedule correction.

These limits are coverage gaps, not assertions that the UI or workflow lacks the feature. They remain open in the system coverage matrix until a later layer supplies a valid upstream scenario.

## Verification

`PurchasingReceivingHistoryLayerTest` runs the layer with its declared dependencies on the isolated, allowlisted `ogami_test` database. It checks every current `GrnStatus`, every current `BillStatus`, the holiday-aware partial receipt date, and an idempotent direct rerun of this layer. The visible PHPUnit run passed (1 test / 12 assertions, 9:20.5, 111 MiB); PHP lint, Pint, and targeted PHPStan pass. Service writes use the app's stock, inspection, NCR, supplier-invoice, journal, bill, payment, and audit paths. The focused test checks the resulting document states and repeatability; it does not replace the complete ledger, journal, and coverage checks planned for `demo:verify`. The demo database is not used by the test.
