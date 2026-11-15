# HR07-REJECT-RESUBMIT — full run

- **Date:** 2026-11-04 UTC
- **Journey run ID:** `c7a99eec-6c07-44ac-b853-1f2fdcb2f85f`
- **Environment:** guarded `ogami-erp-e2e` Compose project; freshly seeded `ogami_e2e`; Redis queue/cache cleared by the guarded baseline command
- **Command:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-REJECT-RESUBMIT`
- **Playwright result:** 2 passed / 0 failed: three selected actors signed in through the real login UI and one Chromium journey. Journey duration: 36.9 seconds; total command: 50.4 seconds.
- **Process result:** the Employee created two separate requests through Profile. HR rejected the first; the Employee observed Rejected and submitted a replacement. HR approved the replacement and Finance independently approved it. The first request stayed rejected; Finance approval of the replacement changed the Employee's bank details.
- **Artifacts:** [journey context and annotated walkthrough screenshots](assets/hr07-reject-resubmit-full-run/).

## Executed steps

| # | Actor/session | UI path and action | Result and checks |
|---|---|---|---|
| 1 | Employee, HR Officer, Finance Officer in separate browser contexts | Employee → Profile; Human Resources → Profile Change Requests; Payroll → Bank Account Changes | Each role opened its page through the authenticated shell. The employee's bank account and request history were empty at the fresh baseline. |
| 2 | Employee | Profile → Bank account → Request update → Submit request | First request created as `pending` through the UI. HR found it through its actionable badge and saw HR Approve; Finance had no action. The employee bank fields were unchanged. |
| 3 | HR Officer | Profile Change Requests → Awaiting my action → expand request → Reject | HR rejected the request through its own session. HR's row became Rejected and lost both review actions. Employee's open profile showed Rejected without refresh and Request update became available. |
| 4 | Employee | Profile → Bank account → Request update → Submit request | Employee created a second request through the UI with a distinct public identifier. The request history showed one Rejected and one Pending HR. HR found the new work from its badge; Finance still had no actionable request. |
| 5 | HR Officer | Profile Change Requests → Awaiting my action → HR Approve; Finance follows Bank Account Changes badge | HR approved the replacement through its own session. The replacement became `pending_finance`; the first request remained Rejected. Employee saw Awaiting Finance live, and Finance found the replacement via its actionable queue. |
| 6 | Finance Officer | Bank Account Changes → Awaiting my action → expand replacement → Finance Approve | Finance approved through its own session. Employee saw Approved, the new bank name, and a masked account ending `6284`; the full account number was absent. The first request remained Rejected and all approval actions disappeared. |

### Walkthrough screenshots

**Step 1 — role-owned views opened**

![Employee opens Profile and reviewers open their queues](assets/hr07-reject-resubmit-full-run/walkthrough/001-employee-hr-and-finance-open-their-own-profile-and-review-queues.png)

**Step 2 — initial request and HR hand-off**

![Employee submits the initial bank change](assets/hr07-reject-resubmit-full-run/walkthrough/002-employee-submits-the-first-bank-change-and-hr-receives-it.png)

![HR opens the initial request from its badge](assets/hr07-reject-resubmit-full-run/walkthrough/002-hr-opens-the-first-request-from-its-actionable-badge.png)

**Step 3 — HR rejection reaches Employee**

![HR rejects the initial request](assets/hr07-reject-resubmit-full-run/walkthrough/003-hr-rejects-the-first-request-and-employee-sees-the-terminal-rejection.png)

![Employee sees the rejection and can resubmit](assets/hr07-reject-resubmit-full-run/walkthrough/003-employee-sees-rejection-and-can-request-a-replacement.png)

**Step 4 — Employee creates a replacement**

![Employee submits a new bank change after rejection](assets/hr07-reject-resubmit-full-run/walkthrough/004-employee-submits-a-new-bank-change-after-rejection.png)

![HR locates the replacement request](assets/hr07-reject-resubmit-full-run/walkthrough/004-hr-locates-the-replacement-request-in-its-actionable-queue.png)

**Step 5 — HR approval and Finance hand-off**

![HR approves the replacement and Finance receives the hand-off](assets/hr07-reject-resubmit-full-run/walkthrough/005-hr-approves-the-replacement-request-and-finance-finds-the-hand-off.png)

![Employee sees that Finance review is pending](assets/hr07-reject-resubmit-full-run/walkthrough/005-employee-sees-finance-review-for-the-replacement.png)

**Step 6 — Finance approval and final Employee view**

![Finance approves the replacement](assets/hr07-reject-resubmit-full-run/walkthrough/006-finance-approves-the-replacement-and-employee-receives-the-new-account.png)

![Employee sees the approved replacement with a masked account](assets/hr07-reject-resubmit-full-run/walkthrough/006-employee-sees-the-approved-replacement-with-masked-account-number.png)

## Read-only final verification

- The original request remains `rejected`, with the seeded HR Officer as its reviewer and no Finance reviewer. It has two request audit rows: Employee creation and HR rejection.
- The replacement remains `approved`, requires Finance, and records the HR Officer and a distinct Finance Officer as reviewers. It has four request audit rows and zero request notification rows.
- The original and replacement each have zero request notification rows. Mailpit message IDs for Employee, HR, and Finance did not change during the run.
- Audit entries attribute both creations to Employee, initial rejection and replacement HR approval to HR, and final Finance approval to Finance. A separate Employee `updated` audit row attributed to Finance contains both sensitive bank field keys; the values are redacted.
- The employee bank name equals the replacement value and the encrypted account ciphertext is present. The UI showed only the masked account. The original rejected values were never applied.
- The HR and Finance actionable badge counts returned to zero. Employee, HR, and Finance browser diagnostics remained clean. Open Employee, HR, and Finance views reflected status changes without refresh.
- No inventory or financial ledger entries are expected for this profile-change process.
- The corrected row-specific locator resolved TST-HR-013. No application code was changed.

## Process diagram

```mermaid
sequenceDiagram
    actor EMP as Employee
    actor HR as HR Officer
    actor FIN as Finance Officer
    participant ERP as OGAMI ERP
    EMP->>ERP: Submit first bank change in Profile
    ERP-->>HR: Pending HR item appears in queue and badge
    HR->>ERP: Reject first request
    ERP-->>EMP: Rejected appears in open Profile
    EMP->>ERP: Submit a distinct replacement request
    ERP-->>HR: Replacement appears in actionable queue
    HR->>ERP: Approve replacement
    ERP-->>EMP: Awaiting Finance appears live
    ERP-->>FIN: Replacement appears in Finance action queue
    FIN->>ERP: Approve replacement
    ERP-->>EMP: Apply replacement bank fields; show masked account
    ERP-->>HR: Replacement approved; action removed
    ERP-->>FIN: Replacement approved; action removed
```

## Coverage limits

This run exercised `pending → rejected` at HR, a new `pending` request after rejection, and the replacement's `pending → pending_finance → approved` path. Finance rejection from `pending_finance`, direct API denials, self-review, stale actions, and concurrent approvers remain planned Phase 4 checks. The earlier failed attempt is retained separately as [TST-HR-013 evidence](2026-11-04-HR07-REJECT-RESUBMIT-first-attempt.md); it does not count as a completed business run.
