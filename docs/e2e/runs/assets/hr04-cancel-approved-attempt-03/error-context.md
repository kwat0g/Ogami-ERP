# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-leave.process.spec.ts >> HR04-CANCEL-APPROVED — Employee cancels approved leave and restores balance and attendance
- Location: e2e-process/hr-leave.process.spec.ts:1327:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('heading', { name: 'My Leave Requests', exact: true })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByRole('heading', { name: 'My Leave Requests', exact: true }) with timeout 8000ms
  - waiting for getByRole('heading', { name: 'My Leave Requests', exact: true })

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
  - button "Notifications":
    - img
  - 'status "Real-time updates: Reconnecting"': Reconnecting
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E employee":
    - img "E2E employee": EE
    - text: E2E employee
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
          - link "Approval Queue":
            - /url: /approvals
            - img
            - text: Approval Queue
        - listitem:
          - link "Notifications":
            - /url: /notifications
            - img
            - text: Notifications
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
  7   | import { listMailpitMessages } from './helpers/mailpit-reader';
  8   | import { captureAnnotatedStep } from './helpers/annotated-screenshot';
  9   | 
  10  | type ActorPage = Awaited<ReturnType<typeof actorPage>>;
  11  | type BalanceBaseline = {
  12  |   employee_id: number;
  13  |   employee_no: string;
  14  |   department_id: number;
  15  |   employee_name: string;
  16  |   balance_id: number | null;
  17  |   balance_year: number | null;
  18  |   total_credits: string | null;
  19  |   used: string | null;
  20  |   remaining: string | null;
  21  |   leave_request_count: number;
  22  | };
  23  | type LeaveSnapshot = {
  24  |   request_id: number;
  25  |   leave_request_no: string;
  26  |   status: string;
  27  |   days: string;
  28  |   start_date: string;
  29  |   end_date: string;
  30  |   employee_id: number;
  31  |   employee_no: string;
  32  |   requester_email: string;
  33  |   department_id: number;
  34  |   leave_type_code: string;
  35  |   dept_approver_email: string | null;
  36  |   hr_approver_email: string | null;
  37  |   cancelled_by_email: string | null;
  38  |   cancelled_at: string | null;
  39  |   balance_used: string;
  40  |   balance_remaining: string;
  41  |   on_leave_attendance_count: number;
  42  |   request_audit_count: number;
  43  |   rejection_reason: string | null;
  44  | };
  45  | type ApprovalRow = {
  46  |   step_order: number;
  47  |   role_slug: string;
  48  |   action: string;
  49  |   approver_email: string | null;
  50  |   is_current: boolean;
  51  | };
  52  | type NotificationRow = { type: string; recipient_email: string; link_to: string | null };
  53  | type AuditRow = {
  54  |   action: string;
  55  |   actor_email: string | null;
  56  |   status: string | null;
  57  | };
  58  | 
  59  | const plannedSteps = [
  60  |   { name: 'Employee files a Vacation Leave request', actor: 'employee', uiPath: 'Profile menu → Leaves → New request', expectedStatus: 'pending_dept; balance and attendance unchanged' },
  61  |   { name: 'Department Head follows the Leaves badge and approves', actor: 'department_head', uiPath: 'Dashboard → Human Resources menu → Leave Management signals badge → Awaiting my action → Approve', expectedStatus: 'pending_hr; HR receives actionable hand-off' },
  62  |   { name: 'HR follows the Leaves badge and approves', actor: 'hr_officer', uiPath: 'Dashboard → Human Resources menu → Leave Management signals badge → Awaiting my action → Approve', expectedStatus: 'approved; balance consumed and attendance marked on_leave' },
  63  | ];
  64  | 
  65  | function actorByName(actors: E2EActor[], name: string): E2EActor {
  66  |   const actor = actors.find((candidate) => candidate.actor === name);
  67  |   if (!actor) throw new Error(`Required seeded E2E actor is missing: ${name}`);
  68  |   return actor;
  69  | }
  70  | 
  71  | function sqlText(value: string): string {
  72  |   return `'${value.replaceAll("'", "''")}'`;
  73  | }
  74  | 
  75  | async function openSelfServiceLeaves(employee: ActorPage): Promise<void> {
  76  |   await employee.page.goto('/dashboard');
  77  |   await expect(employee.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  78  |   await employee.page.getByRole('button', { name: /^Profile menu for / }).click();
  79  |   await employee.page.getByRole('menuitem', { name: 'Leaves', exact: true }).click();
  80  |   await expect(employee.page).toHaveURL(/\/self-service\/leaves(?:\?|$)/);
> 81  |   await expect(employee.page.getByRole('heading', { name: 'My Leave Requests', exact: true })).toBeVisible();
      |                                                                                                ^ Error: expect(locator).toBeVisible() failed
  82  | }
  83  | 
  84  | async function openDashboard(actor: ActorPage): Promise<void> {
  85  |   await actor.page.goto('/dashboard');
  86  |   await expect(actor.page).toHaveURL(/\/dashboard(?:\/|$)/);
  87  |   await expect(actor.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  88  |   await expect(actor.page.getByRole('button', { name: /^Profile menu for / })).toBeVisible();
  89  | }
  90  | 
  91  | async function openHumanResourcesMenu(actor: ActorPage): Promise<void> {
  92  |   const section = actor.page.getByRole('button', { name: /^Human Resources(?:$|:)/ });
  93  |   await expect(section).toBeVisible();
  94  |   if (await section.getAttribute('aria-expanded') !== 'true') await section.click();
  95  |   await expect(section).toHaveAttribute('aria-expanded', 'true');
  96  |   const leaveManagement = actor.page.getByRole('link', { name: /^Leave Management/ });
  97  |   await expect(leaveManagement).toBeVisible();
  98  |   await leaveManagement.hover();
  99  | }
  100 | 
  101 | async function openLeaveApprovalFromBadge(actor: ActorPage, expectedCount: number): Promise<void> {
  102 |   await openHumanResourcesMenu(actor);
  103 |   const badge = actor.page.getByRole('button', {
  104 |     name: `View all Leave Management signals: ${expectedCount} Leave requests awaiting your approval`,
  105 |     exact: true,
  106 |   });
  107 |   await expect(badge, 'Leave badge should match actionable leave rows').toBeVisible();
  108 |   await badge.click();
  109 |   const signalDialog = actor.page.getByRole('dialog', { name: 'Leave Management signals' });
  110 |   const actionableLink = signalDialog.getByRole('link', {
  111 |     name: new RegExp(`^Leave requests awaiting your approval: ${expectedCount}$`),
  112 |   });
  113 |   await expect(actionableLink).toHaveCount(1);
  114 |   await expect(actionableLink).toContainText(String(expectedCount));
  115 |   await actionableLink.click();
  116 |   await expect(actor.page).toHaveURL(/\/hr\/leaves\?signal=awaiting_my_approval(?:&|$)/);
  117 |   await expect(actor.page.getByRole('heading', { name: 'Leave requests', exact: true })).toBeVisible();
  118 | }
  119 | 
  120 | async function employeeBalanceBaseline(email: string): Promise<BalanceBaseline[]> {
  121 |   return readOnlyDatabaseJson<BalanceBaseline[]>(`
  122 |     SELECT employee.id AS employee_id,
  123 |            employee.employee_no,
  124 |            employee.department_id,
  125 |            employee.first_name || ' ' || employee.last_name AS employee_name,
  126 |            balance.id AS balance_id,
  127 |            balance.year AS balance_year,
  128 |            balance.total_credits::text AS total_credits,
  129 |            balance.used::text AS used,
  130 |            balance.remaining::text AS remaining,
  131 |            (SELECT COUNT(*)::int FROM leave_requests request
  132 |              WHERE request.employee_id = employee.id) AS leave_request_count
  133 |       FROM users actor
  134 |       JOIN employees employee ON employee.id = actor.employee_id
  135 |       JOIN leave_types leave_type ON leave_type.code = 'VL' AND leave_type.is_active = true
  136 |       LEFT JOIN employee_leave_balances balance
  137 |         ON balance.employee_id = employee.id
  138 |        AND balance.leave_type_id = leave_type.id
  139 |        AND balance.year = EXTRACT(YEAR FROM NOW() AT TIME ZONE 'Asia/Manila')::int
  140 |      WHERE actor.email = ${sqlText(email)}
  141 |   `);
  142 | }
  143 | 
  144 | async function nextBusinessDay(employeeEmail: string): Promise<string> {
  145 |   const rows = await readOnlyDatabaseJson<Array<{ date: string }>>(`
  146 |     WITH manila_today AS (
  147 |       SELECT (NOW() AT TIME ZONE 'Asia/Manila')::date AS date
  148 |     ), candidates AS (
  149 |       SELECT (manila_today.date + days_ahead.day)::date AS date
  150 |         FROM manila_today
  151 |         CROSS JOIN generate_series(2, 60) AS days_ahead(day)
  152 |     )
  153 |     SELECT candidates.date::text AS date
  154 |       FROM candidates
  155 |      WHERE EXTRACT(DOW FROM candidates.date) <> 0
  156 |        AND NOT EXISTS (
  157 |          SELECT 1 FROM holidays holiday
  158 |           WHERE holiday.deleted_at IS NULL
  159 |             AND (holiday.date = candidates.date
  160 |               OR (holiday.is_recurring
  161 |                 AND TO_CHAR(holiday.date, 'MM-DD') = TO_CHAR(candidates.date, 'MM-DD')))
  162 |        )
  163 |        AND NOT EXISTS (
  164 |          SELECT 1 FROM attendances attendance
  165 |          JOIN users actor ON actor.employee_id = attendance.employee_id
  166 |           WHERE actor.email = ${sqlText(employeeEmail)}
  167 |             AND attendance.date = candidates.date
  168 |        )
  169 |      ORDER BY candidates.date
  170 |      LIMIT 1
  171 |   `);
  172 |   expect(rows, 'reference calendar should expose a future working day').toHaveLength(1);
  173 |   return rows[0].date;
  174 | }
  175 | 
  176 | async function leaveSnapshot(reason: string): Promise<LeaveSnapshot[]> {
  177 |   return readOnlyDatabaseJson<LeaveSnapshot[]>(`
  178 |     SELECT request.id AS request_id,
  179 |            request.leave_request_no,
  180 |            request.status,
  181 |            request.days::text AS days,
```