# HR05-LOAN-UNDER-THRESHOLD — attempt 05: initial notice assertion stopped the journey

**Result:** Employee submitted the Company Loan through the UI. The loan and threshold-branch assertions passed: status `pending`, three pending approvals, VP `skipped`, no journal, and the Finance submission FYI. The test failed because it expected an initial Department Head `approval.action_required` notification, which the normal eligible-Department-Head path does not send. No approval action ran in this attempt.

- **Environment:** Fresh guarded E2E baseline and real-UI logins; one headless Chromium worker, zero retries.
- **Correction:** TST-HR-067 updated the initial state expectation. A second assertion mismatch in the later notification matrix was corrected in attempt 07: after Department Head approval, the notice is for next step 2 (HR), not step 1 (Department Head).
- **Classification:** Test expectation issue only; the Department Head's Approval Queue is the initial hand-off.

Evidence: [sanitized trace, screenshots and context](assets/hr05-loan-under-threshold-attempt-05/).
