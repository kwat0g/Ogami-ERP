# HR05-LOAN-UNDER-THRESHOLD — attempt 02: test expected an initial Department Head notice

**Result:** Employee submitted a ₱29,999.99 Company Loan through Self-service Loans. Read-only assertions confirmed `pending`, Department Head → HR → Finance pending, VP skipped at submission, no journal, the Employee row, and the separate observer detail. The Finance submission FYI reached the configured Finance accounts. The journey then failed because the test expected an `approval.action_required` notice to the Department Head at step 1; it found none.

- **Environment:** Fresh guarded E2E baseline, selected real-UI actor login, one headless Chromium worker, zero retries.
- **Source explanation:** `ApprovalService::NOTIFY_ON_SUBMIT` excludes workflow kind `loan`. `LoanService::request()` calls `notifyNextStep()` only when no eligible Department Head exists and that approval is skipped. In the normal path the pending request is found in the Department Head's Approval Queue.
- **Correction:** Assert no initial `approval.action_required` notice and require the Department Head to find the item in Approval Queue. Keep the Finance FYI recipient assertion.
- **Classification:** Test issue TST-HR-067, resolved in attempt 07. The queue hand-off is the supported initial UI route; no product finding is inferred.

Evidence: [sanitized trace, screenshots and journey context](assets/hr05-loan-under-threshold-attempt-02/). The next fresh-baseline reset removed this attempt's transaction.
