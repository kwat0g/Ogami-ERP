# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: maintenance-breakdown.process.spec.ts >> MNT01-BREAKDOWN — corrective maintenance through assignment, repair log, and completion
- Location: e2e-process/maintenance-breakdown.process.spec.ts:211:1

# Error details

```
Error: Command failed: psql --no-psqlrc --quiet --tuples-only --no-align --set ON_ERROR_STOP=1 --command BEGIN TRANSACTION READ ONLY; SELECT COALESCE(json_agg(row_to_json(e2e_rows)), '[]'::json) FROM (SELECT notice.type, recipient.email,
           notice.data ->> 'title' AS title
    FROM notifications notice
    JOIN users recipient ON recipient.id = notice.notifiable_id
    WHERE notice.notifiable_type LIKE '%User'
      AND notice.data ->> 'entity_id' = (
        SELECT hash_id FROM maintenance_work_orders WHERE mwo_number = 'MWO-202611-0001'
      )
    ORDER BY notice.type, recipient.email) AS e2e_rows; COMMIT;
ERROR:  column "hash_id" does not exist
LINE 7:         SELECT hash_id FROM maintenance_work_orders WHERE mw...
                       ^

```