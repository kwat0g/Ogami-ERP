# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: maintenance-breakdown.process.spec.ts >> MNT01-BREAKDOWN — corrective maintenance through assignment, repair log, and completion
- Location: e2e-process/maintenance-breakdown.process.spec.ts:211:1

# Error details

```
Error: Command failed: psql --no-psqlrc --quiet --tuples-only --no-align --set ON_ERROR_STOP=1 --command BEGIN TRANSACTION READ ONLY; SELECT COALESCE(json_agg(row_to_json(e2e_rows)), '[]'::json) FROM (SELECT wo.id, wo.mwo_number, wo.status, wo.type, wo.priority,
           wo.verification_required, creator.email AS created_by_email,
           technician.email AS assigned_email, machine.machine_code,
           machine.status AS machine_status, wo.started_at, wo.completed_at,
           wo.downtime_minutes
    FROM maintenance_work_orders wo
    JOIN users creator ON creator.id = wo.created_by
    LEFT JOIN employees employee ON employee.id = wo.assigned_to
    LEFT JOIN users technician ON technician.id = employee.user_id
    JOIN machines machine ON wo.maintainable_type = 'machine' AND machine.id = wo.maintainable_id
    WHERE wo.mwo_number = 'MWO-202611-0001') AS e2e_rows; COMMIT;
ERROR:  column employee.user_id does not exist
LINE 9:     LEFT JOIN users technician ON technician.id = employee.u...
                                                          ^

```