# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: production-stock.floor.spec.ts >> PROD01-HAPPY — stock work order, Warehouse handover, startup QC, production, and outgoing release
- Location: e2e-process/production-stock.floor.spec.ts:284:1

# Error details

```
Error: Command failed: psql --no-psqlrc --quiet --tuples-only --no-align --set ON_ERROR_STOP=1 --command BEGIN TRANSACTION READ ONLY; SELECT COALESCE(json_agg(row_to_json(e2e_rows)), '[]'::json) FROM (SELECT employee.employee_no, employee.full_name
    FROM employees employee JOIN users account ON account.employee_id = employee.id
    WHERE account.email = 'e2e+employee@ogami-e2e.test' AND employee.status = 'active') AS e2e_rows; COMMIT;
ERROR:  column employee.full_name does not exist
LINE 1: ...), '[]'::json) FROM (SELECT employee.employee_no, employee.f...
                                                             ^

```