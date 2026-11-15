# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: maintenance-breakdown.process.spec.ts >> MNT01-BREAKDOWN — corrective maintenance through assignment, repair log, and completion
- Location: e2e-process/maintenance-breakdown.process.spec.ts:211:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('[data-layout="page-header"]').getByText('Open', { exact: true })
Expected: visible
Error: strict mode violation: locator('[data-layout="page-header"]').getByText('Open', { exact: true }) resolved to 2 elements:
    1) <span class="inline-flex items-center px-1.5 py-0.5 rounded text-caption font-medium leading-tight whitespace-nowrap transition-colors duration-fast bg-category-1/15 text-primary">Open</span> aka locator('h1').getByText('Open')
    2) <div class="text-xs text-primary font-medium">Open</div> aka getByRole('region', { name: 'Document progress' }).getByText('Open')

Call log:
  - Expect "toBeVisible" locator('[data-layout="page-header"]').getByText('Open', { exact: true }) with timeout 8000ms
  - waiting for locator('[data-layout="page-header"]').getByText('Open', { exact: true })

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
  65  |     // First-visit module expansion is a temporary peek that collapses after
  66  |     // five seconds. Use the sidebar's own persistent Expand all control so the
  67  |     // subsequent user click remains reachable without timing around that UI.
  68  |     const expandAll = page.getByRole('button', { name: 'Expand all', exact: true });
  69  |     await expect(expandAll).toBeVisible();
  70  |     await expandAll.click();
  71  |   }
  72  |   await expect(link).toBeVisible();
  73  |   await link.click();
  74  |   await expect(page.getByRole('heading', { name: 'Maintenance work orders', exact: true })).toBeVisible();
  75  | }
  76  | 
  77  | async function openMaintenanceNotification(session: ActorPage, number: string): Promise<void> {
  78  |   const page = session.page;
  79  |   await page.getByRole('button', { name: /^Notifications(?:, \d+ unread)?$/ }).click();
  80  |   const tray = page.getByRole('dialog', { name: 'Notifications' });
  81  |   await expect(tray).toBeVisible();
  82  |   const link = tray.getByRole('link', { name: new RegExp(number) });
  83  |   await expect(link, `notification hand-off should identify ${number}`).toHaveCount(1);
  84  |   await link.click();
  85  |   await expect(page.getByRole('heading', { level: 1 })).toContainText(number);
  86  | }
  87  | 
  88  | async function expectStatus(page: ActorPage['page'], status: string): Promise<void> {
  89  |   const labels: Record<string, string> = {
  90  |     open: 'Open', assigned: 'Assigned', in_progress: 'In progress', completed: 'Completed', cancelled: 'Cancelled',
  91  |   };
> 92  |   await expect(page.locator('[data-layout="page-header"]').getByText(labels[status], { exact: true })).toBeVisible();
      |                                                                                                        ^ Error: expect(locator).toBeVisible() failed
  93  | }
  94  | 
  95  | async function workOrder(context: JourneyContext): Promise<WorkOrderSnapshot> {
  96  |   const number = contextValue<string>(context, 'mwoNumber');
  97  |   const rows = await readOnlyDatabaseJson<WorkOrderSnapshot[]>(`
  98  |     SELECT wo.id, wo.mwo_number, wo.status, wo.type, wo.priority,
  99  |            wo.verification_required, creator.email AS created_by_email,
  100 |            technician.email AS assigned_email, machine.machine_code,
  101 |            machine.status AS machine_status, wo.started_at, wo.completed_at,
  102 |            wo.downtime_minutes
  103 |     FROM maintenance_work_orders wo
  104 |     JOIN users creator ON creator.id = wo.created_by
  105 |     LEFT JOIN employees employee ON employee.id = wo.assigned_to
  106 |     LEFT JOIN users technician ON technician.id = employee.user_id
  107 |     JOIN machines machine ON wo.maintainable_type = 'machine' AND machine.id = wo.maintainable_id
  108 |     WHERE wo.mwo_number = ${sqlText(number)}
  109 |   `);
  110 |   expect(rows, `one maintenance work order should exist for ${number}`).toHaveLength(1);
  111 |   return rows[0];
  112 | }
  113 | 
  114 | async function assertDocumentAndStatus(context: JourneyContext, status: string, machineStatus: string): Promise<WorkOrderSnapshot> {
  115 |   const row = await workOrder(context);
  116 |   expect(row.status).toBe(status);
  117 |   expect(row.machine_status).toBe(machineStatus);
  118 |   expect(row.type).toBe('corrective');
  119 |   expect(row.priority).toBe('high');
  120 |   expect(row.verification_required).toBe(false);
  121 |   return row;
  122 | }
  123 | 
  124 | async function notifications(context: JourneyContext): Promise<NotificationRow[]> {
  125 |   const number = contextValue<string>(context, 'mwoNumber');
  126 |   return readOnlyDatabaseJson<NotificationRow[]>(`
  127 |     SELECT notice.type, recipient.email,
  128 |            notice.data ->> 'title' AS title
  129 |     FROM notifications notice
  130 |     JOIN users recipient ON recipient.id = notice.notifiable_id
  131 |     WHERE notice.notifiable_type LIKE '%User'
  132 |       AND notice.data ->> 'entity_id' = (
  133 |         SELECT hash_id FROM maintenance_work_orders WHERE mwo_number = ${sqlText(number)}
  134 |       )
  135 |     ORDER BY notice.type, recipient.email
  136 |   `);
  137 | }
  138 | 
  139 | async function assertNotificationRecipients(
  140 |   context: JourneyContext,
  141 |   expected: Array<{ type: string; email: string }>,
  142 | ): Promise<void> {
  143 |   const rows = await notifications(context);
  144 |   expect(rows.map(({ type, email }) => ({ type, email }))).toEqual(expected);
  145 | }
  146 | 
  147 | async function auditTransitions(context: JourneyContext): Promise<AuditRow[]> {
  148 |   const number = contextValue<string>(context, 'mwoNumber');
  149 |   return readOnlyDatabaseJson<AuditRow[]>(`
  150 |     SELECT audit.new_values ->> 'status' AS status, actor.email
  151 |     FROM audit_logs audit
  152 |     JOIN users actor ON actor.id = audit.user_id
  153 |     WHERE audit.model_type ILIKE '%MaintenanceWorkOrder'
  154 |       AND audit.model_id = (SELECT id FROM maintenance_work_orders WHERE mwo_number = ${sqlText(number)})
  155 |       AND audit.action = 'updated'
  156 |       AND audit.new_values ? 'status'
  157 |     ORDER BY audit.id
  158 |   `);
  159 | }
  160 | 
  161 | async function assertAuditTransitions(context: JourneyContext, expected: AuditRow[]): Promise<void> {
  162 |   expect(await auditTransitions(context)).toEqual(expected);
  163 | }
  164 | 
  165 | async function assertWorkRecords(context: JourneyContext, expectedStructured: number): Promise<void> {
  166 |   const number = contextValue<string>(context, 'mwoNumber');
  167 |   const rows = await readOnlyDatabaseJson<Array<{
  168 |     finding: string | null; action_taken: string | null; result: string | null; email: string;
  169 |   }>>(`
  170 |     SELECT log.finding, log.action_taken, log.result, actor.email
  171 |     FROM maintenance_logs log
  172 |     JOIN maintenance_work_orders wo ON wo.id = log.work_order_id
  173 |     JOIN users actor ON actor.id = log.logged_by
  174 |     WHERE wo.mwo_number = ${sqlText(number)}
  175 |       AND log.finding IS NOT NULL
  176 |     ORDER BY log.id
  177 |   `);
  178 |   expect(rows).toHaveLength(expectedStructured);
  179 |   if (expectedStructured > 0) {
  180 |     expect(rows).toEqual([{
  181 |       finding: contextValue<string>(context, 'finding'),
  182 |       action_taken: contextValue<string>(context, 'actionTaken'),
  183 |       result: 'passed',
  184 |       email: contextValue<string>(context, 'technicianEmail'),
  185 |     }]);
  186 |   }
  187 | }
  188 | 
  189 | async function assertDowntime(context: JourneyContext, ended: boolean): Promise<void> {
  190 |   const number = contextValue<string>(context, 'mwoNumber');
  191 |   const rows = await readOnlyDatabaseJson<Array<{
  192 |     category: string; end_time: string | null; duration_minutes: number | null;
```