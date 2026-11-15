# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: production-stock.floor.spec.ts >> PROD01-HAPPY — stock work order, Warehouse handover, startup QC, production, and outgoing release
- Location: e2e-process/production-stock.floor.spec.ts:284:1

# Error details

```
Error: Command failed: psql --no-psqlrc --quiet --tuples-only --no-align --set ON_ERROR_STOP=1 --command BEGIN TRANSACTION READ ONLY; SELECT COALESCE(json_agg(row_to_json(e2e_rows)), '[]'::json) FROM (SELECT (SELECT COUNT(*)::int FROM work_order_materials material WHERE material.work_order_id = 1) AS lines,
               (SELECT COUNT(*)::int FROM purchase_requests request WHERE request.work_order_id = 1) AS shortage_prs) AS e2e_rows; COMMIT;
ERROR:  column request.work_order_id does not exist
LINE 2: ...OUNT(*)::int FROM purchase_requests request WHERE request.wo...
                                                             ^

```