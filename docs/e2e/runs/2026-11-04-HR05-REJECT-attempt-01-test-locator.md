# HR05-REJECT — attempt 01: required-reason label locator

**Result:** The Employee submission completed through the UI. The Department Head found the pending request through the Approval Queue and opened its rejection dialog. The test then stopped before submitting a rejection because its exact label locator did not match the required marker rendered beside the label.

- **Journey run ID:** `89ffb9a1-9211-43f7-9a83-b98e0f096b78`
- **Execution:** Fresh isolated E2E baseline; three actor sessions authenticated through the real login UI; one headless Chromium worker; no retries.
- **Actors:** Employee, Department Head, HR Officer observer.
- **Steps:** Employee submitted a cash advance; read-only state and the HR observer's live pending record passed. The Department Head found it in the Approval Queue and opened **Reject loan request**. The test failed while locating the required reason field, before any rejection request was sent.
- **Classification:** Test issue `TST-HR-065`, resolved by using the accessible textbox role with a name pattern that accommodates the visible required marker; the required attribute and disabled/enabled confirmation behavior remain asserted.
- **Business result:** No rejection decision or approval-side effect was made. This locator failure is not evidence of a product defect.

Evidence, including the sanitized trace, screenshots, saved journey context, and Employee-step walkthrough: [attempt assets](assets/hr05-reject-attempt-01/).
