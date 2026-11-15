# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: maintenance-breakdown.process.spec.ts >> MNT01-BREAKDOWN — corrective maintenance through assignment, repair log, and completion
- Location: e2e-process/maintenance-breakdown.process.spec.ts:209:1

# Error details

```
Error: Command failed: psql --no-psqlrc --quiet --tuples-only --no-align --set ON_ERROR_STOP=1 --command BEGIN TRANSACTION READ ONLY; SELECT COALESCE(json_agg(row_to_json(e2e_rows)), '[]'::json) FROM (SELECT audit.new_values ->> 'status' AS status, actor.email
    FROM audit_logs audit
    JOIN users actor ON actor.id = audit.user_id
    WHERE audit.model_type ILIKE '%MaintenanceWorkOrder'
      AND audit.model_id = (SELECT id FROM maintenance_work_orders WHERE mwo_number = 'MWO-202611-0001')
      AND audit.action = 'updated'
      AND audit.new_values ? 'status'
    ORDER BY audit.id) AS e2e_rows; COMMIT;
ERROR:  operator does not exist: json ? unknown
LINE 7:       AND audit.new_values ? 'status'
                                   ^
HINT:  No operator matches the given name and argument types. You might need to add explicit type casts.

```