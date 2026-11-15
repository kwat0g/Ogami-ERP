# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-profile.process.spec.ts >> HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval
- Location: e2e-process/hr-profile.process.spec.ts:178:1

# Error details

```
Error: Command failed: psql --no-psqlrc --quiet --tuples-only --no-align --set ON_ERROR_STOP=1 --command BEGIN TRANSACTION READ ONLY; SELECT COALESCE(json_agg(row_to_json(e2e_rows)), '[]'::json) FROM (SELECT request.id AS request_id, request.hash_id AS request_hash_id,
           request.status, request.requires_finance, request.employee_id,
           requester.email AS requester_email,
           employee.bank_name AS employee_bank_name,
           employee.bank_account_no AS employee_bank_account_cipher,
           hr.email AS hr_reviewer_email,
           finance.email AS finance_reviewer_email,
           (SELECT COUNT(*)::int FROM audit_logs audit
             WHERE audit.model_type ILIKE '%ProfileUpdateRequest'
               AND audit.model_id = request.id) AS request_audit_count,
           (SELECT COUNT(*)::int FROM audit_logs audit
             WHERE audit.model_type ILIKE '%Employee'
               AND audit.model_id = employee.id
               AND audit.new_values->>'bank_name' = '') AS employee_bank_audit_count,
           (SELECT COUNT(*)::int FROM notifications notice
             WHERE notice.data->>'subject_type' ILIKE '%ProfileUpdateRequest'
               AND notice.data->>'subject_id' = request.id::text) AS notification_count
      FROM profile_update_requests request
      JOIN employees employee ON employee.id = request.employee_id
      JOIN users requester ON requester.id = request.requested_by
      LEFT JOIN users hr ON hr.id = request.reviewed_by
      LEFT JOIN users finance ON finance.id = request.finance_reviewed_by
     WHERE request.hash_id = 'non-existent-e2e-profile-request') AS e2e_rows; COMMIT;
ERROR:  column request.hash_id does not exist
LINE 1: ...[]'::json) FROM (SELECT request.id AS request_id, request.ha...
                                                             ^

```