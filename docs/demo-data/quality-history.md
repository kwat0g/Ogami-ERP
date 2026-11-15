# Quality and customer complaint history

The `quality-history` layer depends on `purchasing-receiving-history`. It adds four customer complaints and their linked NCRs against the synthetic active customer and seeded FC-001 fuse-box cover. It adds 12 CAPA action rows: three each for the incoming NCR and the overdue, resolved, and closed complaint NCRs. Complaint records and their 8D reports are created through `ComplaintService`; NCR actions, disposition, and closure use `NcrService`.

| Story | Complaint state at as-of | Quality state | Demo use |
|---|---|---|---|
| `OPEN-WITHIN-CLOCK` | Open, D3 due time is still ahead | Linked NCR remains open | Start a customer complaint while its response clock is running |
| `OPEN-OVERDUE` | Open, D3 deadline has passed | Linked NCR is in progress with overdue containment, corrective, and preventive actions | Show the overdue response and CAPA work |
| `RESOLVED` | Resolved | Finalized 8D report and closed NCR with a rework disposition | Review a resolved complaint before the final close step |
| `CLOSED` | Closed | Finalized 8D report and closed NCR with a rework disposition | Review a completed complaint and its linked quality evidence |
| Incoming partial acceptance | No customer complaint | Existing incoming NCR stays open/in progress with containment, corrective, and preventive actions | Follow a supplier-lot issue from receiving QC into CAPA |

Complaint creation, 8D edits/finalization, complaint resolution/closure, NCR actions, disposition, and NCR closure all use the current CRM and Quality services. Historical complaint creation runs with notification suppression. The current complaint service does not create an internal in-app notification for its linked NCR; the layer does not fabricate one, and F-034 records that gap. Dates use the requested as-of anchor, and historical work and deadlines use business days from the seeded holiday calendar.

## Current workflow limits

The `ComplaintStatus` enum also contains `investigating` and `cancelled`. The current `ComplaintService` exposes no operation to begin investigation or cancel a complaint; its supported lifecycle operations are create, resolve, and close. This layer does not write either status directly. See finding F-033.

Complaints are not linked to a sales order or delivery item in this layer because no O2C delivery history is registered yet. `ComplaintService` permits a complaint with an active customer and product as its source; the complaint and NCR chain remain valid, but this sample does not demonstrate shipment provenance. The layer provides representative scenarios, not 12–24 months of complaint trend history or a complete CAPA effectiveness/verification lifecycle.

All identities and product references are synthetic demo data. Complaint quantities and narratives are illustrative, not customer records or released customer requirements.

## Verification

The focused integration test seeds the dependency chain and this layer inside the isolated `ogami_test` transaction, then checks the three supported complaint states, D3 timing, weekday/holiday dates, NCR/8D links, CAPA action coverage, chronology, and deterministic replay. Final direct PHPUnit result: 1 test / 24 assertions, 9:04.3, 111 MiB reported memory. PHP lint, Pint, and targeted PHPStan pass. The test transaction rolls back; it does not populate the demo database or prove the complete dataset's performance target.
