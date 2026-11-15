# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: production-stock.floor.spec.ts >> PROD01-HAPPY — stock work order, Warehouse handover, startup QC, production, and outgoing release
- Location: e2e-process/production-stock.floor.spec.ts:284:1

# Error details

```
Error: Command failed: psql --no-psqlrc --quiet --tuples-only --no-align --set ON_ERROR_STOP=1 --command BEGIN TRANSACTION READ ONLY; SELECT COALESCE(json_agg(row_to_json(e2e_rows)), '[]'::json) FROM (SELECT json_agg(json_build_object(
      'item_id', item.id, 'code', item.code, 'name', item.name,
      'unit_of_measure', item.unit_of_measure, 'standard_cost', item.standard_cost::text,
      'safety_stock', item.safety_stock::text,
      'quantity_per_unit', bom_item.quantity_per_unit::text,
      'waste_factor', bom_item.waste_factor::text
    ) ORDER BY bom_item.sort_order) AS materials,
    (SELECT COALESCE((setting.value #>> '{}')::int, 5) FROM settings setting
      WHERE setting.key = 'quality.in_process.sample_size') AS sample_size,
    (SELECT finished.id FROM items finished
      JOIN products product ON product.part_number = finished.code
      WHERE product.part_number = 'WB-001' AND finished.item_type = 'finished_good'
      ORDER BY finished.id LIMIT 1) AS finished_item_id,
    (SELECT mold.setup_time_minutes FROM product_routings routing
      JOIN routing_operations operation ON operation.product_routing_id = routing.id
      JOIN molds mold ON mold.id = operation.mold_id
      JOIN products product ON product.id = routing.product_id
      WHERE product.part_number = 'WB-001' AND routing.is_active = true
      ORDER BY operation.sequence LIMIT 1) AS setup_minutes
    FROM products product
    JOIN bill_of_materials bom ON bom.product_id = product.id AND bom.is_active = true
    JOIN bom_items bom_item ON bom_item.bom_id = bom.id
    JOIN items item ON item.id = bom_item.item_id
    WHERE product.part_number = 'WB-001') AS e2e_rows; COMMIT;
ERROR:  column operation.product_routing_id does not exist
LINE 15:       JOIN routing_operations operation ON operation.product...
                                                    ^

```