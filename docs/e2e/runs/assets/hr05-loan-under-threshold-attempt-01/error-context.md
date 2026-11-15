# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-loans.process.spec.ts >> HR05-LOAN-UNDER-THRESHOLD — Company loan under ₱50,000 skips VP and completes through Finance
- Location: e2e-process/hr-loans.process.spec.ts:652:1

# Error details

```
Error: the Human Resources module should be expandable

expect(locator).toHaveCount(expected) failed

Locator:  locator('[data-module="Human Resources"]').getByRole('button')
Expected: 1
Received: 0
Timeout:  8000ms

Call log:
  - the Human Resources module should be expandable locator('[data-module="Human Resources"]').getByRole('button') with timeout 8000ms
  - waiting for locator('[data-module="Human Resources"]').getByRole('button')
    19 × locator resolved to 0 elements
       - unexpected value "0"

```

# Test source

```ts
  1   | import { test, expect } from './fixtures';
  2   | import { actorPage, loadActors } from './actor-session';
  3   | import type { E2EActor } from './actor-session';
  4   | import { createJourneyContext, runProcessStep } from './process-runner';
  5   | import { assertAuditActor, assertRecipientsExactly, assertSecondViewerRealtime } from './helpers/process-invariants';
  6   | import { readOnlyDatabaseJson } from './helpers/database-reader';
  7   | import { listMailpitMessages } from './helpers/mailpit-reader';
  8   | 
  9   | type ActorPage = Awaited<ReturnType<typeof actorPage>>;
  10  | type LoanSnapshot = {
  11  |   loan_id: number;
  12  |   loan_no: string;
  13  |   status: string;
  14  |   loan_type: string;
  15  |   principal: string;
  16  |   total_paid: string;
  17  |   balance: string;
  18  |   pay_periods_total: number;
  19  |   pay_periods_remaining: number;
  20  |   purpose: string;
  21  |   requester_email: string;
  22  |   employee_id: number;
  23  |   department_id: number;
  24  |   disbursement_journal_entry_id: number | null;
  25  |   payment_count: number;
  26  | };
  27  | type ApprovalSnapshot = {
  28  |   step_order: number;
  29  |   role_slug: string;
  30  |   action: string;
  31  |   approver_email: string | null;
  32  |   remarks: string | null;
  33  | };
  34  | type NoticeSnapshot = { type: string; recipient_email: string; link_to: string | null; dedupe_key: string | null };
  35  | type AuditSnapshot = { action: string; actor_email: string | null; status: string | null };
  36  | type JournalSnapshot = {
  37  |   entry_number: string;
  38  |   status: string;
  39  |   created_by_email: string | null;
  40  |   posted_by_email: string | null;
  41  |   total_debit: string;
  42  |   total_credit: string;
  43  |   line_debit: string;
  44  |   line_credit: string;
  45  | };
  46  | 
  47  | const plannedSteps = [
  48  |   { name: 'Employee submits a cash advance request', actor: 'employee', uiPath: 'Profile menu → Loans → Apply for a loan → Cash Advance', expectedStatus: 'pending; Department Head is the current approval step; no journal entry' },
  49  |   { name: 'Department Head finds and approves the first step', actor: 'department_head', uiPath: 'Sidebar → Approval Queue → My action required → Cash Advance loan → Approve', expectedStatus: 'pending; Finance is the current approval step; no disbursement' },
  50  |   { name: 'Finance follows the approval notification and approves', actor: 'finance_officer', uiPath: 'Notification bell → Loan approval notice → Approve', expectedStatus: 'pending; Vice President is the current approval step; no disbursement' },
  51  |   { name: 'Vice President follows the approval notification and approves', actor: 'vice_president', uiPath: 'Notification bell → Loan approval notice → Approve', expectedStatus: 'active; one balanced disbursement journal entry; Employee is notified' },
  52  | ];
  53  | 
  54  | function actorByName(actors: E2EActor[], name: string): E2EActor {
  55  |   const actor = actors.find((candidate) => candidate.actor === name);
  56  |   if (!actor) throw new Error(`Required seeded E2E actor is missing: ${name}`);
  57  |   return actor;
  58  | }
  59  | 
  60  | function sqlText(value: string): string {
  61  |   return `'${value.replaceAll("'", "''")}'`;
  62  | }
  63  | 
  64  | async function openDashboard(session: ActorPage): Promise<void> {
  65  |   await session.page.goto('/dashboard');
  66  |   await expect(session.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  67  |   await expect(session.page.getByRole('button', { name: /^Profile menu for / })).toBeVisible();
  68  | }
  69  | 
  70  | async function openEmployeeLoans(session: ActorPage): Promise<void> {
  71  |   await openDashboard(session);
  72  |   await session.page.getByRole('button', { name: /^Profile menu for / }).click();
  73  |   await session.page.getByRole('menuitem', { name: 'Loans', exact: true }).click();
  74  |   await expect(session.page).toHaveURL(/\/self-service\/loans(?:\?|$)/);
  75  |   await expect(session.page.getByRole('heading', { name: 'Loans & Cash Advances', exact: true })).toBeVisible();
  76  |   await expect(session.page.getByRole('button', { name: 'Apply for a loan', exact: true }).first()).toBeEnabled();
  77  | }
  78  | 
  79  | async function openSidebarItem(session: ActorPage, section: string, href: string, label: string): Promise<void> {
  80  |   const nav = session.page.getByRole('navigation', { name: 'Modules' });
  81  |   const target = nav.locator(`a[href="${href}"]`);
  82  |   await expect(target, `the ${label} route should be available in this role's sidebar`).toHaveCount(1);
  83  |   if (!(await target.isVisible())) {
  84  |     const module = session.page.locator(`[data-module="${section}"]`);
  85  |     const toggle = module.getByRole('button');
> 86  |     await expect(toggle, `the ${section} module should be expandable`).toHaveCount(1);
      |                                                                        ^ Error: the Human Resources module should be expandable
  87  |     if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click();
  88  |   }
  89  |   await expect(target).toBeVisible();
  90  |   await target.click();
  91  | }
  92  | 
  93  | async function openLoansObserver(session: ActorPage): Promise<void> {
  94  |   await openDashboard(session);
  95  |   await openSidebarItem(session, 'Human Resources', '/hr/loans', 'Employee Loans');
  96  |   await expect(session.page.getByRole('heading', { name: 'Loans & Cash Advance', exact: true })).toBeVisible();
  97  | }
  98  | 
  99  | async function loanSnapshot(purpose: string): Promise<LoanSnapshot[]> {
  100 |   return readOnlyDatabaseJson<LoanSnapshot[]>(`
  101 |     SELECT loan.id AS loan_id, loan.loan_no, loan.status::text AS status,
  102 |            loan.loan_type, loan.principal::text AS principal, loan.total_paid::text AS total_paid,
  103 |            loan.balance::text AS balance, loan.pay_periods_total, loan.pay_periods_remaining,
  104 |            loan.purpose, requester.email AS requester_email, employee.id AS employee_id,
  105 |            employee.department_id, loan.disbursement_journal_entry_id,
  106 |            (SELECT COUNT(*)::int FROM loan_payments payment WHERE payment.loan_id = loan.id) AS payment_count
  107 |       FROM employee_loans loan
  108 |       JOIN employees employee ON employee.id = loan.employee_id
  109 |       JOIN users requester ON requester.employee_id = employee.id
  110 |      WHERE loan.purpose = ${sqlText(purpose)}
  111 |      ORDER BY loan.id DESC
  112 |      LIMIT 1
  113 |   `);
  114 | }
  115 | 
  116 | async function approvalRows(loanId: number): Promise<ApprovalSnapshot[]> {
  117 |   return readOnlyDatabaseJson<ApprovalSnapshot[]>(`
  118 |     SELECT approval.step_order, approval.role_slug, approval.action, approval.remarks,
  119 |            approver.email AS approver_email
  120 |       FROM approval_records approval
  121 |       LEFT JOIN users approver ON approver.id = approval.approver_id
  122 |      WHERE approval.approvable_id = ${loanId}
  123 |        AND approval.approvable_type ILIKE '%EmployeeLoan%'
  124 |        AND approval.is_current = true
  125 |      ORDER BY approval.step_order
  126 |   `);
  127 | }
  128 | 
  129 | async function noticesForLoan(hashId: string): Promise<NoticeSnapshot[]> {
  130 |   return readOnlyDatabaseJson<NoticeSnapshot[]>(`
  131 |     SELECT notification.type, recipient.email AS recipient_email,
  132 |            notification.data->>'link_to' AS link_to, notification.dedupe_key
  133 |       FROM notifications notification
  134 |       JOIN users recipient ON recipient.id = notification.notifiable_id
  135 |      WHERE notification.data->>'entity_id' = ${sqlText(hashId)}
  136 |      ORDER BY notification.id
  137 |   `);
  138 | }
  139 | 
  140 | async function auditRows(loanId: number): Promise<AuditSnapshot[]> {
  141 |   return readOnlyDatabaseJson<AuditSnapshot[]>(`
  142 |     SELECT audit.action, actor.email AS actor_email,
  143 |            COALESCE(audit.new_values->>'status', audit.old_values->>'status') AS status
  144 |       FROM audit_logs audit
  145 |       LEFT JOIN users actor ON actor.id = audit.user_id
  146 |      WHERE (
  147 |        audit.model_id::text = ${sqlText(String(loanId))}
  148 |        AND audit.model_type ILIKE '%EmployeeLoan%'
  149 |      ) OR (
  150 |        audit.model_id IN (
  151 |          SELECT approval.id
  152 |            FROM approval_records approval
  153 |           WHERE approval.approvable_id = ${loanId}
  154 |             AND approval.approvable_type ILIKE '%EmployeeLoan%'
  155 |        )
  156 |        AND audit.model_type ILIKE '%ApprovalRecord%'
  157 |      )
  158 |      ORDER BY audit.id
  159 |   `);
  160 | }
  161 | 
  162 | async function journalRows(loanId: number): Promise<JournalSnapshot[]> {
  163 |   return readOnlyDatabaseJson<JournalSnapshot[]>(`
  164 |     SELECT entry.entry_number, entry.status, creator.email AS created_by_email,
  165 |            poster.email AS posted_by_email,
  166 |            entry.total_debit::text AS total_debit, entry.total_credit::text AS total_credit,
  167 |            COALESCE(SUM(line.debit), 0)::text AS line_debit,
  168 |            COALESCE(SUM(line.credit), 0)::text AS line_credit
  169 |       FROM journal_entries entry
  170 |       LEFT JOIN users creator ON creator.id = entry.created_by
  171 |       LEFT JOIN users poster ON poster.id = entry.posted_by
  172 |       LEFT JOIN journal_entry_lines line ON line.journal_entry_id = entry.id
  173 |      WHERE entry.reference_type = 'loan_disbursement'
  174 |        AND entry.reference_id = ${loanId}
  175 |        AND entry.deleted_at IS NULL
  176 |      GROUP BY entry.id, creator.email, poster.email
  177 |   `);
  178 | }
  179 | 
  180 | async function waitForLoan(purpose: string, expectedStatus: string): Promise<LoanSnapshot> {
  181 |   let rows: LoanSnapshot[] = [];
  182 |   await expect.poll(async () => {
  183 |     rows = await loanSnapshot(purpose);
  184 |     return rows[0]?.status ?? 'missing';
  185 |   }, { timeout: 15_000, message: `loan should reach ${expectedStatus}` }).toBe(expectedStatus);
  186 |   if (!rows[0]) throw new Error(`Loan request disappeared while waiting for ${expectedStatus}.`);
```