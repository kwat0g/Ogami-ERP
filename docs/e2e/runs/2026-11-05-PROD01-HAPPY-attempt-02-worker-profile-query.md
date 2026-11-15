# PROD01-HAPPY — Attempt 02: Eloquent accessor queried as a database column

**Date:** 2026-11-05 UTC  
**Outcome:** Test-harness failure during read-only fixture lookup, before a business step  
**Classification:** Test issue TST-PROD-002  
**Journey:** `PROD01-HAPPY`  
**Run mode:** Fresh guarded E2E baseline; headless Chromium tablet/touch emulation; one worker; no PHP test process detected during the 300-second quiet window.

## Result

The corrected `tablet-floor` project launched Chromium. The test failed 563 ms into its body while reading the seeded Production employee's profile for the manager's UI crew assignment. Its read-only query selected `employees.full_name`, but `full_name` is an Eloquent accessor, not a stored PostgreSQL column. PostgreSQL returned:

```text
ERROR: column employee.full_name does not exist
```

No process UI action or transactional record was created. The test body stopped before opening its actor contexts or creating its journey context. This run does not exercise a production transition and is not an application defect.

## Correction

The fixture query now reads the stored `employee_no`, `first_name`, `middle_name`, `last_name`, and `suffix` fields. The test composes the display name with the same rules as `Employee::getFullNameAttribute()` in `api/app/Modules/HR/Models/Employee.php`. No application code changed. Attempt 03 will start from a fresh E2E baseline; this failure remains archived.

## Evidence

- [Playwright error context](assets/prod01-happy-attempt-02/error-context.md)
- [Failure screenshot](assets/prod01-happy-attempt-02/test-failed-1.png)
- [Sanitized trace](assets/prod01-happy-attempt-02/trace.zip)
- Schema: `api/database/migrations/0016_create_employees_table.php`
- Accessor: `api/app/Modules/HR/Models/Employee.php`
- Test location: `spa/e2e-process/production-stock.floor.spec.ts`
