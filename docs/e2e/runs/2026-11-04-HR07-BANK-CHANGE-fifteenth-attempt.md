# HR07-BANK-CHANGE — fifteenth attempt

- **Date:** 2026-11-04
- **Journey run ID:** `286e1fba-6e84-4710-9073-46b495a0793c`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and a seeded `ogami_e2e` baseline; Redis transient stores were cleared by the guarded seed path
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** Three ordered journey steps passed. Employee submitted through the Profile UI; HR approved using HR's session; Finance found the hand-off in its actionable queue and approved using Finance's session. The Employee page showed Approved, the requested bank name, and the masked last four; HR and Finance rows showed Approved with their action buttons removed. Realtime hand-offs and step screenshots were captured.
- **Failure:** The final read-only snapshot passed checks for the approved request, HR and Finance reviewer identities, changed employee bank name and encrypted account value, at least four request audit entries, and zero request notifications. The test then failed because an aggregate query reported zero Employee bank-field audit rows. Audit chronology, Mailpit, badge-zero, and final diagnostics assertions after that point did not run.
- **Investigation:** A post-run read-only query found the dedicated database back at its seeded baseline (zero profile-change requests and 527 audit rows), despite the in-journey read-only snapshot having found the approved record. The reason for that post-run state change is not established. This attempt does not count as a complete journey.
- **Correction in progress:** Replace the aggregate count with a direct Employee audit-row query by the seeded employee ID and journey start time. The query now checks the Finance actor and redacted bank-field keys across both old/new audit JSON. This remains unverified until the next clean-baseline run.
- **Evidence:** [archived screenshots, trace, error context, and journey context](runs/assets/hr07-bank-change-fifteenth-attempt/); `spa/e2e-process/hr-profile.process.spec.ts`; read-only E2E database counts after the failed run.
- **State:** UI approvals completed. Final audit, mail, and badge invariants are unverified; reseed before the next attempt.
