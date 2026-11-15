# Purchasing history

Status: implemented in the purchasing-history unit, stacked after CRM pricing. This is a compact process and status dataset, not a month-by-month demand history.

## Layer and dependencies

`purchasing-history` depends on `maintenance-spare-parts`, `supplier-listings`, `opening-stock-balances`, and `workforce-identities`. It uses the current `PurchaseRequestService`, `PurchaseOrderService`, `RequestForQuoteService`, `SupplierQuoteService`, `RfqAwardService`, `SupplierResponseService`, `VendorSourcingService`, and supplier portal invitation/password-reset services. Transactional rows, approvals, supplier replies, document records, audit activity, and generated document numbers come from those application paths.

The layer selects the active, positively priced source for `RM-001` through `VendorSourcingService`. PR and PO approval steps come from the active workflow definitions, and the high-value PO quantity is calculated from the app's active VP approval threshold and current supplier price. Historical actions use the shared as-of clock and notification suppression. The active supplier portal account uses a reserved `.test` email and the configured shared demo password.

## Small-scale coverage

The verified run used `--as-of=2026-11-15 --scale=small --seed=20261115`.

| Process record | Rows | Statuses |
|---|---:|---|
| Named purchase requests | 19 | draft 1, pending 2, approved 5, rejected 1, converted 9, cancelled 1 |
| Purchase orders linked to named scenario PRs | 9 | draft 1, pending approval 2, approved 1, sent 1, acknowledged 1, supplier proposed 1, supplier declined 1, cancelled 1 |
| RFQs | 5 | draft, open, closed, awarded, cancelled; one each |
| Supplier quotations | 3 | draft 1, submitted 1, awarded 1 |
| Opening-stock-generated replenishment PRs | 16 | 1 converted through approval to PO; 15 valid editable drafts with owning department and sourcing method assigned |
| Supplier PO responses | 3 | accept, propose, decline; each linked to its PO and active supplier portal user |
| Formal quotation documents | 2 | synthetic PDFs uploaded through the RFQ document service and linked to submitted quotes |

The small supplier-listing profile establishes one priced `RM-001` supplier. A losing bid is therefore not a meaningful small-scale state. At larger scales, when `VendorSourcingService` returns another positively priced supplier, the awarded-RFQ scenario invites that supplier, captures an alternate quote, and exercises `not_awarded`.

The opening-stock listener creates all 16 replenishment PRs. To keep the generated PO and approval volume bounded, the layer completes one chain at small scale, three at demo scale, and five at full scale; it edits the remaining PRs through the normal service into ready-to-submit drafts. This preserves the auto-replenishment source data without generating a redundant PO for every stock-health example.

## Named demonstration scenarios

All markers use the `DEMO-PURCHASING:` prefix in PR reason, RFQ title, quote notes, or PO remarks.

| Marker | Starting state | Demonstration |
|---|---|---|
| `PR-DRAFT` | Draft | Edit and submit a department request |
| `PR-PENDING-FINANCE` | Awaiting Finance | Review the first PR approval step |
| `PR-PENDING-VP` | Awaiting Vice President | Continue a high-value approval chain |
| `PR-REJECTED` | Rejected | Inspect approver note and rejection audit |
| `PR-CANCELLED` | Cancelled | Inspect a withdrawn requirement |
| `PO-DRAFT` | Draft | Edit or submit an order |
| `PO-PENDING-FINANCE` | Awaiting Finance | Review a PO approval |
| `PO-PENDING-VP` | Awaiting Vice President | Review the high-value approval step |
| `PO-APPROVED` | Approved; manual dispatch required | Open the approved PO and continue supplier transmission |
| `PO-SENT` | Sent | Inspect the supplier-facing order |
| `PO-ACKNOWLEDGED` | Acknowledged | Review the supplier's acceptance |
| `PO-SUPPLIER-PROPOSED` | Supplier proposed changes | Review the proposed delivery change |
| `PO-SUPPLIER-DECLINED` | Supplier declined | Review supplier's declined response |
| `PO-CANCELLED` | Cancelled | Inspect a historical cancellation |
| `RFQ-DRAFT` / `RFQ-OPEN` | Draft / accepting quotations | Continue publication and capture a quote |
| `RFQ-CLOSED` | Closed | Inspect a submitted quotation after close |
| `RFQ-AWARDED` | Awarded | Inspect the winning quote and linked award |
| `RFQ-CANCELLED` | Cancelled | Inspect the cancellation trail |

The approved-only PO is created before the supplier portal user is invited. Current application behavior auto-publishes an approved PO to an active portal recipient and advances it to `sent`, so a portal-backed PO cannot remain parked at `approved`. POs used for send/response scenarios are approved after the invitation exists and follow the automatic portal publication path.

Submitted quote scenarios use a generated one-page PDF that identifies itself as fictional demo data with no commercial value. The file is uploaded through `RequestForQuoteService::uploadInternalDocument`; its filename and contents are deterministic. The captured quote values are synthetic sourcing examples derived from the current supplier price, not vendor commitments (F-021).

## Verification evidence and limits

- Focused test: `PurchasingHistoryLayerTest`, 1 test / 28 assertions, 4:55, 107 MiB. It checks current PR, PO, RFQ, and quote states, linked PDF files, the manual-dispatch ledger, and a second service-layer run with unchanged PR, PO, PO-line, RFQ, quote, document, supplier-response, dispatch, and audit fingerprints.
- Guarded console smoke: `demo:seed --only=purchasing-history --scale=small --as-of=2026-11-15 --seed=20261115`, run only against `ogami_test` with the demo guard explicitly allowlisting that DB and local DB host. Before the run, the existing reference baseline seeders `RolePermissionSeeder`, `SettingsSeeder`, `ShiftSeeder`, `LeaveTypeSeeder`, `HolidaySeeder`, and `WorkflowSeeder` were applied to `ogami_test`. Eleven selected/dependency layers completed in about 2:23; the purchasing-history layer took 55.06 seconds and process peak was 78.5 MiB.
- Identical console reruns completed with Purchasing history in under one second and retained 19 named PRs, 9 scenario POs, 5 RFQs, 3 quotes, 16 opening-generated PRs, 3 supplier responses, and the same 2 PDF records. Both PDF files existed on the local storage disk and were linked to quote records.
- Read-only approval checks found current pending records for Finance and Vice President on both PR and PO workflows (one each). Every generated approval action is performed by an actor resolved from the actual approval-chain role.
- A current application warning suppresses the manual-dispatch inbox alert because the `purchasing.view` recipients lack subject access to the PO; see F-031. The seeder does not add substitute notification rows.
- Receiving (GRNs), incoming QC execution, supplier invoices, and AP payments are not part of this unit; they remain for the next Purchasing/Procure-to-Pay transaction unit. No full/demo scale console seed or complete `demo:verify` has run.

The `demo:credentials` command on the guarded database lists the supplier portal account and configured shared demo password alongside internal demo accounts. Do not copy any account from a real environment into the defense handout.
