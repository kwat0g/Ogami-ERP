# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-profile.process.spec.ts >> HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval
- Location: e2e-process/hr-profile.process.spec.ts:192:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('heading').first()
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByRole('heading').first() with timeout 8000ms
  - waiting for getByRole('heading').first()

```

```yaml
- link "Skip to content":
  - /url: "#main-content"
- banner:
  - button "Toggle sidebar":
    - img
  - link "Ogami ERP Philippine Ogami Corporation":
    - /url: /dashboard
    - img "Ogami ERP"
    - text: Philippine Ogami Corporation
  - navigation "Breadcrumb": Dashboard
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications, 23 unread":
    - img
  - 'status "Real-time updates: Live. Last updated 6:55 AM"': Live
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E finance officer":
    - img "E2E finance officer": EF
    - text: E2E finance officer
    - img
- complementary "Sidebar":
  - navigation "Modules":
    - group "Work":
      - text: Work
      - list:
        - listitem:
          - link "Dashboard":
            - /url: /dashboard
            - img
            - text: Dashboard
        - listitem:
          - link "Action Center":
            - /url: /action-center
            - img
            - text: Action Center
        - listitem:
          - link "Decision Center":
            - /url: /decision-center
            - img
            - text: Decision Center
        - listitem:
          - link "Approval Queue":
            - /url: /approvals
            - img
            - text: Approval Queue
        - listitem:
          - link "Notifications":
            - /url: /notifications
            - img
            - text: Notifications
          - 'button "View all Notifications signals: 23 Unread notifications"': "23"
    - button "Finance"
    - button "Procurement"
    - button "Payroll"
    - button "Assets"
  - group "Module controls":
    - button "Expand all"
- main
```

# Test source

```ts
  1   | import { test, expect } from './fixtures';
  2   | import { actorPage, loadActors } from './actor-session';
  3   | import type { E2EActor } from './actor-session';
  4   | import { createJourneyContext, runProcessStep } from './process-runner';
  5   | import { assertAuditActor, assertRecipientsExactly } from './helpers/process-invariants';
  6   | import { readOnlyDatabaseJson } from './helpers/database-reader';
  7   | import { findMailpitMessagesFor, type MailpitSummary } from './helpers/mailpit-reader';
  8   | import { captureAnnotatedStep } from './helpers/annotated-screenshot';
  9   | 
  10  | type ActorPage = Awaited<ReturnType<typeof actorPage>>;
  11  | 
  12  | type EmployeeBaseline = {
  13  |   employee_id: number;
  14  |   employee_no: string;
  15  |   first_name: string;
  16  |   last_name: string;
  17  |   bank_name: string | null;
  18  |   bank_account_cipher: string | null;
  19  | };
  20  | 
  21  | type RequestSnapshot = {
  22  |   request_id: number;
  23  |   status: string;
  24  |   requires_finance: boolean;
  25  |   employee_id: number;
  26  |   requester_email: string;
  27  |   employee_bank_name: string | null;
  28  |   employee_bank_account_cipher: string | null;
  29  |   hr_reviewer_email: string | null;
  30  |   finance_reviewer_email: string | null;
  31  |   request_audit_count: number;
  32  |   employee_bank_audit_count: number;
  33  |   notification_count: number;
  34  | };
  35  | 
  36  | type AuditSnapshot = {
  37  |   action: string;
  38  |   actor_email: string | null;
  39  |   status: string | null;
  40  | };
  41  | 
  42  | const plannedSteps = [
  43  |   { name: 'Employee, HR, and Finance open their own profile and review queues', actor: 'employee / hr_officer / finance_officer', uiPath: 'Employee Home → My profile; Human Resources → Profile Change Requests; Payroll → Bank Account Changes', expectedStatus: 'no open profile change request' },
  44  |   { name: 'Employee submits a bank account change and HR receives the actionable queue item', actor: 'employee', uiPath: 'Self-service → My profile → Bank account → Request update → Submit request', expectedStatus: 'pending; HR action available; employee account remains unchanged' },
  45  |   { name: 'HR approves the first leg and Finance receives its queue item', actor: 'hr_officer', uiPath: 'Human Resources → Profile Change Requests → Awaiting my action → expand request → HR Approve', expectedStatus: 'pending_finance; account remains unchanged; Finance action available' },
  46  |   { name: 'Finance approves and the employee account is updated', actor: 'finance_officer', uiPath: 'Payroll → Bank Account Changes → Awaiting my action → expand request → Finance Approve', expectedStatus: 'approved; requested bank account applied' },
  47  | ];
  48  | 
  49  | function actorByName(actors: E2EActor[], actor: string): E2EActor {
  50  |   const found = actors.find((candidate) => candidate.actor === actor);
  51  |   if (!found) throw new Error(`Required seeded E2E actor is missing: ${actor}`);
  52  |   return found;
  53  | }
  54  | 
  55  | function sqlText(value: string): string {
  56  |   return `'${value.replaceAll("'", "''")}'`;
  57  | }
  58  | 
  59  | async function openSidebarItem(
  60  |   session: ActorPage,
  61  |   section: string,
  62  |   href: string,
  63  |   label: string,
  64  | ): Promise<void> {
  65  |   const nav = session.page.getByRole('navigation', { name: 'Modules' });
  66  |   const target = nav.locator(`a[href="${href}"]`);
  67  |   await expect(target, `the ${label} route should be available in this role's sidebar`).toHaveCount(1);
  68  |   if (!(await target.isVisible())) {
  69  |     const module = session.page.locator(`[data-module="${section}"]`);
  70  |     const toggle = module.getByRole('button');
  71  |     await expect(toggle, `the ${section} module should be expandable`).toHaveCount(1);
  72  |     if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click();
  73  |   }
  74  |   await expect(target).toBeVisible();
  75  |   await target.click();
  76  | }
  77  | 
  78  | async function openQueue(session: ActorPage, section: string, heading: string): Promise<void> {
  79  |   // /dashboard is the destination used by the real sign-in flow; / is the
  80  |   // public marketing site and does not enter the authenticated ERP shell.
  81  |   await session.page.goto('/dashboard');
> 82  |   await expect(session.page.getByRole('heading').first()).toBeVisible();
      |                                                           ^ Error: expect(locator).toBeVisible() failed
  83  |   await openSidebarItem(session, section, '/hr/profile-update-requests', heading);
  84  |   await expect(session.page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
  85  | }
  86  | 
  87  | async function requestSnapshot(requesterEmail: string, createdAfter: string, bankName?: string): Promise<RequestSnapshot[]> {
  88  |   return readOnlyDatabaseJson<RequestSnapshot[]>(`
  89  |     SELECT request.id AS request_id,
  90  |            request.status, request.requires_finance, request.employee_id,
  91  |            requester.email AS requester_email,
  92  |            employee.bank_name AS employee_bank_name,
  93  |            employee.bank_account_no AS employee_bank_account_cipher,
  94  |            hr.email AS hr_reviewer_email,
  95  |            finance.email AS finance_reviewer_email,
  96  |            (SELECT COUNT(*)::int FROM audit_logs audit
  97  |              WHERE audit.model_type ILIKE '%ProfileUpdateRequest'
  98  |                AND audit.model_id = request.id) AS request_audit_count,
  99  |            (SELECT COUNT(*)::int FROM audit_logs audit
  100 |              WHERE audit.model_type ILIKE '%Employee'
  101 |                AND audit.model_id = employee.id
  102 |                AND audit.new_values->>'bank_name' = ${sqlText(bankName ?? '')}) AS employee_bank_audit_count,
  103 |            (SELECT COUNT(*)::int FROM notifications notice
  104 |              WHERE notice.data->>'subject_type' ILIKE '%ProfileUpdateRequest'
  105 |                AND notice.data->>'subject_id' = request.id::text) AS notification_count
  106 |       FROM profile_update_requests request
  107 |       JOIN employees employee ON employee.id = request.employee_id
  108 |       JOIN users requester ON requester.id = request.requested_by
  109 |       LEFT JOIN users hr ON hr.id = request.reviewed_by
  110 |       LEFT JOIN users finance ON finance.id = request.finance_reviewed_by
  111 |      WHERE requester.email = ${sqlText(requesterEmail)}
  112 |        AND request.note = 'Bank account change'
  113 |        AND request.created_at >= ${sqlText(createdAfter)}::timestamptz
  114 |      ORDER BY request.id DESC
  115 |      LIMIT 1
  116 |   `);
  117 | }
  118 | 
  119 | async function employeeBankAudit(requesterEmail: string, createdAfter: string, bankName: string): Promise<AuditSnapshot[]> {
  120 |   return readOnlyDatabaseJson<AuditSnapshot[]>(`
  121 |     SELECT audit.action, actor.email AS actor_email,
  122 |            audit.new_values->>'bank_name' AS status
  123 |       FROM audit_logs audit
  124 |       JOIN profile_update_requests request ON audit.model_type ILIKE '%Employee'
  125 |        AND audit.model_id = request.employee_id
  126 |       JOIN users requester ON requester.id = request.requested_by
  127 |       LEFT JOIN users actor ON actor.id = audit.user_id
  128 |      WHERE requester.email = ${sqlText(requesterEmail)}
  129 |        AND request.note = 'Bank account change'
  130 |        AND request.created_at >= ${sqlText(createdAfter)}::timestamptz
  131 |        AND audit.new_values->>'bank_name' = ${sqlText(bankName)}
  132 |      ORDER BY audit.id
  133 |   `);
  134 | }
  135 | 
  136 | async function requestAudit(requesterEmail: string, createdAfter: string): Promise<AuditSnapshot[]> {
  137 |   return readOnlyDatabaseJson<AuditSnapshot[]>(`
  138 |     SELECT audit.action, actor.email AS actor_email,
  139 |            audit.new_values->>'status' AS status
  140 |       FROM audit_logs audit
  141 |       JOIN profile_update_requests request
  142 |         ON audit.model_type ILIKE '%ProfileUpdateRequest'
  143 |        AND audit.model_id = request.id
  144 |       JOIN users requester ON requester.id = request.requested_by
  145 |       LEFT JOIN users actor ON actor.id = audit.user_id
  146 |      WHERE requester.email = ${sqlText(requesterEmail)}
  147 |        AND request.note = 'Bank account change'
  148 |        AND request.created_at >= ${sqlText(createdAfter)}::timestamptz
  149 |      ORDER BY audit.id
  150 |   `);
  151 | }
  152 | 
  153 | async function expectRequestStatus(requesterEmail: string, createdAfter: string, expected: string): Promise<RequestSnapshot> {
  154 |   let rows: RequestSnapshot[] = [];
  155 |   await expect.poll(async () => {
  156 |     rows = await requestSnapshot(requesterEmail, createdAfter);
  157 |     return rows[0]?.status ?? 'missing';
  158 |   }, { timeout: 15_000, message: `profile update request should reach ${expected}` }).toBe(expected);
  159 |   if (!rows[0]) throw new Error(`Profile update request for ${requesterEmail} disappeared.`);
  160 |   return rows[0];
  161 | }
  162 | 
  163 | async function clickBadgeHandoff(session: ActorPage, itemLabel: string, expectedCount: number): Promise<void> {
  164 |   const badge = session.page.getByRole('button', {
  165 |     name: new RegExp(`View all ${itemLabel} signals: ${expectedCount}`),
  166 |   });
  167 |   await expect(badge, `${itemLabel} should expose the actionable work count`).toBeVisible();
  168 |   await expect(badge).toContainText(String(expectedCount));
  169 |   await badge.click();
  170 |   const signalMenu = session.page.getByRole('dialog', { name: `${itemLabel} signals` });
  171 |   const actionable = signalMenu.getByRole('link').filter({ hasText: 'Awaiting my action' });
  172 |   await expect(actionable, `${itemLabel} should expose the actionable queue link`).toHaveCount(1);
  173 |   await expect(actionable).toContainText(String(expectedCount));
  174 |   await actionable.click();
  175 |   // The live SPA currently routes this signal to the queue path without the
  176 |   // expected `signal=awaiting_my_action` query (UX-HR-001 is tracked).
  177 |   await expect(session.page).toHaveURL(/\/hr\/profile-update-requests(?:\?|$)/);
  178 | }
  179 | 
  180 | async function expandEmployeeRequest(session: ActorPage, employeeName: string): Promise<void> {
  181 |   const row = session.page.getByRole('row').filter({ hasText: employeeName });
  182 |   await expect(row, 'the employee request should be present in this role\'s actionable queue').toHaveCount(1);
```