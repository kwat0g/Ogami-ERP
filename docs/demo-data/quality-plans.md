# Incoming quality-plan master data

The `quality-plans` layer publishes baseline incoming-inspection plans for the active inventory item types selected by the application's `quality.rollout.eligible_item_types` setting. In the current system baseline, that setting contains `raw_material`.

The layer reads its sample size from `quality.rollout.fixed_sample_size` and its inspection checks from the application-owned `quality.rollout.template.resin` and `quality.rollout.template.general` settings. The current baseline sample size is 3. Items in the `Resins` category use the resin template; other eligible categories use the general template. Those templates and their acceptance criteria come from migrations `0330_seed_quality_rollout_templates.php` and `2026_10_22_120000_complete_quality_rollout_template_criteria.php`; this layer adds no measurement limits or checks of its own.

Every plan is created as an active, vendor-independent, `incoming` plan with fixed sampling and an effective start equal to `--as-of`. The layer calls `ItemQualityPlanService::createRevision()` as an active user selected by `system.automation.actor_roles`, while `DemoSeedContext` supplies the actor context and controlled time. Plan definitions are immutable in the application, so a later change must be published as a new revision through the quality workflow.

An existing plan effective on the requested as-of date is preserved and registered for later layers. An active future default revision that would make a new as-of revision a backdate stops the layer with an item-specific error. Supplier-specific plans are not generated.

## Small-scale verification

The guarded small-mode smoke used the current baseline seeders, the exact `ogami_test` database allowlist, and `--as-of=2026-11-14`. It created 18 version-1 active plans, all effective on that date, with the configured fixed sample size of 3:

| Inventory category | Plans |
|---|---:|
| Resins | 9 |
| Colorants | 3 |
| Metal Inserts | 3 |
| Process Consumables | 3 |
| **Total** | **18** |

`QualityPlanLayerTest` exercises the registered layer through `DemoSeedRunner`, checks each plan against the configured template and automation actor, verifies the matching `created` audit row uses the same actor and controlled date, and proves that a second run creates no plan or audit drift. Together with the existing `ItemQualityPlanTest`, the focused suite passes 8 tests / 342 assertions. The console reported the quality layer at 1.0 second and 60.5 MiB peak process memory for the completed dependency run; dependency layers already existed from the earlier guarded attempts in that test database.

## Coverage boundary

This is quality master data only. It does not create receipts, inspections, failed lots, NCRs, CAPAs, supplier-specific controls, outgoing checks, or customer-specific inspection specifications. Those belong to later transactional layers and must follow the current services and workflow states. The finished-product catalog explicitly labels its dimensions as illustrative and says they are not released customer drawings, so this layer does not convert those examples into acceptance limits. Quality-plan coverage can therefore support incoming-QC demos for the configured raw materials, while released product/customer specifications remain an open master-data gap.
