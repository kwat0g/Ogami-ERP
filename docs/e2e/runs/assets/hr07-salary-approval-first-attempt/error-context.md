# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-profile.process.spec.ts >> HR07-SALARY-APPROVAL — salary adjustment request is blocked by missing UI (GAP-UI-02)
- Location: e2e-process/hr-profile.process.spec.ts:1201:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('navigation', { name: 'Modules' })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByRole('navigation', { name: 'Modules' }) with timeout 8000ms
  - waiting for getByRole('navigation', { name: 'Modules' })

```

# Test source

```ts
  1137 |       uiPath: plannedSteps[2].uiPath,
  1138 |       expectedStatus: plannedSteps[2].expectedStatus,
  1139 |       page: hr.page,
  1140 |       plannedSteps,
  1141 |       context: journey,
  1142 |       testInfo,
  1143 |       action: async () => {
  1144 |         const responsePromise = hr.page.waitForResponse((response) =>
  1145 |           response.url().includes(`/hr/profile-update-requests/${requestHashId}/review`) && response.request().method() === 'PATCH',
  1146 |         );
  1147 |         await hr.page.getByRole('button', { name: 'Approve', exact: true }).click();
  1148 |         const confirm = hr.page.getByRole('dialog');
  1149 |         await expect(confirm.getByText(/whitelisted fields will be applied/i)).toBeVisible();
  1150 |         await confirm.getByRole('button', { name: 'Approve', exact: true }).click();
  1151 |         const response = await responsePromise;
  1152 |         expect(response.status(), 'HR UI approval should be accepted').toBe(200);
  1153 | 
  1154 |         await expect(employee.page.getByRole('listitem').filter({ hasText: 'Mobile' }).getByText('Approved', { exact: true })).toBeVisible();
  1155 |         await expect(employee.page.getByText(mobileNumber, { exact: true })).toBeVisible();
  1156 |         const hrRow = requestRow(hr, employeeName, 'Approved');
  1157 |         await expect(hrRow).toBeVisible();
  1158 |         await expect(hrRow.getByRole('button', { name: 'Approve', exact: true })).toHaveCount(0);
  1159 |         await expect(hrRow.getByRole('button', { name: 'Reject', exact: true })).toHaveCount(0);
  1160 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  1161 |       },
  1162 |       verify: async () => {
  1163 |         const rows = await ordinaryProfileRequestForJourney(employeeUser.email, journey.startedAt);
  1164 |         expect(rows).toHaveLength(1);
  1165 |         expect(rows[0].status).toBe('approved');
  1166 |         expect(rows[0].requires_finance).toBe(false);
  1167 |         expect(rows[0].requester_email).toBe(employeeUser.email);
  1168 |         expect(rows[0].hr_reviewer_email).toBe(hrUser.email);
  1169 |         expect(rows[0].employee_mobile_number).toBe(mobileNumber);
  1170 |         expect(rows[0].request_audit_count).toBeGreaterThanOrEqual(2);
  1171 |         expect(rows[0].notification_count).toBe(0);
  1172 | 
  1173 |         const audits = await ordinaryProfileAuditsForJourney(employeeUser.email, journey.startedAt);
  1174 |         const created = audits.find((audit) => audit.request_id === rows[0].request_id && audit.action === 'created');
  1175 |         const approved = audits.find((audit) => audit.request_id === rows[0].request_id && audit.status === 'approved');
  1176 |         expect(created).toBeDefined();
  1177 |         expect(approved).toBeDefined();
  1178 |         assertAuditActor({ actor_email: created?.actor_email ?? undefined }, employeeUser.email);
  1179 |         assertAuditActor({ actor_email: approved?.actor_email ?? undefined }, hrUser.email);
  1180 | 
  1181 |         const employeeAudits = await employeeMobileAudit(baseline.employee_id, journey.startedAt);
  1182 |         expect(employeeAudits.some((audit) => audit.actor_email === hrUser.email && audit.mobile_number_audited)).toBe(true);
  1183 |         const mailAfter = await mailpitForActors(recipients);
  1184 |         expect(mailAfter.map((message) => message.ID).sort()).toEqual(mailBefore.map((message) => message.ID).sort());
  1185 |       },
  1186 |       assertInvariants: async () => {
  1187 |         await expect(employee.page.getByText(/0 pending · 1 total/)).toBeVisible();
  1188 |         await expect(hr.page.getByRole('button', { name: /View all Profile Change Requests signals/ })).toHaveCount(0);
  1189 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  1190 |         employee.diagnostics.assertClean('employee sees the approved contact change live');
  1191 |         hr.diagnostics.assertClean('HR completes the ordinary profile review');
  1192 |         finance.diagnostics.assertClean('Finance has no action for the ordinary change');
  1193 |       },
  1194 |     });
  1195 |     await captureAnnotatedStep(employee.page, 'employee', 'Employee sees the approved contact value and closed request', 3, testInfo);
  1196 |   } finally {
  1197 |     await Promise.all([employee.context.close(), hr.context.close(), finance.context.close()]);
  1198 |   }
  1199 | });
  1200 | 
  1201 | test('HR07-SALARY-APPROVAL — salary adjustment request is blocked by missing UI (GAP-UI-02)', async ({ browser }, testInfo) => {
  1202 |   test.setTimeout(90_000);
  1203 | 
  1204 |   const actors = await loadActors();
  1205 |   const hrUser = actorByName(actors, 'hr_officer');
  1206 |   const employeeUser = actorByName(actors, 'employee');
  1207 |   const hr = await actorPage(browser, hrUser.actor);
  1208 |   const journey = createJourneyContext('HR07-SALARY-APPROVAL');
  1209 |   const employees = await readOnlyDatabaseJson<Array<{
  1210 |     employee_id: number;
  1211 |     employee_no: string;
  1212 |     first_name: string;
  1213 |     last_name: string;
  1214 |     basic_monthly_salary: string | null;
  1215 |     salary_adjustment_count: number;
  1216 |   }>>(`
  1217 |     SELECT employee.id AS employee_id, employee.employee_no,
  1218 |            employee.first_name, employee.last_name, employee.basic_monthly_salary,
  1219 |            (SELECT COUNT(*)::int FROM salary_adjustments adjustment
  1220 |              WHERE adjustment.employee_id = employee.id) AS salary_adjustment_count
  1221 |       FROM employees employee
  1222 |       JOIN users actor ON actor.employee_id = employee.id
  1223 |      WHERE actor.email = ${sqlText(employeeUser.email)}
  1224 |   `);
  1225 |   expect(employees, 'the seeded Employee should resolve to one compensation record').toHaveLength(1);
  1226 |   const target = employees[0];
  1227 |   expect(target.salary_adjustment_count, 'a fresh baseline should not contain prior salary transactions').toBe(0);
  1228 |   const employeeName = `${target.first_name} ${target.last_name}`;
  1229 |   const plannedSteps = [
  1230 |     { name: 'HR requests an employee salary adjustment through the UI', actor: 'hr_officer', uiPath: 'Human Resources → Employees → Employee detail → Edit / Salary Adjustments', expectedStatus: 'pending; PM checker receives the request' },
  1231 |     { name: 'Production Manager checks the salary adjustment', actor: 'production_manager', uiPath: 'Human Resources → Salary Adjustments → Approve or Reject', expectedStatus: 'pending for VP after PM approval; rejected terminal on PM rejection' },
  1232 |     { name: 'Vice President approves the final salary adjustment', actor: 'vice_president', uiPath: 'Human Resources → Salary Adjustments → Approve or Reject', expectedStatus: 'approved; pay history and effective-date behavior applied' },
  1233 |   ];
  1234 | 
  1235 |   try {
  1236 |     await test.step('HR inspects the employee compensation UI and salary-adjustment queue', async () => {
> 1237 |       await expect(hr.page.getByRole('navigation', { name: 'Modules' })).toBeVisible();
       |                                                                          ^ Error: expect(locator).toBeVisible() failed
  1238 |       await openSidebarItem(hr, 'Human Resources', '/hr/employees', 'Employees');
  1239 |       await expect(hr.page.getByRole('heading', { name: 'Employees', exact: true })).toBeVisible();
  1240 |       await hr.page.getByPlaceholder('Search name or employee no…').fill(target.employee_no);
  1241 |       const employeeRow = hr.page.getByRole('row').filter({ hasText: target.employee_no });
  1242 |       await expect(employeeRow).toHaveCount(1);
  1243 |       await expect(employeeRow).toContainText(employeeName);
  1244 |       await employeeRow.click();
  1245 |       await expect(hr.page.getByRole('heading', { name: new RegExp(employeeName) })).toBeVisible();
  1246 |       await hr.page.getByRole('button', { name: 'Edit', exact: true }).click();
  1247 |       await expect(hr.page.getByText('Compensation is managed through the salary-adjustment approval workflow.', { exact: true })).toBeVisible();
  1248 |       await expect(hr.page.getByRole('link', { name: 'Open salary adjustments', exact: true })).toBeVisible();
  1249 |       await expect(hr.page.getByLabel('Monthly salary', { exact: true })).toHaveCount(0);
  1250 |       await expect(hr.page.getByLabel('Semi-monthly rate', { exact: true })).toHaveCount(0);
  1251 |       await expect(hr.page.getByRole('button', { name: /Request salary adjustment|New salary adjustment/i })).toHaveCount(0);
  1252 | 
  1253 |       await openSidebarItem(hr, 'Human Resources', '/hr/employees', 'Employees');
  1254 |       await hr.page.getByRole('button', { name: 'Salary Adjustments', exact: true }).click();
  1255 |       await expect(hr.page.getByRole('heading', { name: 'Salary Adjustments', exact: true })).toBeVisible();
  1256 |       await expect(hr.page.getByText('No adjustments', { exact: true })).toBeVisible();
  1257 |       await expect(hr.page.getByRole('button', { name: /Request salary adjustment|New salary adjustment/i })).toHaveCount(0);
  1258 |       journey.values.employeeNo = target.employee_no;
  1259 |       await captureAnnotatedStep(hr.page, 'hr_officer', 'Salary-adjustment queue exposes reviewers but no request action', 1, testInfo);
  1260 |       hr.diagnostics.assertClean('HR inspects salary adjustment entry points');
  1261 |     });
  1262 | 
  1263 |     await runProcessStep({
  1264 |       name: plannedSteps[0].name,
  1265 |       actor: plannedSteps[0].actor,
  1266 |       uiPath: plannedSteps[0].uiPath,
  1267 |       expectedStatus: plannedSteps[0].expectedStatus,
  1268 |       page: hr.page,
  1269 |       plannedSteps,
  1270 |       context: journey,
  1271 |       testInfo,
  1272 |       action: async () => {
  1273 |         test.fixme(true, 'GAP-UI-02: SalaryAdjustment has an API request endpoint and active PM → VP approval chain, but the SPA exposes no HR request form/action. The employee edit screen removes compensation fields; the salary-adjustment page is an approval queue only. Do not create this transactional record through the API.');
  1274 |       },
  1275 |       verify: async () => {
  1276 |         expect(target.salary_adjustment_count).toBe(0);
  1277 |       },
  1278 |       assertInvariants: async () => {
  1279 |         hr.diagnostics.assertClean('salary-adjustment request is blocked at the missing UI stage');
  1280 |       },
  1281 |     });
  1282 |   } finally {
  1283 |     await hr.context.close();
  1284 |   }
  1285 | });
  1286 | 
```