# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-attendance.process.spec.ts >> HR03-OVERTIME-APPROVE: employee request reaches its department head and updates the open employee view
- Location: e2e-process/hr-attendance.process.spec.ts:1025:1

# Error details

```
Error: the Department Head should receive an Overtime approval badge

expect(locator).toBeVisible() failed

Locator: getByRole('button', { name: /^View all Overtime signals:/ })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - the Department Head should receive an Overtime approval badge getByRole('button', { name: /^View all Overtime signals:/ }) with timeout 8000ms
  - waiting for getByRole('button', { name: /^View all Overtime signals:/ })

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
  - navigation "Breadcrumb":
    - link "Dashboard":
      - /url: /dashboard
    - img
    - text: Department
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications, 1 unread":
    - img
  - 'status "Real-time updates: Live. Last updated 12:00 PM"': Live
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E department head":
    - img "E2E department head": ED
    - text: E2E department head
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
          - 'button "View all Approval Queue signals: 1 Approvals requiring your action"': "1"
        - listitem:
          - link "Notifications":
            - /url: /notifications
            - img
            - text: Notifications
          - 'button "View all Notifications signals: 1 Unread notifications"': "1"
    - 'button "Human Resources: Overtime requests awaiting your approval: 1"': Human Resources
    - button "Sales & CRM"
    - button "Procurement"
  - group "Module controls":
    - button "Expand all"
- main:
  - heading "Department Updated just now" [level=1]
  - text: Approvals, attendance, and headcount.
  - button "Time in"
  - region "Needs your attention":
    - heading "Needs your attention" [level=2]
    - link "View action center":
      - /url: /action-center
    - status: All clear.
  - text: As of Nov 4, 2026 · 10:59 PM
  - heading "Pending Approvals" [level=3]
  - link "Open →":
    - /url: /approvals
  - button "Export Pending Approvals as CSV":
    - img
  - table:
    - rowgroup:
      - row "Document Type Waiting":
        - columnheader "Document"
        - columnheader "Type"
        - columnheader "Waiting"
    - rowgroup:
      - row "2026-11-04 00:00:00 Overtime 0h · 24h left":
        - cell "2026-11-04 00:00:00":
          - link "2026-11-04 00:00:00":
            - /url: /hr/attendance/overtime/j16KyzV0Xl
        - cell "Overtime"
        - cell "0h · 24h left"
  - paragraph: Updated 10:59 PM
  - heading "Team DTR Today" [level=3]
  - link "Open →":
    - /url: /hr/attendance
  - button "Export Team DTR Today as CSV":
    - img
  - text: "0"
  - paragraph: department DTR records today
  - paragraph: Updated 10:59 PM
  - heading "Team On Leave Today" [level=3]
  - link "Open →":
    - /url: /hr/leaves
  - button "Export Team On Leave Today as CSV":
    - img
  - text: "0"
  - paragraph: approved leave in your department
  - paragraph: Updated 10:59 PM
  - heading "Open Purchase Requests" [level=3]
  - link "Open →":
    - /url: /purchasing/purchase-requests
  - button "Export Open Purchase Requests as CSV":
    - img
  - text: "0"
  - paragraph: open purchase requests
  - paragraph: Updated 10:59 PM
  - heading "Probation Alerts" [level=3]
  - link "Open →":
    - /url: /hr/employees
  - button "Export Probation Alerts as CSV":
    - img
  - text: "0"
  - paragraph: department reviews due in 30 days
  - paragraph: Updated 10:59 PM
  - heading "Outstanding Loans" [level=3]
  - link "Open →":
    - /url: /hr/loans
  - button "Export Outstanding Loans as CSV":
    - img
  - paragraph: Nothing outstanding.
  - paragraph: Updated 10:59 PM
  - heading "Returns Awaiting Approval" [level=3]
  - link "Open →":
    - /url: /return-management
  - button "Export Returns Awaiting Approval as CSV":
    - img
  - paragraph: Nothing outstanding.
  - paragraph: Updated 10:59 PM
  - heading "Latest Payslip" [level=3]
  - link "Open →":
    - /url: /self-service/payslips
  - button "Export Latest Payslip as CSV":
    - img
  - text: —
  - paragraph: No finalized payslip yet
  - paragraph: Updated 10:59 PM
  - heading "My Leave Balance" [level=3]
  - link "Open →":
    - /url: /self-service/leaves
  - button "Export My Leave Balance as CSV":
    - img
  - text: "0"
  - paragraph: remaining leave days
  - paragraph: Updated 10:59 PM
  - heading "My Shift Today" [level=3]
  - link "Open →":
    - /url: /self-service/dtr
  - button "Export My Shift Today as CSV":
    - img
  - text: —
  - paragraph: No hours recorded yet
  - paragraph: Updated 10:59 PM
  - heading "My Pending Requests" [level=3]
  - link "Open →":
    - /url: /self-service
  - button "Export My Pending Requests as CSV":
    - img
  - text: "0"
  - paragraph: your pending requests
  - paragraph: Updated 10:59 PM
```

# Test source

```ts
  148 |   return rows[0];
  149 | }
  150 | 
  151 | async function eligibleOvertimeSubmissionRecipients(employeeEmail: string): Promise<string[]> {
  152 |   const rows = await readOnlyDatabaseJson<Array<{ email: string }>>(`
  153 |     WITH requester AS (
  154 |       SELECT employee.id AS employee_id, employee.department_id
  155 |         FROM users actor
  156 |         JOIN employees employee ON employee.id = actor.employee_id
  157 |        WHERE actor.email = ${sqlText(employeeEmail)}
  158 |     )
  159 |     SELECT DISTINCT recipient.email
  160 |       FROM requester
  161 |       JOIN settings setting ON setting.key = 'attendance.overtime_submitted.notification_roles'
  162 |       JOIN users recipient ON recipient.is_active = true AND recipient.employee_id <> requester.employee_id
  163 |       JOIN roles role ON role.id = recipient.role_id
  164 |        AND role.slug IN (SELECT jsonb_array_elements_text(setting.value::jsonb))
  165 |       JOIN employees recipient_employee ON recipient_employee.id = recipient.employee_id
  166 |        AND recipient_employee.department_id = requester.department_id
  167 |       JOIN role_permissions role_permission ON role_permission.role_id = role.id
  168 |       JOIN permissions permission ON permission.id = role_permission.permission_id
  169 |        AND permission.slug = 'attendance.ot.approve'
  170 |      ORDER BY recipient.email
  171 |   `);
  172 |   return rows.map((row) => row.email);
  173 | }
  174 | 
  175 | async function overtimeSnapshot(reason: string): Promise<OvertimeSnapshot[]> {
  176 |   return readOnlyDatabaseJson<OvertimeSnapshot[]>(`
  177 |     SELECT request.id AS request_id,
  178 |            employee.id AS employee_id,
  179 |            employee.employee_no,
  180 |            employee.department_id,
  181 |            requester.email AS requester_email,
  182 |            request.date::text AS date,
  183 |            request.hours_requested::text AS hours_requested,
  184 |            request.reason,
  185 |            request.status,
  186 |            approver.email AS approved_by_email,
  187 |            request.approved_at::text AS approved_at,
  188 |            request.is_auto_detected,
  189 |            (SELECT COUNT(*)::int FROM attendances attendance
  190 |              WHERE attendance.employee_id = request.employee_id
  191 |                AND attendance.date = request.date) AS attendance_count
  192 |       FROM overtime_requests request
  193 |       JOIN employees employee ON employee.id = request.employee_id
  194 |       JOIN users requester ON requester.employee_id = employee.id
  195 |       LEFT JOIN users approver ON approver.id = request.approved_by
  196 |      WHERE request.reason = ${sqlText(reason)}
  197 |      ORDER BY request.id
  198 |   `);
  199 | }
  200 | 
  201 | async function overtimeAuditRows(reason: string): Promise<OvertimeAuditRow[]> {
  202 |   return readOnlyDatabaseJson<OvertimeAuditRow[]>(`
  203 |     SELECT audit.action,
  204 |            actor.email AS actor_email,
  205 |            COALESCE(audit.new_values->>'status', audit.old_values->>'status') AS status
  206 |       FROM audit_logs audit
  207 |       JOIN overtime_requests request
  208 |         ON audit.model_type ILIKE '%OvertimeRequest'
  209 |        AND audit.model_id = request.id
  210 |       LEFT JOIN users actor ON actor.id = audit.user_id
  211 |      WHERE request.reason = ${sqlText(reason)}
  212 |      ORDER BY audit.id
  213 |   `);
  214 | }
  215 | 
  216 | async function overtimeNotificationRows(publicId: string): Promise<OvertimeNotificationRow[]> {
  217 |   return readOnlyDatabaseJson<OvertimeNotificationRow[]>(`
  218 |     SELECT notice.type,
  219 |            recipient.email AS recipient_email,
  220 |            notice.data->>'link_to' AS link_to
  221 |       FROM notifications notice
  222 |       JOIN users recipient
  223 |         ON recipient.id = notice.notifiable_id
  224 |        AND notice.notifiable_type = 'App\\Modules\\Auth\\Models\\User'
  225 |      WHERE notice.data->>'entity_type' = 'overtime_request'
  226 |        AND notice.data->>'entity_id' = ${sqlText(publicId)}
  227 |      ORDER BY notice.created_at, notice.id
  228 |   `);
  229 | }
  230 | 
  231 | async function openSelfServiceOvertime(employee: ActorPage): Promise<void> {
  232 |   await employee.page.goto('/dashboard');
  233 |   await expect(employee.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  234 |   await employee.page.getByRole('button', { name: /^Profile menu for / }).click();
  235 |   await employee.page.getByRole('menuitem', { name: 'Overtime', exact: true }).click();
  236 |   await expect(employee.page).toHaveURL(/\/self-service\/overtime(?:\?|$)/);
  237 |   await expect(employee.page.getByRole('heading', { name: 'Overtime Requests', exact: true })).toBeVisible();
  238 | }
  239 | 
  240 | async function openOvertimeApprovalSignal(departmentHead: ActorPage): Promise<void> {
  241 |   await departmentHead.page.goto('/dashboard');
  242 |   await expect(departmentHead.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
  243 |   const module = departmentHead.page.locator('[data-module="Human Resources"]');
  244 |   const toggle = module.getByRole('button');
  245 |   if (await toggle.count() === 1 && await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
  246 | 
  247 |   const signalButton = departmentHead.page.getByRole('button', { name: /^View all Overtime signals:/ });
> 248 |   await expect(signalButton, 'the Department Head should receive an Overtime approval badge').toBeVisible();
      |                                                                                               ^ Error: the Department Head should receive an Overtime approval badge
  249 |   await signalButton.click();
  250 |   const signalDialog = departmentHead.page.getByRole('dialog', { name: 'Overtime signals' });
  251 |   const actionableLink = signalDialog.getByRole('link', { name: /Overtime requests awaiting your approval: 1/ });
  252 |   await expect(actionableLink).toBeVisible();
  253 |   await actionableLink.click();
  254 |   await expect(departmentHead.page).toHaveURL(/\/hr\/attendance\/overtime\?[^#]*signal=awaiting_my_approval/);
  255 |   await expect(departmentHead.page.getByRole('heading', { name: 'Overtime requests', exact: true })).toBeVisible();
  256 | }
  257 | 
  258 | async function punchBaseline(email: string): Promise<PunchBaseline> {
  259 |   const rows = await readOnlyDatabaseJson<PunchBaseline[]>(`
  260 |     WITH target_employee AS (
  261 |       SELECT employee.id AS employee_id,
  262 |              employee.employee_no,
  263 |              employee.first_name || ' ' || employee.last_name AS employee_name
  264 |         FROM users actor
  265 |         JOIN employees employee ON employee.id = actor.employee_id
  266 |        WHERE actor.email = ${sqlText(email)}
  267 |     ), candidates AS (
  268 |       SELECT employee.employee_id,
  269 |              (timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::date AS work_date
  270 |         FROM target_employee employee
  271 |         CROSS JOIN generate_series(1, 90) AS day_offset(day)
  272 |        WHERE EXTRACT(ISODOW FROM timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day) <> 7
  273 |          AND NOT EXISTS (
  274 |            SELECT 1 FROM holidays holiday
  275 |             WHERE holiday.deleted_at IS NULL
  276 |               AND (holiday.date = (timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::date
  277 |                 OR (holiday.is_recurring
  278 |                   AND EXTRACT(MONTH FROM holiday.date) = EXTRACT(MONTH FROM timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)
  279 |                   AND EXTRACT(DAY FROM holiday.date) = EXTRACT(DAY FROM timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)))
  280 |          )
  281 |          AND NOT EXISTS (
  282 |            SELECT 1 FROM attendances attendance
  283 |             WHERE attendance.employee_id = employee.employee_id
  284 |               AND attendance.date = (timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::date
  285 |          )
  286 |          AND NOT EXISTS (
  287 |            SELECT 1 FROM employee_shift_assignments assignment
  288 |             WHERE assignment.employee_id = employee.employee_id
  289 |               AND assignment.effective_date <= (timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::date
  290 |               AND (assignment.end_date IS NULL OR assignment.end_date >= (timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::date)
  291 |               AND assignment.working_days IS NOT NULL
  292 |               AND jsonb_typeof(assignment.working_days::jsonb) = 'array'
  293 |               AND jsonb_array_length(assignment.working_days::jsonb) > 0
  294 |               AND NOT EXISTS (
  295 |                 SELECT 1 FROM jsonb_array_elements_text(assignment.working_days::jsonb) AS working_day(iso_day)
  296 |                  WHERE working_day.iso_day::int = EXTRACT(ISODOW FROM timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::int
  297 |               )
  298 |          )
  299 |        ORDER BY work_date
  300 |        LIMIT 1
  301 |     )
  302 |     SELECT employee.employee_id,
  303 |            employee.employee_no,
  304 |            employee.employee_name,
  305 |            candidate.work_date::text AS work_date,
  306 |            shift.name AS shift_name,
  307 |            shift.start_time::text AS shift_start,
  308 |            shift.end_time::text AS shift_end,
  309 |            shift.break_minutes::int AS break_minutes,
  310 |            shift.grace_minutes::int AS grace_minutes,
  311 |            shift.is_extended,
  312 |            shift.auto_ot_hours::float AS auto_ot_hours,
  313 |            (SELECT COUNT(*)::int FROM employee_shift_assignments assignment
  314 |              WHERE assignment.employee_id = employee.employee_id
  315 |                AND assignment.effective_date <= candidate.work_date
  316 |                AND (assignment.end_date IS NULL OR assignment.end_date >= candidate.work_date)) AS active_assignment_count,
  317 |            (SELECT (setting.value #>> '{}')::float FROM settings setting WHERE setting.key = 'company.latitude') AS latitude,
  318 |            (SELECT (setting.value #>> '{}')::float FROM settings setting WHERE setting.key = 'company.longitude') AS longitude,
  319 |            (SELECT (setting.value #>> '{}')::float FROM settings setting WHERE setting.key = 'company.punch_radius_m') AS radius_m,
  320 |            (SELECT (setting.value #>> '{}')::float FROM settings setting WHERE setting.key = 'company.punch_max_accuracy_m') AS accuracy_cap_m
  321 |       FROM target_employee employee
  322 |       JOIN candidates candidate ON candidate.employee_id = employee.employee_id
  323 |       LEFT JOIN LATERAL (
  324 |         SELECT assignment.shift_id
  325 |           FROM employee_shift_assignments assignment
  326 |          WHERE assignment.employee_id = employee.employee_id
  327 |            AND assignment.effective_date <= candidate.work_date
  328 |            AND (assignment.end_date IS NULL OR assignment.end_date >= candidate.work_date)
  329 |          ORDER BY assignment.effective_date DESC, assignment.id DESC
  330 |          LIMIT 1
  331 |       ) assignment ON true
  332 |       LEFT JOIN shifts default_shift ON default_shift.is_default = true AND default_shift.is_active = true
  333 |       JOIN shifts shift ON shift.id = COALESCE(assignment.shift_id, default_shift.id)
  334 |   `);
  335 |   expect(rows, 'seeded employee and an unoccupied non-holiday workday should resolve').toHaveLength(1);
  336 |   const baseline = rows[0];
  337 |   expect(baseline.shift_name, 'selected shift must be active and resolved from employee assignment/default').toBeTruthy();
  338 |   expect(baseline.latitude).toEqual(expect.any(Number));
  339 |   expect(baseline.longitude).toEqual(expect.any(Number));
  340 |   expect(baseline.radius_m).toBeGreaterThan(0);
  341 |   expect(baseline.accuracy_cap_m).toBeGreaterThan(0);
  342 |   expect(baseline.is_extended, 'the selected baseline shift should not create an OT variant').toBe(false);
  343 |   expect(baseline.auto_ot_hours).toBeNull();
  344 |   return baseline;
  345 | }
  346 | 
  347 | async function punchSnapshot(email: string, date: string): Promise<PunchSnapshot[]> {
  348 |   return readOnlyDatabaseJson<PunchSnapshot[]>(`
```