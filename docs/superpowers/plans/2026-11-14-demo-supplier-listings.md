# Demo Supplier Listings Implementation Plan

> **For agentic workers:** Implement the approved purchasing coverage from `docs/demo-data/01-data-plan.md` with Laravel domain services. Keep this unit focused on supplier item listing submissions, reviews, approval links and the live pending inbox scenario.

**Goal:** Seed realistic, deterministic supplier offers for the current demo vendor/item catalogs, with all current supplier-listing states represented and approved offers usable by sourcing.

**Architecture:** Depend on `business-partners` and `workforce-identities`. Use `SupplierListingService::submit`, `approve`, and `reject` under `DemoSeedContext` clocks and the Purchasing Officer role actor. Historical submit notifications are suppressed; the current pending scenario uses normal in-app notification delivery. No listing, approved-supplier, audit, or outbox rows are inserted directly.

**Source rules:** Vendor names, item/category codes, role keys, status enum, permissions and quote fields come from current app code. Quote amounts are synthetic estimates derived from the already documented demo item standard costs using fixed vendor factors; no real supplier quote or statutory amount is implied.

**Verification:** Focused feature test must prove status coverage, correct reviewer links, current inbox notices, approved-supplier synchronization, base-unit price conversion, controlled dates, stable event data and rerun idempotence. Console smoke uses only the exact allowlisted `ogami_test` database.

## Task 1: Supplier listing layer

- [x] Write a focused integration test first; confirm it fails before the layer is registered.
- [x] Add category-aware supplier/item source mappings derived from current demo references.
- [x] Create historical approved offers, one revised offer that supersedes its predecessor, one rejected offer, and one current pending offer.
- [x] Use current domain services under controlled clocks and the seeded authorized Purchasing Officer identity.
- [x] Keep historical notifications suppressed and retain normal notifications for the live pending row.
- [x] Verify all listing states, `approved_suppliers`, price-per-base-unit, timestamps, notification audience and deterministic idempotency.
- [x] Run targeted tests, PHP lint and Pint; record the synthetic price basis and unit results in the docs.
- [x] Commit and open a stacked draft PR on the workforce identity branch (#530).
