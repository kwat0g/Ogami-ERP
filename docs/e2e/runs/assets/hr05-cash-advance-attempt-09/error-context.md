# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-loans.process.spec.ts >> HR05-CASH-ADVANCE — Employee request advances through Department Head, Finance, and VP before disbursement
- Location: e2e-process/hr-loans.process.spec.ts:235:1

# Error details

```
Error: Command failed: psql --no-psqlrc --quiet --tuples-only --no-align --set ON_ERROR_STOP=1 --command BEGIN TRANSACTION READ ONLY; SELECT COALESCE(json_agg(row_to_json(e2e_rows)), '[]'::json) FROM (SELECT entry.entry_number, entry.status, creator.email AS created_by_email,
           poster.email AS posted_by_email,
           entry.total_debit::text AS total_debit, entry.total_credit::text AS total_credit,
           COALESCE(SUM(line.debit), 0)::text AS line_debit,
           COALESCE(SUM(line.credit), 0)::text AS line_credit
      FROM journal_entries entry
      LEFT JOIN users creator ON creator.id = entry.created_by
      LEFT JOIN users poster ON poster.id = entry.posted_by
      LEFT JOIN journal_entry_lines line ON line.journal_entry_id = entry.id
     WHERE entry.reference_type = 'loan_disbursement'
       AND entry.reference_id = 1
       AND entry.deleted_at IS NULL
     GROUP BY entry.id, creator.email) AS e2e_rows; COMMIT;
ERROR:  column "poster.email" must appear in the GROUP BY clause or be used in an aggregate function
LINE 2:            poster.email AS posted_by_email,
                   ^

```