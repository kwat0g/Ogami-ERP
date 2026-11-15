# HR07-BANK-CHANGE — full run

- **Date:** 2026-11-04 UTC
- **Journey run ID:** `d00445e9-93ad-4ed9-8897-12ab2d9218d0`
- **Environment:** guarded `ogami-erp-e2e` Compose project; freshly reseeded `ogami_e2e`; Redis queue/cache cleared by the guarded baseline command
- **Command:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Playwright result:** 2 passed / 0 failed (three selected actors signed in through the real login UI; one Chromium journey). Journey duration: 28.9 seconds; total test command: 42.4 seconds.
- **Process result:** Employee → HR → Finance independent approval completed through three role-owned browser contexts. The UI showed `pending → pending_finance → approved`; Finance approval applied the employee's requested bank change.
- **Artifacts:** [archived journey context and annotated screenshots](assets/hr07-bank-change-seventeenth-full-run/). No failure trace was produced on this passing run.

## Executed steps

| # | Actor/session | UI route and action | Result and checks |
|---|---|---|---|
| 1 | Employee, HR, Finance (separate sessions) | Employee Home → Profile; Human Resources → Profile Change Requests; Payroll → Bank Account Changes | All three views opened through the authenticated shell/sidebar. Baseline had no open bank-change request. |
| 2 | Employee | Self-service → My profile → Bank account → Request update → Submit request | UI created the request as `pending`. HR's already-open queue and actionable badge updated; employee account remained unchanged; Finance had no pre-HR action. Read-only DB check confirmed no reviewer fields and zero request notifications. |
| 3 | HR Officer | Human Resources → Profile Change Requests → Awaiting my action → expand request → HR Approve | HR approved through the HR session. Status moved to `pending_finance`; employee and HR screens updated without refresh; HR's action disappeared; Finance found the hand-off through its badge queue and saw only Finance Approve. Read-only DB check confirmed HR as reviewer and unchanged employee account. |
| 4 | Finance Officer | Payroll → Bank Account Changes → Awaiting my action → expand request → Finance Approve | Finance approved through the Finance session. Employee saw Approved, the new bank name, and masked account ending `7426`; the full account was absent. HR and Finance rows showed Approved with approval actions removed; both actionable badges returned to zero. |

### Walkthrough screenshots

**Step 1 — all role views opened**

![Employee, HR and Finance open their own profile and review queues](assets/hr07-bank-change-seventeenth-full-run/walkthrough/001-employee-hr-and-finance-open-their-own-profile-and-review-queues.png)

**Step 2 — Employee submits and HR receives the hand-off**

![Employee submits a bank account change and HR receives the actionable queue item](assets/hr07-bank-change-seventeenth-full-run/walkthrough/002-employee-submits-a-bank-account-change-and-hr-receives-the-actionable-queue-item.png)

**Step 3 — HR approves and Finance receives its work**

![HR approves the first leg and Finance receives its queue item](assets/hr07-bank-change-seventeenth-full-run/walkthrough/003-hr-approves-the-first-leg-and-finance-receives-its-queue-item.png)

**Step 4 — Finance approves and the employee profile updates**

![Finance approves and the employee account is updated](assets/hr07-bank-change-seventeenth-full-run/walkthrough/004-finance-approves-and-the-employee-account-is-updated.png)

## Read-only verification

- Request status is `approved`; the requester is the seeded Employee; `reviewed_by` is the HR Officer and `finance_reviewed_by` is the distinct Finance Officer.
- Employee bank name equals the submitted run-specific value; the encrypted account value changed from baseline. The rendered account remains masked.
- Request audit history contains creation, HR `pending_finance`, and Finance `approved` events with the expected actors; at least four request audit entries exist.
- Finance has one Employee `updated` audit row carrying both `bank_name` and `bank_account_no` keys. Sensitive audit values are redacted; the source employee row independently confirms the applied values.
- The request has zero notification rows, and Mailpit captured no new messages for Employee, HR, or Finance. The HR and Finance actionable badge counts are zero after completion.
- Each role's browser diagnostics remained clean. The HR and Finance pages were left open across hand-offs; status changes appeared without a refresh.
- No inventory or financial ledger effects are expected for this profile-only process.
- An independent read-only snapshot after Playwright exited still found the approved request persisted, `requires_finance=true`, the expected HR and Finance reviewers, four request audit rows, one Finance-authored Employee bank-field audit, and zero request notifications. This confirms the final state remained present after the browser run.

## Process diagram

```mermaid
sequenceDiagram
    actor EMP as Employee
    actor HR as HR Officer
    actor FIN as Finance Officer
    participant ERP as OGAMI ERP
    EMP->>ERP: Submit bank change in My Profile
    ERP-->>EMP: Request pending; existing account unchanged
    ERP-->>HR: Pending request appears in HR queue/badge
    HR->>ERP: Approve HR review
    ERP-->>EMP: Awaiting Finance
    ERP-->>FIN: Finance action appears in Bank Account Changes
    FIN->>ERP: Approve Finance review
    ERP-->>EMP: Approved; apply bank name/account
    ERP-->>HR: Approved; HR action removed
    ERP-->>FIN: Approved; Finance action removed
```

## Findings and limits

- The journey completed without a process blocker. `UX-HR-001` remains open: the actionable badge's “Awaiting my action” link opens the queue without the `signal` filter, but the correct request is still visible and actionable.
- `UX-HR-002` remains open: Finance's expanded detail omits the HR reviewer name. The read-only request/reviewer/audit checks confirm HR approval attribution.
- No application code was changed. The row-level audit verifier was corrected after attempt 15; this clean run passed it. The guarded runner's stack reuse/readiness path also completed this invocation within its command window.
- Rejection/resubmission, cancellation, self-approval denial, direct API negative authorization, stale approval, delegation, and race variants remain planned and are not covered by this happy-path run.
