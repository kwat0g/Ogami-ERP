# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-attendance.process.spec.ts >> HR03-OVERTIME-APPROVE: employee request reaches its department head and updates the open employee view
- Location: e2e-process/hr-attendance.process.spec.ts:1034:1

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
  - text: As of Nov 4, 2026 · 11:13 PM
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
  - paragraph: Updated 11:13 PM
  - heading "Team DTR Today" [level=3]
  - link "Open →":
    - /url: /hr/attendance
  - button "Export Team DTR Today as CSV":
    - img
  - text: "0"
  - paragraph: department DTR records today
  - paragraph: Updated 11:13 PM
  - heading "Team On Leave Today" [level=3]
  - link "Open →":
    - /url: /hr/leaves
  - button "Export Team On Leave Today as CSV":
    - img
  - text: "0"
  - paragraph: approved leave in your department
  - paragraph: Updated 11:13 PM
  - heading "Open Purchase Requests" [level=3]
  - link "Open →":
    - /url: /purchasing/purchase-requests
  - button "Export Open Purchase Requests as CSV":
    - img
  - text: "0"
  - paragraph: open purchase requests
  - paragraph: Updated 11:13 PM
  - heading "Probation Alerts" [level=3]
  - link "Open →":
    - /url: /hr/employees
  - button "Export Probation Alerts as CSV":
    - img
  - text: "0"
  - paragraph: department reviews due in 30 days
  - paragraph: Updated 11:13 PM
  - heading "Outstanding Loans" [level=3]
  - link "Open →":
    - /url: /hr/loans
  - button "Export Outstanding Loans as CSV":
    - img
  - paragraph: Nothing outstanding.
  - paragraph: Updated 11:13 PM
  - heading "Returns Awaiting Approval" [level=3]
  - link "Open →":
    - /url: /return-management
  - button "Export Returns Awaiting Approval as CSV":
    - img
  - paragraph: Nothing outstanding.
  - paragraph: Updated 11:13 PM
  - heading "Latest Payslip" [level=3]
  - link "Open →":
    - /url: /self-service/payslips
  - button "Export Latest Payslip as CSV":
    - img
  - text: —
  - paragraph: No finalized payslip yet
  - paragraph: Updated 11:13 PM
  - heading "My Leave Balance" [level=3]
  - link "Open →":
    - /url: /self-service/leaves
  - button "Export My Leave Balance as CSV":
    - img
  - text: "0"
  - paragraph: remaining leave days
  - paragraph: Updated 11:13 PM
  - heading "My Shift Today" [level=3]
  - link "Open →":
    - /url: /self-service/dtr
  - button "Export My Shift Today as CSV":
    - img
  - text: —
  - paragraph: No hours recorded yet
  - paragraph: Updated 11:13 PM
  - heading "My Pending Requests" [level=3]
  - link "Open →":
    - /url: /self-service
  - button "Export My Pending Requests as CSV":
    - img
  - text: "0"
  - paragraph: your pending requests
  - paragraph: Updated 11:13 PM
- status:
  - img
  - text: OT Request from E2E employee
  - button "Close":
    - img
```

# Test source

```ts
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
  244 |   const toggle = departmentHead.page.getByRole('button', { name: /^Human Resources(?:$|:)/ });
  245 |   await expect(toggle).toBeVisible();
  246 |   if (await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
  247 |   await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  248 | 
  249 |   // The sidebar's temporary module expansion closes after five seconds unless
  250 |   // a contained item is hovered or focused. Keep it open while opening the
  251 |   // signal menu, matching the established Leave approval flow.
  252 |   const overtimeLink = module.getByRole('link', { name: /^Overtime/ });
  253 |   await expect(overtimeLink).toBeVisible();
  254 |   await overtimeLink.hover();
  255 | 
  256 |   const signalButton = departmentHead.page.getByRole('button', { name: /^View all Overtime signals:/ });
> 257 |   await expect(signalButton, 'the Department Head should receive an Overtime approval badge').toBeVisible();
      |                                                                                               ^ Error: the Department Head should receive an Overtime approval badge
  258 |   await signalButton.click();
  259 |   const signalDialog = departmentHead.page.getByRole('dialog', { name: 'Overtime signals' });
  260 |   const actionableLink = signalDialog.getByRole('link', { name: /Overtime requests awaiting your approval: 1/ });
  261 |   await expect(actionableLink).toBeVisible();
  262 |   await actionableLink.click();
  263 |   await expect(departmentHead.page).toHaveURL(/\/hr\/attendance\/overtime\?[^#]*signal=awaiting_my_approval/);
  264 |   await expect(departmentHead.page.getByRole('heading', { name: 'Overtime requests', exact: true })).toBeVisible();
  265 | }
  266 | 
  267 | async function punchBaseline(email: string): Promise<PunchBaseline> {
  268 |   const rows = await readOnlyDatabaseJson<PunchBaseline[]>(`
  269 |     WITH target_employee AS (
  270 |       SELECT employee.id AS employee_id,
  271 |              employee.employee_no,
  272 |              employee.first_name || ' ' || employee.last_name AS employee_name
  273 |         FROM users actor
  274 |         JOIN employees employee ON employee.id = actor.employee_id
  275 |        WHERE actor.email = ${sqlText(email)}
  276 |     ), candidates AS (
  277 |       SELECT employee.employee_id,
  278 |              (timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::date AS work_date
  279 |         FROM target_employee employee
  280 |         CROSS JOIN generate_series(1, 90) AS day_offset(day)
  281 |        WHERE EXTRACT(ISODOW FROM timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day) <> 7
  282 |          AND NOT EXISTS (
  283 |            SELECT 1 FROM holidays holiday
  284 |             WHERE holiday.deleted_at IS NULL
  285 |               AND (holiday.date = (timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::date
  286 |                 OR (holiday.is_recurring
  287 |                   AND EXTRACT(MONTH FROM holiday.date) = EXTRACT(MONTH FROM timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)
  288 |                   AND EXTRACT(DAY FROM holiday.date) = EXTRACT(DAY FROM timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)))
  289 |          )
  290 |          AND NOT EXISTS (
  291 |            SELECT 1 FROM attendances attendance
  292 |             WHERE attendance.employee_id = employee.employee_id
  293 |               AND attendance.date = (timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::date
  294 |          )
  295 |          AND NOT EXISTS (
  296 |            SELECT 1 FROM employee_shift_assignments assignment
  297 |             WHERE assignment.employee_id = employee.employee_id
  298 |               AND assignment.effective_date <= (timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::date
  299 |               AND (assignment.end_date IS NULL OR assignment.end_date >= (timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::date)
  300 |               AND assignment.working_days IS NOT NULL
  301 |               AND jsonb_typeof(assignment.working_days::jsonb) = 'array'
  302 |               AND jsonb_array_length(assignment.working_days::jsonb) > 0
  303 |               AND NOT EXISTS (
  304 |                 SELECT 1 FROM jsonb_array_elements_text(assignment.working_days::jsonb) AS working_day(iso_day)
  305 |                  WHERE working_day.iso_day::int = EXTRACT(ISODOW FROM timezone('Asia/Manila', CURRENT_TIMESTAMP)::date + day_offset.day)::int
  306 |               )
  307 |          )
  308 |        ORDER BY work_date
  309 |        LIMIT 1
  310 |     )
  311 |     SELECT employee.employee_id,
  312 |            employee.employee_no,
  313 |            employee.employee_name,
  314 |            candidate.work_date::text AS work_date,
  315 |            shift.name AS shift_name,
  316 |            shift.start_time::text AS shift_start,
  317 |            shift.end_time::text AS shift_end,
  318 |            shift.break_minutes::int AS break_minutes,
  319 |            shift.grace_minutes::int AS grace_minutes,
  320 |            shift.is_extended,
  321 |            shift.auto_ot_hours::float AS auto_ot_hours,
  322 |            (SELECT COUNT(*)::int FROM employee_shift_assignments assignment
  323 |              WHERE assignment.employee_id = employee.employee_id
  324 |                AND assignment.effective_date <= candidate.work_date
  325 |                AND (assignment.end_date IS NULL OR assignment.end_date >= candidate.work_date)) AS active_assignment_count,
  326 |            (SELECT (setting.value #>> '{}')::float FROM settings setting WHERE setting.key = 'company.latitude') AS latitude,
  327 |            (SELECT (setting.value #>> '{}')::float FROM settings setting WHERE setting.key = 'company.longitude') AS longitude,
  328 |            (SELECT (setting.value #>> '{}')::float FROM settings setting WHERE setting.key = 'company.punch_radius_m') AS radius_m,
  329 |            (SELECT (setting.value #>> '{}')::float FROM settings setting WHERE setting.key = 'company.punch_max_accuracy_m') AS accuracy_cap_m
  330 |       FROM target_employee employee
  331 |       JOIN candidates candidate ON candidate.employee_id = employee.employee_id
  332 |       LEFT JOIN LATERAL (
  333 |         SELECT assignment.shift_id
  334 |           FROM employee_shift_assignments assignment
  335 |          WHERE assignment.employee_id = employee.employee_id
  336 |            AND assignment.effective_date <= candidate.work_date
  337 |            AND (assignment.end_date IS NULL OR assignment.end_date >= candidate.work_date)
  338 |          ORDER BY assignment.effective_date DESC, assignment.id DESC
  339 |          LIMIT 1
  340 |       ) assignment ON true
  341 |       LEFT JOIN shifts default_shift ON default_shift.is_default = true AND default_shift.is_active = true
  342 |       JOIN shifts shift ON shift.id = COALESCE(assignment.shift_id, default_shift.id)
  343 |   `);
  344 |   expect(rows, 'seeded employee and an unoccupied non-holiday workday should resolve').toHaveLength(1);
  345 |   const baseline = rows[0];
  346 |   expect(baseline.shift_name, 'selected shift must be active and resolved from employee assignment/default').toBeTruthy();
  347 |   expect(baseline.latitude).toEqual(expect.any(Number));
  348 |   expect(baseline.longitude).toEqual(expect.any(Number));
  349 |   expect(baseline.radius_m).toBeGreaterThan(0);
  350 |   expect(baseline.accuracy_cap_m).toBeGreaterThan(0);
  351 |   expect(baseline.is_extended, 'the selected baseline shift should not create an OT variant').toBe(false);
  352 |   expect(baseline.auto_ot_hours).toBeNull();
  353 |   return baseline;
  354 | }
  355 | 
  356 | async function punchSnapshot(email: string, date: string): Promise<PunchSnapshot[]> {
  357 |   return readOnlyDatabaseJson<PunchSnapshot[]>(`
```