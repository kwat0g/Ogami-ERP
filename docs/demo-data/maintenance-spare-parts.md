# Maintenance spare-part catalog

The `maintenance-spare-parts` layer completes the demo spare-part master catalog. The inventory reference layer already supplies six `SP-001`–`SP-006` examples; this layer adds sequential `SP-007` onward so that all scales share the same part identities.

## Scale and records

| Scale | Existing spare items | Added by this layer | Total spare items |
|---|---:|---:|---:|
| small | 6 | 14 | 20 |
| demo | 6 | 94 | 100 |
| full | 6 | 134 | 140 |

New catalog families cover barrel heater bands, thermocouple probes, hydraulic filters, injection nozzle tips, press safety/proximity sensors, mold ejector pins, hydraulic solenoid components, mold seals, mold cooling couplings, and auxiliary-equipment service parts. Family variants are spread round-robin so small mode includes multiple equipment classes. Every item uses the active Spare Parts category, `spare_part` classification, the current `PCS` unit, a fixed-quantity reorder method, and synthetic standard cost, reorder point, safety stock, minimum order quantity, and lead time. These values are illustrative catalog estimates, not approved vendor quotations or maintenance standards.

The layer depends on the Inventory catalog, production assets, and workforce identities. It creates or restores records through `ItemService`, runs at `--as-of`, and attributes audit records to the seeded warehouse staff actor. It fails closed if an `SP-007`–`SP-140` code is occupied by data that does not match the synthetic demo catalog; it does not overwrite changed master data. It does not create stock balances, movements, or maintenance part usage. Those belong to the opening-balance and transactional layers.

## Application boundary

The current inventory item model has no machine/mold compatibility relation, and maintenance only associates a part with a work order when usage is recorded. The item's description carries an intended equipment class as readable guidance; that text does not enforce fitment. The coverage plan's proposed equipment links are therefore not represented as foreign keys or compatibility rules. See [F-024](FINDINGS.md#discovery-findings). A future application change is required to demonstrate enforced part-to-asset compatibility.

## Verification

`MaintenanceSparePartsCatalogLayerTest` passes (1 test / 13 assertions), checking the small scale, 20 / 100 / 140 scale plans, category and item fields, controlled timestamps, warehouse actor attribution, and a no-drift rerun. The guarded small console smoke passed on the exact allowlisted `ogami_test` database after the five required system/reference seeders were explicitly run. All six selected/dependency layers completed in 46.4 seconds at 62.5 MiB process peak; this layer took 1.71 seconds. Read-only SQL confirmed 20 spare items (`SP-001`–`SP-020`), 14 actor-attributed create audit rows, all item/audit timestamps on `2026-11-15`, and no stock levels. A same-options console rerun left record and audit fingerprints unchanged. PHP lint, Pint, and targeted PHPStan pass. Demo/full scale database writes are not implied by the planned counts.
