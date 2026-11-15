# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: maintenance-breakdown.process.spec.ts >> MNT01-BREAKDOWN — corrective maintenance through assignment, repair log, and completion
- Location: e2e-process/maintenance-breakdown.process.spec.ts:208:1

# Error details

```
TimeoutError: locator.click: Timeout 15000ms exceeded.
Call log:
  - waiting for getByRole('navigation', { name: 'Modules' }).locator('a[href="/maintenance/work-orders"]')
    - locator resolved to <a data-discover="true" href="/maintenance/work-orders" class="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-inset relative flex min-h-11 items-center gap-2 py-1.5 mb-0.5 rounded-md text-base transition-colors duration-fast md:min-h-9 text-secondary hover:bg-elevated hover:text-primary mx-2 px-3">…</a>
  - attempting click action
    2 × waiting for element to be visible, enabled and stable
      - element is not visible
    - retrying click action
    - waiting 20ms
    2 × waiting for element to be visible, enabled and stable
      - element is not visible
    - retrying click action
      - waiting 100ms
    21 × waiting for element to be visible, enabled and stable
       - element is not visible
     - retrying click action
       - waiting 500ms

```

# Test source

```ts
  1   | import { test, expect } from './fixtures';
  2   | import { actorPage, loadActors } from './actor-session';
  3   | import type { E2EActor } from './actor-session';
  4   | import { createJourneyContext, runProcessStep } from './process-runner';
  5   | import type { JourneyContext } from './process-runner';
  6   | import { readOnlyDatabaseJson } from './helpers/database-reader';
  7   | 
  8   | type ActorPage = Awaited<ReturnType<typeof actorPage>>;
  9   | type WorkOrderSnapshot = {
  10  |   id: number;
  11  |   mwo_number: string;
  12  |   status: string;
  13  |   type: string;
  14  |   priority: string;
  15  |   verification_required: boolean;
  16  |   created_by_email: string;
  17  |   assigned_email: string | null;
  18  |   machine_code: string;
  19  |   machine_status: string;
  20  |   started_at: string | null;
  21  |   completed_at: string | null;
  22  |   downtime_minutes: number;
  23  | };
  24  | type NotificationRow = { type: string; email: string; title: string };
  25  | type AuditRow = { status: string; email: string };
  26  | 
  27  | const plannedSteps = [
  28  |   { name: 'Create a corrective maintenance work order for a free machine', actor: 'maintenance_tech', uiPath: 'Maintenance → Maintenance Work Orders → New work order → Create work order', expectedStatus: 'open' },
  29  |   { name: 'Production manager discovers the order from the work order list', actor: 'production_manager', uiPath: 'Maintenance → Maintenance Work Orders → work order row', expectedStatus: 'open' },
  30  |   { name: 'Maintenance supervisor follows assignment-needed notification and assigns the technician', actor: 'maintenance_supervisor', uiPath: 'Notifications → assignment-needed notification → Assign worker', expectedStatus: 'assigned' },
  31  |   { name: 'Technician follows assignment notification and starts the order', actor: 'maintenance_tech', uiPath: 'Notifications → assigned notification → Confirm start', expectedStatus: 'in_progress' },
  32  |   { name: 'Technician records the passing work result', actor: 'maintenance_tech', uiPath: 'Work order detail → Record work → Save work record', expectedStatus: 'in_progress' },
  33  |   { name: 'Technician completes the work order', actor: 'maintenance_tech', uiPath: 'Work order detail → Complete → Confirm complete', expectedStatus: 'completed' },
  34  | ];
  35  | 
  36  | function actorByName(actors: E2EActor[], actor: string): E2EActor {
  37  |   const match = actors.find((candidate) => candidate.actor === actor);
  38  |   if (!match) throw new Error(`Required seeded E2E actor is missing: ${actor}`);
  39  |   return match;
  40  | }
  41  | 
  42  | function contextValue<T>(context: JourneyContext, key: string): T {
  43  |   const value = context.values[key];
  44  |   if (value === undefined) throw new Error(`Journey context is missing ${key}.`);
  45  |   return value as T;
  46  | }
  47  | 
  48  | function sqlText(value: string): string {
  49  |   return `'${value.replaceAll("'", "''")}'`;
  50  | }
  51  | 
  52  | async function openSession(session: ActorPage): Promise<void> {
  53  |   // The setup project signs this actor in through the real login UI and stores
  54  |   // that actor's own session. Enter through the sign-in route so the app sends
  55  |   // an already-authenticated user to their normal landing page.
  56  |   await session.page.goto('/sign-in');
  57  |   await expect(session.page).toHaveURL(/\/dashboard(?:\/|$)/);
  58  | }
  59  | 
  60  | async function openMaintenanceMenu(session: ActorPage): Promise<void> {
  61  |   const page = session.page;
  62  |   const link = page.getByRole('navigation', { name: 'Modules' }).locator('a[href="/maintenance/work-orders"]');
  63  |   await expect(link, 'Maintenance Work Orders should be reachable from the sidebar').toHaveCount(1);
  64  |   if (!(await link.isVisible())) {
  65  |     const toggle = page.locator('[data-module="Maintenance"]').getByRole('button');
  66  |     await expect(toggle).toBeVisible();
  67  |     if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click();
  68  |   }
  69  |   await expect(link).toBeVisible();
> 70  |   await link.click();
      |              ^ TimeoutError: locator.click: Timeout 15000ms exceeded.
  71  |   await expect(page.getByRole('heading', { name: 'Maintenance work orders', exact: true })).toBeVisible();
  72  | }
  73  | 
  74  | async function openMaintenanceNotification(session: ActorPage, number: string): Promise<void> {
  75  |   const page = session.page;
  76  |   await page.getByRole('button', { name: /^Notifications(?:, \d+ unread)?$/ }).click();
  77  |   const tray = page.getByRole('dialog', { name: 'Notifications' });
  78  |   await expect(tray).toBeVisible();
  79  |   const link = tray.getByRole('link', { name: new RegExp(number) });
  80  |   await expect(link, `notification hand-off should identify ${number}`).toHaveCount(1);
  81  |   await link.click();
  82  |   await expect(page.getByRole('heading', { level: 1 })).toContainText(number);
  83  | }
  84  | 
  85  | async function expectStatus(page: ActorPage['page'], status: string): Promise<void> {
  86  |   const labels: Record<string, string> = {
  87  |     open: 'Open', assigned: 'Assigned', in_progress: 'In progress', completed: 'Completed', cancelled: 'Cancelled',
  88  |   };
  89  |   await expect(page.locator('[data-layout="page-header"]').getByText(labels[status], { exact: true })).toBeVisible();
  90  | }
  91  | 
  92  | async function workOrder(context: JourneyContext): Promise<WorkOrderSnapshot> {
  93  |   const number = contextValue<string>(context, 'mwoNumber');
  94  |   const rows = await readOnlyDatabaseJson<WorkOrderSnapshot[]>(`
  95  |     SELECT wo.id, wo.mwo_number, wo.status, wo.type, wo.priority,
  96  |            wo.verification_required, creator.email AS created_by_email,
  97  |            technician.email AS assigned_email, machine.machine_code,
  98  |            machine.status AS machine_status, wo.started_at, wo.completed_at,
  99  |            wo.downtime_minutes
  100 |     FROM maintenance_work_orders wo
  101 |     JOIN users creator ON creator.id = wo.created_by
  102 |     LEFT JOIN employees employee ON employee.id = wo.assigned_to
  103 |     LEFT JOIN users technician ON technician.id = employee.user_id
  104 |     JOIN machines machine ON wo.maintainable_type = 'machine' AND machine.id = wo.maintainable_id
  105 |     WHERE wo.mwo_number = ${sqlText(number)}
  106 |   `);
  107 |   expect(rows, `one maintenance work order should exist for ${number}`).toHaveLength(1);
  108 |   return rows[0];
  109 | }
  110 | 
  111 | async function assertDocumentAndStatus(context: JourneyContext, status: string, machineStatus: string): Promise<WorkOrderSnapshot> {
  112 |   const row = await workOrder(context);
  113 |   expect(row.status).toBe(status);
  114 |   expect(row.machine_status).toBe(machineStatus);
  115 |   expect(row.type).toBe('corrective');
  116 |   expect(row.priority).toBe('high');
  117 |   expect(row.verification_required).toBe(false);
  118 |   return row;
  119 | }
  120 | 
  121 | async function notifications(context: JourneyContext): Promise<NotificationRow[]> {
  122 |   const number = contextValue<string>(context, 'mwoNumber');
  123 |   return readOnlyDatabaseJson<NotificationRow[]>(`
  124 |     SELECT notice.type, recipient.email,
  125 |            notice.data ->> 'title' AS title
  126 |     FROM notifications notice
  127 |     JOIN users recipient ON recipient.id = notice.notifiable_id
  128 |     WHERE notice.notifiable_type LIKE '%User'
  129 |       AND notice.data ->> 'entity_id' = (
  130 |         SELECT hash_id FROM maintenance_work_orders WHERE mwo_number = ${sqlText(number)}
  131 |       )
  132 |     ORDER BY notice.type, recipient.email
  133 |   `);
  134 | }
  135 | 
  136 | async function assertNotificationRecipients(
  137 |   context: JourneyContext,
  138 |   expected: Array<{ type: string; email: string }>,
  139 | ): Promise<void> {
  140 |   const rows = await notifications(context);
  141 |   expect(rows.map(({ type, email }) => ({ type, email }))).toEqual(expected);
  142 | }
  143 | 
  144 | async function auditTransitions(context: JourneyContext): Promise<AuditRow[]> {
  145 |   const number = contextValue<string>(context, 'mwoNumber');
  146 |   return readOnlyDatabaseJson<AuditRow[]>(`
  147 |     SELECT audit.new_values ->> 'status' AS status, actor.email
  148 |     FROM audit_logs audit
  149 |     JOIN users actor ON actor.id = audit.user_id
  150 |     WHERE audit.model_type ILIKE '%MaintenanceWorkOrder'
  151 |       AND audit.model_id = (SELECT id FROM maintenance_work_orders WHERE mwo_number = ${sqlText(number)})
  152 |       AND audit.action = 'updated'
  153 |       AND audit.new_values ? 'status'
  154 |     ORDER BY audit.id
  155 |   `);
  156 | }
  157 | 
  158 | async function assertAuditTransitions(context: JourneyContext, expected: AuditRow[]): Promise<void> {
  159 |   expect(await auditTransitions(context)).toEqual(expected);
  160 | }
  161 | 
  162 | async function assertWorkRecords(context: JourneyContext, expectedStructured: number): Promise<void> {
  163 |   const number = contextValue<string>(context, 'mwoNumber');
  164 |   const rows = await readOnlyDatabaseJson<Array<{
  165 |     finding: string | null; action_taken: string | null; result: string | null; email: string;
  166 |   }>>(`
  167 |     SELECT log.finding, log.action_taken, log.result, actor.email
  168 |     FROM maintenance_logs log
  169 |     JOIN maintenance_work_orders wo ON wo.id = log.work_order_id
  170 |     JOIN users actor ON actor.id = log.logged_by
```