# HR07-BANK-CHANGE — fourteenth attempt

- **Date:** 2026-11-04
- **Journey run ID:** `286e1fba-6e84-4710-9073-46b495a0793c`
- **Environment:** guarded `ogami-erp-e2e` stack; fresh baseline created after transient Redis reset
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** UI sign-in passed (3/3). Employee submitted through Profile; HR approved from its queue; Finance found and approved from its queue; Employee saw Approved and the masked updated account; both reviewer rows showed Approved without actions. Read-only verification passed for request status, both reviewer fields, changed employee account, request audit count, and zero notification rows. The Employee audit assertion then failed because it searched audit JSON for the plaintext bank name and got 0 matching rows.
- **Finding:** Read-only inspection found an Employee `updated` audit row by `e2e+finance_officer@ogami-e2e.test`; its fields include `bank_name` and `bank_account_no`, with both sensitive values redacted as `***`. This is intended audit redaction, not missing audit activity.
- **Correction:** Verify the presence of both redacted sensitive-field keys and the Finance audit actor, rather than compare the audit values to plaintext. The employee record itself is still verified against the requested bank name and changed ciphertext.
- **Evidence:** [archived screenshots, trace, error context, and journey context](runs/assets/hr07-bank-change-fourteenth-attempt/); a read-only query in the dedicated E2E database showed the Finance actor and redacted field keys; `api/app/Common/Traits/HasAuditLog.php`.
- **State:** Finance completed the UI transaction, but read-only audit verification failed in the test assertion. Reseed before rerunning.
