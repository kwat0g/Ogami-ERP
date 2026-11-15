# Product inspection specifications and NCR templates

The `quality-inspection-specs` layer supplies the current product inspection-spec page and NCR template catalog. It depends on the finished-product catalog and workforce identities. Every service action is attributed to the seeded QC Inspector, runs at `--as-of`, and uses the current `InspectionSpecService` or `NcrTemplateService` lifecycle.

## Scale and records

| Scale | Product specs | Immutable spec revisions | Spec checks | NCR templates |
|---|---:|---:|---:|---:|
| small | 16 | 16 | 80 | 8 |
| demo | 16 | 16 | 80 | 12 |
| full | 16 | 16 | 80 | 16 |

The finished-product catalog has 16 fixed demo parts at every scale. Each receives overall length, width, and height checks plus appearance and fit/retention checks. Dimensional nominal values, units, and the illustrative tolerance are read from the current product `specifications` field; the seeder fails if that source is missing or malformed. Every acceptance criterion clearly says that the limit is synthetic and requires an approved drawing before production use. The test also runs with global checklist capture enabled so each published definition satisfies the app's current instruction requirement.

These are navigation and workflow examples, not released customer specifications. The product catalog itself labels its dimensions and ±0.20 mm tolerance as illustrative; do not use these values for actual production, PPAP, capability conclusions, or customer acceptance. The 16 product codes follow `FinishedProductCatalogLayer` and `ProductionAssetsLayer`.

## NCR template catalog

Template sources use the current `NcrSource` enum (`inspection_fail`, `customer_complaint`) and severities use `NcrSeverity` (`low`, `medium`, `high`, `critical`). Templates include generic and product-specific examples. One inactive obsolete-revision/label template is retained so active filtering has both states. All names are prefixed `DEMO —`; descriptions and notes explicitly state that these are not actual complaints, defects, supplier events, or customer notifications.

The app's 11 injection-molding defect codes are system reference data from `DefectTypeSeeder`; this layer does not duplicate those rows. It creates no inspections, NCRs, CAPAs, PPAP records, customer complaint events, notifications, or customer-facing messages. Those transactional histories remain later Phase 4 work.

## Idempotency and verification

Inspection specs are immutable and revisioned. Before calling `upsertForProduct`, the layer compares the active root, current revision, and all current check fields with the planned definition; an identical rerun does not add a version or audit rows. NCR templates use stable demo names and are updated through the application service only when their planned fields differ.

`QualityInspectionReferencesLayerTest` checks all catalog counts and enums, product links, QC audit attribution, controlled as-of timestamps, checklist-capture compatibility, and a no-drift rerun. The focused layer test passed (1 test / 172 assertions). Inspection spec and NCR service regressions passed (18 tests / 128 assertions). The guarded console smoke and demo/full scale console writes remain to be completed for this unit.
