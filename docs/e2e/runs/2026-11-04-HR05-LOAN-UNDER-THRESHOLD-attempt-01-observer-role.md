# HR05-LOAN-UNDER-THRESHOLD — attempt 01: observer role could not reach Loans

**Result:** The selected actors authenticated through the real UI, then the journey stopped before the first transaction step. The test tried to open `/hr/loans` as the second Finance Officer, whose sidebar did not expose the Human Resources module. No loan was submitted and no product behavior was exercised.

- **Environment:** Guarded E2E stack, `ogami_e2e`, allowlisted `https://nginx`; one headless Chromium worker, zero retries.
- **Actors signed in:** Employee, Department Head, HR Officer, Finance Officer, second Finance Officer, Vice President.
- **Failure:** `openLoansObserver()` expected the second Finance Officer to expand the Human Resources section. The role had no such sidebar control.
- **Correction:** Use the Employee requester session as the non-approver real-time observer. That page remains open during Department Head, HR and Finance actions and verifies the Employee's pending-to-active update. Keep Finance notifications independently checked against both Finance accounts.
- **Classification:** Test issue TST-HR-066, resolved in attempt 07. No product finding.

Evidence: [sanitized trace, failure screenshots and context](assets/hr05-loan-under-threshold-attempt-01/).
