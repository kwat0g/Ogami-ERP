# Supplier listing demo data

The `supplier-listings` layer covers internal supplier-offer review and source qualification in Purchasing. It depends on `business-partners` and `workforce-identities`; selecting it also seeds the current raw-material, packaging, and spare-part catalogs and the internal reviewer account.

## Records and process states

The layer maps resin and colorant items to Inabata Philippines Inc.; metal inserts to Philippine Nakamura and Aikawa Philippines Inc.; and packaging to Aikawa Philippines Inc., Junlec Printing Supply, Tanigawa Seisakusho, and Dyna Pack & Malinta. It creates approved current listings for the mapped sources, a revised Inabata offer on RM-001 (the earlier approved revision becomes superseded), a rejected Inabata quote for RM-003, and a live pending Inabata quote for RM-005. Every state in the current `SupplierListingStatus` enum is represented.

| Scale | Mapped approved offers | Revision history | Rejected | Live pending | Total listings | Approved source rows |
|---|---:|---:|---:|---:|---:|---:|
| small | 22 | 1 superseded | 1 | 1 | 25 | 22 |
| demo | 37 | 1 superseded | 1 | 1 | 40 | 37 |
| full | 37 | 1 superseded | 1 | 1 | 40 | 37 |

The current catalog has 9 resins, 3 colorants, 3 metal inserts, 4 packaging items, 3 process consumables, and 6 spare parts. Supplier listings cover 22 material/packaging items: resin, colorant and process-consumable sourcing goes to Inabata; inserts go to Philippine Nakamura and Aikawa; packaging is offered by Aikawa, Junlec, Tanigawa and Dyna Pack. Maintenance spares remain unqualified because none of the six supplier descriptions identify a spare-parts business. The demo/full maps include all six named suppliers.

## Domain behavior

Submissions, approvals, and rejections all call `SupplierListingService`. The service creates the listing, catalog-change outbox events, and normal review notifications; approval supersedes an earlier approved offer and synchronizes the `ApprovedSupplier` row with its price per base unit. No transactional listing, approved-source, audit, outbox, or notification rows are inserted directly.

All historical submissions use controlled timestamps and suppress in-app notifications while preserving outbox events. The RM-005 pending offer is submitted at the as-of date's prior business day with ordinary notifications enabled; the exact current Purchasing approval audience receives the inbox notice. It remains unreviewed for the live demo. For a Saturday/holiday as-of date, its submitted date is moved backward to the preceding plant business day. Review dates are moved forward to the next non-weekend, non-holiday day.

The seeded source actor is the current internal Purchasing Officer. Supplier portal user provisioning is not part of this layer, so this unit does not claim that a supplier can log in and manage its offer. The B2B portal listings page remains dependent on the later portal-identity and P2P scenario layers.

## Synthetic quote assumptions

Offer price is calculated from the demo item's standard cost, an order-unit conversion, and a fixed supplier factor. For example, RM-001 has a demo standard cost of PHP 120.00/kg, the quoted bag is 25 kg, and the Inabata factor is 1.03; the illustrative offer is PHP 3,090.00/bag, which the application stores as PHP 123.60/kg in `ApprovedSupplier.last_price`. Other mapped vendors use fixed factors in the layer source. Packaging and metal-insert conversions follow the layer's explicit item/category mapping.

These prices and pack sizes are generated demo assumptions, not supplier quotations or market benchmarks. See [F-021](FINDINGS.md#discovery-findings) and the current item/BOM cost caveats in [F-018](FINDINGS.md#discovery-findings).

## Verification

The focused feature test passed (1 test / 39 assertions) and checks all four enum values, scale-small volume, reviewer identity, approval/rejection timestamps, price-per-base-unit synchronization, current inbox audience, outbox history, controlled timestamps, and byte-equivalent reruns of the owned listing/source/outbox/notice rows. The guarded console smoke used the exact allowlisted `ogami_test` database and confirmed 31 listing rows, 28 approved-source rows, 61 catalog-change events, and three reviewer notices for the pending row; see [PROGRESS.md](PROGRESS.md). The layer itself validates the expected status counts, approved-source links and prices, required revision/rejection rows, and the live pending notification before returning.
