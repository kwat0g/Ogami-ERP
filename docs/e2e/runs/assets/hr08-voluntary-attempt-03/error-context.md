# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-separation.process.spec.ts >> HR08-VOLUNTARY — voluntary separation through departmental clearance and final pay
- Location: e2e-process/hr-separation.process.spec.ts:410:1

# Error details

```
Error: Human Resources should be expandable

expect(locator).toHaveCount(expected) failed

Locator:  locator('[data-module="Human Resources"]').getByRole('button')
Expected: 1
Received: 0
Timeout:  8000ms

Call log:
  - Human Resources should be expandable locator('[data-module="Human Resources"]').getByRole('button') with timeout 8000ms
  - waiting for locator('[data-module="Human Resources"]').getByRole('button')
    20 × locator resolved to 0 elements
       - unexpected value "0"

```

# Test source

```ts
  1   | import { test, expect } from './fixtures';
  2   | import { actorPage, loadActors } from './actor-session';
  3   | import type { E2EActor } from './actor-session';
  4   | import { createJourneyContext, runProcessStep } from './process-runner';
  5   | import { assertAuditActor, assertPhpCurrency, assertRecipientsExactly } from './helpers/process-invariants';
  6   | import { readOnlyDatabaseJson } from './helpers/database-reader';
  7   | import { clearClock, freezeClock, readClock } from './helpers/clock';
  8   | import { getMailpitMessage, listMailpitMessages, type MailpitSummary } from './helpers/mailpit-reader';
  9   | 
  10  | type ActorPage = Awaited<ReturnType<typeof actorPage>>;
  11  | type ChecklistEntry = { department: string; item_key: string; label: string };
  12  | type ClearanceItem = ChecklistEntry & {
  13  |   status: 'pending' | 'cleared' | 'blocked';
  14  |   signed_by: number | null;
  15  |   signed_at: string | null;
  16  |   signed_by_email: string | null;
  17  | };
  18  | type ClearanceSnapshot = {
  19  |   clearance_id: number;
  20  |   clearance_no: string;
  21  |   employee_id: number;
  22  |   employee_no: string;
  23  |   employee_status: string;
  24  |   employee_user_active: boolean | null;
  25  |   status: string;
  26  |   separation_reason: string;
  27  |   separation_date: string;
  28  |   final_pay_computed: boolean;
  29  |   final_pay_amount: string | null;
  30  |   final_pay_breakdown: Record<string, string> | null;
  31  |   journal_entry_id: number | null;
  32  |   initiated_by_email: string;
  33  |   finalized_by_email: string | null;
  34  |   items: ClearanceItem[];
  35  | };
  36  | type AuditSnapshot = { action: string; actor_email: string | null; created_at: string };
  37  | type NotificationSnapshot = { type: string; recipient_email: string; link_to: string | null };
  38  | type JournalSnapshot = {
  39  |   entry_number: string;
  40  |   status: string;
  41  |   created_by_email: string | null;
  42  |   posted_by_email: string | null;
  43  |   total_debit: string;
  44  |   total_credit: string;
  45  |   line_debit: string;
  46  |   line_credit: string;
  47  |   line_count: number;
  48  | };
  49  | type HistorySnapshot = {
  50  |   change_type: string;
  51  |   status: string | null;
  52  |   separation_reason: string | null;
  53  |   actor_email: string | null;
  54  | };
  55  | 
  56  | const PROCESS_ID = 'HR08-VOLUNTARY';
  57  | 
  58  | function actorByName(actors: E2EActor[], actor: string): E2EActor {
  59  |   const found = actors.find((candidate) => candidate.actor === actor);
  60  |   if (!found) throw new Error(`Required seeded E2E actor is missing: ${actor}`);
  61  |   return found;
  62  | }
  63  | 
  64  | function sqlText(value: string): string {
  65  |   return `'${value.replaceAll("'", "''")}'`;
  66  | }
  67  | 
  68  | function manilaDate(instant: string): string {
  69  |   const parts = new Intl.DateTimeFormat('en-CA', {
  70  |     timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit',
  71  |   }).formatToParts(new Date(instant));
  72  |   const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));
  73  |   return `${values.year}-${values.month}-${values.day}`;
  74  | }
  75  | 
  76  | async function openSidebarItem(session: ActorPage, section: string, href: string, label: string): Promise<void> {
  77  |   const nav = session.page.getByRole('navigation', { name: 'Modules' });
  78  |   const target = nav.locator(`a[href="${href}"]`);
  79  |   await expect(target, `${label} should be available in this role's sidebar`).toHaveCount(1);
  80  |   if (!(await target.isVisible())) {
  81  |     const module = session.page.locator(`[data-module="${section}"]`);
  82  |     const toggle = module.getByRole('button');
> 83  |     await expect(toggle, `${section} should be expandable`).toHaveCount(1);
      |                                                             ^ Error: Human Resources should be expandable
  84  |     if ((await toggle.getAttribute('aria-expanded')) !== 'true') await toggle.click();
  85  |   }
  86  |   await expect(target).toBeVisible();
  87  |   await target.click();
  88  | }
  89  | 
  90  | async function openDashboard(session: ActorPage): Promise<void> {
  91  |   const nav = session.page.getByRole('navigation', { name: 'Modules' });
  92  |   if (await nav.count() === 0) {
  93  |     // The authenticated session's first destination is its dashboard. After
  94  |     // that initial landing, all movement stays on sidebar links and in-app UI.
  95  |     await session.page.goto('/dashboard');
  96  |   } else if (new URL(session.page.url()).pathname !== '/dashboard') {
  97  |     await nav.locator('a[href="/dashboard"]').click();
  98  |   }
  99  |   await expect(nav).toBeVisible();
  100 |   await expect(session.page.getByRole('button', { name: /^Profile menu for / })).toBeVisible();
  101 | }
  102 | 
  103 | async function configuredChecklist(): Promise<ChecklistEntry[]> {
  104 |   const rows = await readOnlyDatabaseJson<Array<{ items: ChecklistEntry[] }>>(`
  105 |     SELECT value::jsonb AS items
  106 |       FROM settings
  107 |      WHERE key = 'hr.separation.clearance_checklist'
  108 |      LIMIT 1
  109 |   `);
  110 |   if (!rows[0]?.items?.length) throw new Error('The E2E baseline has no configured separation checklist.');
  111 |   const seen = new Set<string>();
  112 |   for (const item of rows[0].items) {
  113 |     if (!item.department?.trim() || !item.item_key?.trim() || !item.label?.trim() || seen.has(item.item_key)) {
  114 |       throw new Error(`The configured separation checklist has an invalid or duplicate item: ${JSON.stringify(item)}`);
  115 |     }
  116 |     seen.add(item.item_key);
  117 |   }
  118 |   return rows[0].items;
  119 | }
  120 | 
  121 | async function employeeBaseline(employeeEmail: string): Promise<Array<{
  122 |   employee_id: number; employee_no: string; first_name: string; last_name: string; status: string;
  123 |   department_name: string; user_active: boolean;
  124 | }>> {
  125 |   return readOnlyDatabaseJson(`
  126 |     SELECT employee.id AS employee_id, employee.employee_no,
  127 |            employee.first_name, employee.last_name, employee.status::text AS status,
  128 |            department.name AS department_name, actor.is_active AS user_active
  129 |       FROM users actor
  130 |       JOIN employees employee ON employee.id = actor.employee_id
  131 |       JOIN departments department ON department.id = employee.department_id
  132 |      WHERE actor.email = ${sqlText(employeeEmail)}
  133 |   `);
  134 | }
  135 | 
  136 | async function clearanceSnapshot(employeeNo: string, remarks: string): Promise<ClearanceSnapshot[]> {
  137 |   return readOnlyDatabaseJson(`
  138 |     SELECT clearance.id AS clearance_id, clearance.clearance_no,
  139 |            employee.id AS employee_id, employee.employee_no, employee.status::text AS employee_status,
  140 |            employee_user.is_active AS employee_user_active,
  141 |            clearance.status::text AS status, clearance.separation_reason,
  142 |            clearance.separation_date::text AS separation_date,
  143 |            clearance.final_pay_computed, clearance.final_pay_amount::text AS final_pay_amount,
  144 |            clearance.final_pay_breakdown,
  145 |            clearance.journal_entry_id,
  146 |            initiator.email AS initiated_by_email, finalizer.email AS finalized_by_email,
  147 |            clearance.clearance_items::json AS items
  148 |       FROM clearances clearance
  149 |       JOIN employees employee ON employee.id = clearance.employee_id
  150 |       JOIN users initiator ON initiator.id = clearance.initiated_by
  151 |       LEFT JOIN users employee_user ON employee_user.employee_id = employee.id
  152 |       LEFT JOIN users finalizer ON finalizer.id = clearance.finalized_by
  153 |      WHERE employee.employee_no = ${sqlText(employeeNo)}
  154 |        AND clearance.remarks = ${sqlText(remarks)}
  155 |      ORDER BY clearance.id DESC
  156 |      LIMIT 1
  157 |   `);
  158 | }
  159 | 
  160 | async function clearanceItems(clearanceId: number): Promise<ClearanceItem[]> {
  161 |   return readOnlyDatabaseJson(`
  162 |     SELECT item->>'department' AS department, item->>'item_key' AS item_key,
  163 |            item->>'label' AS label, item->>'status' AS status,
  164 |            NULLIF(item->>'signed_by', '')::bigint AS signed_by,
  165 |            item->>'signed_at' AS signed_at, signer.email AS signed_by_email
  166 |       FROM clearances clearance
  167 |       CROSS JOIN LATERAL jsonb_array_elements(clearance.clearance_items::jsonb) AS item
  168 |       LEFT JOIN users signer ON signer.id = NULLIF(item->>'signed_by', '')::bigint
  169 |      WHERE clearance.id = ${clearanceId}
  170 |      ORDER BY item->>'item_key'
  171 |   `);
  172 | }
  173 | 
  174 | async function clearanceAudits(clearanceId: number): Promise<AuditSnapshot[]> {
  175 |   return readOnlyDatabaseJson(`
  176 |     SELECT audit.action, actor.email AS actor_email, audit.created_at::text AS created_at
  177 |       FROM audit_logs audit
  178 |       LEFT JOIN users actor ON actor.id = audit.user_id
  179 |      WHERE audit.model_id = ${clearanceId}
  180 |        AND audit.model_type ILIKE '%Clearance'
  181 |      ORDER BY audit.id
  182 |   `);
  183 | }
```