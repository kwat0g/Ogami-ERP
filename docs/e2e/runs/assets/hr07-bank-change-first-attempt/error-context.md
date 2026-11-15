# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-profile.process.spec.ts >> HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval
- Location: e2e-process/hr-profile.process.spec.ts:176:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('button', { name: 'My profile', exact: true })
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByRole('button', { name: 'My profile', exact: true }) with timeout 8000ms
  - waiting for getByRole('button', { name: 'My profile', exact: true })

```

```yaml
- link "Skip to content":
  - /url: "#main-content"
- banner:
  - navigation:
    - link "Philippine Ogami Corporation Philippine Ogami Corporation Philippines":
      - /url: "#top"
      - img "Philippine Ogami Corporation"
      - text: Philippine Ogami Corporation Philippines
    - link "Capabilities":
      - /url: "#capabilities"
    - link "Process":
      - /url: "#process"
    - link "Quality":
      - /url: "#quality"
    - link "Filipino-made":
      - /url: "#filipino-made"
    - link "Contact":
      - /url: "#contact"
    - button "Sign in":
      - img
      - text: Sign in
- main:
  - heading "Precision the world trusts, made in the Philippines." [level=1]
  - paragraph: Philippine Ogami Corporation delivers automotive-grade injection-molded parts for Toyota, Nissan, Honda, Suzuki, Yamaha — engineered to IATF 16949 at Block 2, Lots 1 & 2, First Cavite Industrial Estate (FCIE), Barangay Langkaan 1, Dasmariñas, Cavite, Philippines.
  - link "Contact us":
    - /url: "#contact"
    - text: Contact us
    - img
  - link "Explore capabilities":
    - /url: "#capabilities"
  - link "Careers":
    - /url: /careers
    - img
    - text: Careers
  - list:
    - listitem: IATF 16949
    - listitem: 5 active customers
    - listitem: 8 active products
  - figure "Part Oil filler cap Material PA66 resin Std IATF 16949": ±0.05 mm Part Oil filler cap Material PA66 resin Std IATF 16949
  - region "Automakers we supply":
    - paragraph: Parts in vehicles from
    - list:
      - listitem: Toyota
      - listitem: Nissan
      - listitem: Honda
      - listitem: Suzuki
      - listitem: Yamaha
    - paragraph: 35 active employees·5 active customers·8 active products·IATF 16949
  - text: Capabilities
  - heading "One partner, from raw resin to finished assembly." [level=2]
  - paragraph: Every step of the value chain under one roof, under your specification.
  - article:
    - img
    - text: IATF 16949
    - heading "Automotive resin parts" [level=3]
    - paragraph: Safety-critical injection-molded components, produced to automotive-grade discipline and supplied tier-direct to the world’s leading OEMs.
  - article:
    - img
    - text: Tight tolerance
    - heading "Medical & precision parts" [level=3]
    - paragraph: Cleanroom-ready molding for devices that cannot fail — tight tolerances, full lot traceability, and material certainty on every shot.
  - article:
    - img
    - text: Value-added
    - heading "Assembly & sub-assembly" [level=3]
    - paragraph: Molding, fitting, and inspection combined into one controlled flow, so finished assemblies arrive ready for your line.
  - article:
    - img
    - text: Built in-house
    - heading "In-house mold design & tooling" [level=3]
    - paragraph: We design, cut, and maintain our own molds — protecting your tolerances, your lead time, and your intellectual property.
  - text: Parts we mold
  - heading "Components We Produce" [level=2]
  - paragraph: Representative geometries of components we produce, drawn from our own tooling.
  - tablist "Select a part":
    - tab "Wiper bushing" [selected]
    - tab "Pivot cap"
    - tab "Oil filler cap"
    - tab "Spacer collar"
  - term: Material
  - definition: POM resin
  - term: Tolerance
  - definition: ±0.02 mm
  - term: Feature
  - definition: Ø 12.0 bore
  - term: Application
  - definition: Steering & wiper linkages
  - text: Construction Flange + Body + Collar
  - button "Exploded view":
    - img
    - text: Exploded view
  - paragraph:
    - img
    - text: Drag to rotate
    - img
    - text: Auto-turntable
  - figure "Part Wiper bushing Material POM resin Std IATF 16949": ±0.02 mm Part Wiper bushing Material POM resin Std IATF 16949
  - text: The manufacturing process
  - heading "Six controlled steps from resin to certified part." [level=2]
  - paragraph: Quality is not a final gate — it is built into every stage. Here is how a part is made, checked, and released to you.
  - text: STEP 01 / 06 Scroll to explore
  - img
  - article:
    - text: "01"
    - img
    - heading "Material & incoming QC" [level=3]
    - paragraph: Every batch of resin is checked against its certificate of analysis and moisture spec before it is ever accepted into inventory.
  - article:
    - text: "02"
    - img
    - heading "Mold design & tooling" [level=3]
    - paragraph: Precision molds are designed and cut in-house, so the geometry your part depends on is owned and controlled end-to-end.
  - article:
    - text: "03"
    - img
    - heading "Injection molding" [level=3]
    - paragraph: Controlled-process molding holds pressure, temperature, and cycle time to tight windows — repeatable shot after shot, at scale.
  - article:
    - text: "04"
    - img
    - heading "Cooling & forming" [level=3]
    - paragraph: Managed cooling locks in dimensional stability, eliminating warp and internal stress before the part leaves the cell.
  - article:
    - text: "05"
    - img
    - heading "Inspection — AQL 0.65" [level=3]
    - paragraph: Critical dimensions are measured against spec tolerances under AQL 0.65 Level II sampling, with in-process checks between operations.
  - article:
    - text: "06"
    - img
    - heading "Certificate & delivery" [level=3]
    - paragraph: A Certificate of Conformance is generated from real inspection data, and parts ship with full traceability from resin to dock.
  - text: Quality · IATF 16949
  - heading "Quality you can audit, on every shipment." [level=2]
  - paragraph: Quality is built into the chain. Four checkpoints stand between raw material and your receiving dock.
  - text: APQP PPAP MSA & SPC Traceable lot control 8D corrective action 01
  - img
  - heading "Incoming verification" [level=3]
  - paragraph: Resin certificates and moisture are verified before any material is accepted — quality starts before the first shot.
  - text: "02"
  - img
  - heading "In-process sampling" [level=3]
  - paragraph: Periodic sampling between operations catches drift early, so a problem never reaches a full production run.
  - text: "03"
  - img
  - heading "Outgoing AQL inspection" [level=3]
  - paragraph: AQL 0.65 Level II sampling with measured critical dimensions gates every shipment against your tolerances.
  - text: "04"
  - img
  - heading "Certificate of Conformance" [level=3]
  - paragraph: Each delivery carries a CoC built from real measurement data, with traceability from raw resin to your receiving dock.
  - img
  - heading "IATF 16949:2016 Certified" [level=3]
  - paragraph: Our quality management system is certified for automotive production — audited, maintained, and continuously improved.
  - button "Download quality policy":
    - img
    - text: Download quality policy
  - img
  - paragraph: Every shipment ships with a Certificate of Conformance built from real inspection data — outgoing parts are sampled at AQL 0.65 Level II and measured against your critical-dimension tolerances, with full traceability from resin lot to delivery.
  - text: "35"
  - paragraph: Active employees
  - text: "5"
  - paragraph: Active customers
  - text: "8"
  - paragraph: Active products
  - text: Filipino-made
  - heading "World-class precision, proudly made at home." [level=2]
  - paragraph: Philippine Ogami Corporation proves that the precision the world's automakers demand can be engineered right here in Cavite. Every part is shaped by skilled Filipino hands, held to the same standard trusted on assembly lines across the globe.
  - term: "35"
  - definition: Active employees
  - term: "5"
  - definition: Active customers
  - term: "8"
  - definition: Active products
  - figure:
    - button:
      - img
    - button "Zoom in"
    - button "Zoom out"
    - link "Leaflet":
      - /url: https://leafletjs.com
    - text: ©
    - link "OpenStreetMap":
      - /url: https://www.openstreetmap.org/copyright
    - text: contributors 14.2860° N 120.9345° E
    - link "Open in Google Maps":
      - /url: https://www.google.com/maps/search/?api=1&query=14.286%2C120.9345
      - img
      - text: Open in Google Maps
    - img
    - text: Block 2, Lots 1 & 2, First Cavite Industrial Estate (FCIE), Barangay Langkaan 1, Dasmariñas, Cavite, Philippines Dasmariñas · Cavite Plant · datum
  - paragraph: Get in touch
  - heading "Talk to us." [level=2]
  - paragraph: Questions about our capabilities, an existing order, or working with us — send a message and the right person will come back to you.
  - link "sales@ogami.com.ph":
    - /url: mailto:sales@ogami.com.ph
    - img
    - text: sales@ogami.com.ph
  - img
  - text: — Block 2, Lots 1 & 2, First Cavite Industrial Estate (FCIE), Barangay Langkaan 1, Dasmariñas, Cavite, Philippines Full name
  - textbox "Full name"
  - text: Company (optional)
  - textbox "Company (optional)"
  - text: Email
  - textbox "Email"
  - text: Phone (optional)
  - textbox "Phone (optional)"
  - text: Message
  - textbox "Message":
    - /placeholder: How can we help?
  - text: 0 / 2000
  - checkbox "I agree to the Privacy Policy. We use your details only to respond to this enquiry."
  - text: I agree to the
  - link "Privacy Policy":
    - /url: /privacy
  - text: . We use your details only to respond to this enquiry.
  - button "Contact us":
    - text: Contact us
    - img
  - paragraph:
    - text: Prefer email?
    - link "Write to us directly":
      - /url: mailto:sales@ogami.com.ph
- contentinfo:
  - img "Philippine Ogami Corporation"
  - text: Philippine Ogami Corporation IATF 16949
  - paragraph: Philippine Ogami Corporation — precision manufacturing and supply-chain support, delivered with traceability.
  - text: Block 2 Lots 1 & 2 First Cavite Industrial Estate (FCIE) Barangay Langkaan 1 Dasmariñas Cavite Philippines
  - navigation "Footer explore":
    - heading "Explore" [level=2]
    - list:
      - listitem:
        - link "Capabilities":
          - /url: "#capabilities"
      - listitem:
        - link "Process":
          - /url: "#process"
      - listitem:
        - link "Quality":
          - /url: "#quality"
      - listitem:
        - link "Filipino-made":
          - /url: "#filipino-made"
      - listitem:
        - link "Contact":
          - /url: "#contact"
  - navigation "Footer company":
    - heading "Company" [level=2]
    - list:
      - listitem:
        - link "About us":
          - /url: "#top"
      - listitem:
        - link "Careers":
          - /url: /careers
  - navigation "Footer quality":
    - heading "Quality" [level=2]
    - list:
      - listitem:
        - button "Quality policy"
  - heading "Molding insights" [level=2]
  - paragraph: Quality tips, process notes, and Philippine Ogami Corporation news — sent sparingly.
  - text: Email address
  - textbox "Email address":
    - /placeholder: your@email.com
  - button "Subscribe" [disabled]:
    - img
  - checkbox "I agree to the Privacy Policy."
  - text: I agree to the
  - link "Privacy Policy":
    - /url: /privacy
  - text: .
  - heading "Get in touch" [level=2]
  - list:
    - listitem:
      - link "sales@ogami.com.ph":
        - /url: mailto:sales@ogami.com.ph
    - listitem: —
    - listitem:
      - link "Sign in →":
        - /url: /sign-in
  - navigation "Legal":
    - list:
      - listitem:
        - link "Privacy Policy":
          - /url: /privacy
      - listitem:
        - link "Terms & Conditions":
          - /url: /terms
      - listitem:
        - link "Cookie Policy":
          - /url: /cookies
  - paragraph: © 2026 Philippine Ogami Corporation. All rights reserved.
  - paragraph: Made in Philippines
- dialog "Cookie consent":
  - paragraph:
    - text: We use only essential cookies to run this site and remember your choice. We don't use advertising or analytics cookies. See our
    - link "Cookie Policy":
      - /url: /cookies
    - text: .
  - button "Decline cookies":
    - img
  - button "Accept"
  - button "Decline"
- button "Back to top":
  - img
```

# Test source

```ts
  116 |     SELECT audit.action, actor.email AS actor_email,
  117 |            audit.new_values->>'bank_name' AS status
  118 |       FROM audit_logs audit
  119 |       JOIN profile_update_requests request ON request.hash_id = ${sqlText(requestHashId)}
  120 |       LEFT JOIN users actor ON actor.id = audit.user_id
  121 |      WHERE audit.model_type ILIKE '%Employee'
  122 |        AND audit.model_id = request.employee_id
  123 |        AND audit.new_values->>'bank_name' = ${sqlText(bankName)}
  124 |      ORDER BY audit.id
  125 |   `);
  126 | }
  127 | 
  128 | async function requestAudit(requestHashId: string): Promise<AuditSnapshot[]> {
  129 |   return readOnlyDatabaseJson<AuditSnapshot[]>(`
  130 |     SELECT audit.action, actor.email AS actor_email,
  131 |            audit.new_values->>'status' AS status
  132 |       FROM audit_logs audit
  133 |       JOIN profile_update_requests request
  134 |         ON audit.model_type ILIKE '%ProfileUpdateRequest'
  135 |        AND audit.model_id = request.id
  136 |       LEFT JOIN users actor ON actor.id = audit.user_id
  137 |      WHERE request.hash_id = ${sqlText(requestHashId)}
  138 |      ORDER BY audit.id
  139 |   `);
  140 | }
  141 | 
  142 | async function expectRequestStatus(requestHashId: string, expected: string): Promise<RequestSnapshot> {
  143 |   let rows: RequestSnapshot[] = [];
  144 |   await expect.poll(async () => {
  145 |     rows = await requestSnapshot(requestHashId);
  146 |     return rows[0]?.status ?? 'missing';
  147 |   }, { timeout: 15_000, message: `profile update request should reach ${expected}` }).toBe(expected);
  148 |   if (!rows[0]) throw new Error(`Profile update request ${requestHashId} disappeared.`);
  149 |   return rows[0];
  150 | }
  151 | 
  152 | async function clickBadgeHandoff(session: ActorPage, itemLabel: string, expectedCount: number): Promise<void> {
  153 |   const badge = session.page.getByRole('button', {
  154 |     name: new RegExp(`View all ${itemLabel} signals: ${expectedCount} Awaiting my action`),
  155 |   });
  156 |   await expect(badge, `${itemLabel} should expose the actionable work count`).toBeVisible();
  157 |   await expect(badge).toContainText(String(expectedCount));
  158 |   await badge.click();
  159 |   const signalMenu = session.page.getByRole('dialog', { name: `${itemLabel} signals` });
  160 |   await signalMenu.getByRole('link', { name: `Awaiting my action: ${expectedCount}` }).click();
  161 |   await expect(session.page).toHaveURL(/signal=awaiting_my_action/);
  162 | }
  163 | 
  164 | async function expandEmployeeRequest(session: ActorPage, employeeName: string): Promise<void> {
  165 |   const row = session.page.getByRole('row').filter({ hasText: employeeName });
  166 |   await expect(row, 'the employee request should be present in this role\'s actionable queue').toHaveCount(1);
  167 |   await row.locator('td').last().click();
  168 |   await expect(session.page.getByRole('region', { name: 'Requested changes' })).toBeVisible();
  169 | }
  170 | 
  171 | async function mailpitForActors(emails: string[]): Promise<MailpitSummary[]> {
  172 |   const messages = await Promise.all(emails.map((email) => findMailpitMessagesFor(email)));
  173 |   return messages.flat();
  174 | }
  175 | 
  176 | test('HR07-BANK-CHANGE — employee bank change requires independent HR and Finance approval', async ({ browser }, testInfo) => {
  177 |   test.setTimeout(180_000);
  178 | 
  179 |   const actors = await loadActors();
  180 |   const employeeUser = actorByName(actors, 'employee');
  181 |   const hrUser = actorByName(actors, 'hr_officer');
  182 |   const financeUser = actorByName(actors, 'finance_officer');
  183 |   const employee = await actorPage(browser, employeeUser.actor);
  184 |   const hr = await actorPage(browser, hrUser.actor);
  185 |   const finance = await actorPage(browser, financeUser.actor);
  186 |   const journey = createJourneyContext('HR07-BANK-CHANGE');
  187 |   const seenIdentifiers = new Set<string>();
  188 |   const bankName = `E2E BANK ${journey.runId.slice(0, 8).toUpperCase()}`;
  189 |   const accountNumber = `E2E${journey.runId.replaceAll('-', '').slice(0, 10).toUpperCase()}7426`;
  190 |   const employeeRows = await readOnlyDatabaseJson<EmployeeBaseline[]>(`
  191 |     SELECT employee.id AS employee_id, employee.employee_no,
  192 |            employee.first_name, employee.last_name, employee.bank_name,
  193 |            employee.bank_account_no AS bank_account_cipher
  194 |       FROM employees employee
  195 |       JOIN users actor ON actor.employee_id = employee.id
  196 |      WHERE actor.email = ${sqlText(employeeUser.email)}
  197 |   `);
  198 |   expect(employeeRows, 'the E2E employee actor should resolve to one seeded employee').toHaveLength(1);
  199 |   const baseline = employeeRows[0];
  200 |   const employeeName = `${baseline.first_name} ${baseline.last_name}`;
  201 |   const mailBefore = await mailpitForActors([employeeUser.email, hrUser.email, financeUser.email]);
  202 |   let requestHashId = '';
  203 | 
  204 |   try {
  205 |     await runProcessStep({
  206 |       name: plannedSteps[0].name,
  207 |       actor: plannedSteps[0].actor,
  208 |       uiPath: plannedSteps[0].uiPath,
  209 |       expectedStatus: plannedSteps[0].expectedStatus,
  210 |       page: employee.page,
  211 |       plannedSteps,
  212 |       context: journey,
  213 |       testInfo,
  214 |       action: async () => {
  215 |         await employee.page.goto('/');
> 216 |         await expect(employee.page.getByRole('button', { name: 'My profile', exact: true })).toBeVisible();
      |                                                                                              ^ Error: expect(locator).toBeVisible() failed
  217 |         await employee.page.getByRole('button', { name: 'My profile', exact: true }).click();
  218 |         await expect(employee.page.getByRole('heading', { name: /E2E employee/i })).toBeVisible();
  219 |         await expect(employee.page.getByRole('heading', { name: 'Bank account', exact: true })).toBeVisible();
  220 |         await expect(employee.page.getByRole('button', { name: 'Request update', exact: true })).toBeVisible();
  221 |         await expect(employee.page.getByText('No change requests', { exact: true })).toBeVisible();
  222 | 
  223 |         await openQueue(hr, 'Human Resources', 'Profile Change Requests');
  224 |         await expect(hr.page.getByText('No requests', { exact: true })).toBeVisible();
  225 |         await openQueue(finance, 'Payroll', 'Bank Account Changes');
  226 |         await expect(finance.page.getByText('No requests', { exact: true })).toBeVisible();
  227 |       },
  228 |       verify: async () => {
  229 |         expect(await requestSnapshot('non-existent-e2e-profile-request')).toHaveLength(0);
  230 |         expect(baseline.bank_name).toBeNull();
  231 |         expect(baseline.bank_account_cipher).toBeNull();
  232 |       },
  233 |       assertInvariants: async () => {
  234 |         employee.diagnostics.assertClean('employee baseline profile view');
  235 |         hr.diagnostics.assertClean('HR baseline profile queue');
  236 |         finance.diagnostics.assertClean('Finance baseline bank-change queue');
  237 |       },
  238 |     });
  239 | 
  240 |     await runProcessStep({
  241 |       name: plannedSteps[1].name,
  242 |       actor: plannedSteps[1].actor,
  243 |       uiPath: plannedSteps[1].uiPath,
  244 |       expectedStatus: plannedSteps[1].expectedStatus,
  245 |       page: employee.page,
  246 |       plannedSteps,
  247 |       context: journey,
  248 |       testInfo,
  249 |       action: async () => {
  250 |         await employee.page.getByRole('button', { name: 'Request update', exact: true }).click();
  251 |         const dialog = employee.page.getByRole('dialog', { name: 'Request Bank Account Update' });
  252 |         await expect(dialog).toBeVisible();
  253 |         await dialog.getByLabel('Bank name').fill(bankName);
  254 |         await dialog.getByLabel('New account number').fill(accountNumber);
  255 |         const responsePromise = employee.page.waitForResponse((response) =>
  256 |           response.url().includes('/hr/self-service/profile/request-update') && response.request().method() === 'POST',
  257 |         );
  258 |         await dialog.getByRole('button', { name: 'Submit request', exact: true }).click();
  259 |         const response = await responsePromise;
  260 |         expect(response.status(), 'employee UI request should be created').toBe(201);
  261 |         const payload = await response.json() as { data?: { id?: string; status?: string } };
  262 |         requestHashId = payload.data?.id ?? '';
  263 |         expect(requestHashId, 'UI create response should provide the public request identifier').not.toBe('');
  264 |         expect(payload.data?.status).toBe('pending');
  265 |         journey.values.profileUpdateRequestId = requestHashId;
  266 |         journey.values.bankName = bankName;
  267 |         journey.values.accountLast4 = accountNumber.slice(-4);
  268 |         expect(seenIdentifiers.has(requestHashId), 'the UI should not return a duplicate request identifier').toBe(false);
  269 |         seenIdentifiers.add(requestHashId);
  270 | 
  271 |         await expect(employee.page.getByText('Bank change submitted — requires HR + Finance approval.', { exact: true })).toBeVisible();
  272 |         await expect(employee.page.getByText('Pending HR', { exact: true })).toBeVisible();
  273 |         await expect(hr.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(1);
  274 |         await clickBadgeHandoff(hr, 'Profile Change Requests', 1);
  275 |         await expandEmployeeRequest(hr, employeeName);
  276 |         await expect(hr.page.getByText(bankName, { exact: true })).toBeVisible();
  277 |         await expect(hr.page.getByRole('button', { name: 'HR Approve', exact: true })).toBeVisible();
  278 |         await expect(hr.page.getByRole('button', { name: 'Finance Approve', exact: true })).toHaveCount(0);
  279 |         await expect(finance.page.getByRole('row').filter({ hasText: employeeName })).toHaveCount(0);
  280 |       },
  281 |       verify: async () => {
  282 |         const record = await expectRequestStatus(requestHashId, 'pending');
  283 |         expect(record.requires_finance).toBe(true);
  284 |         expect(record.requester_email).toBe(employeeUser.email);
  285 |         expect(record.employee_bank_name).toBe(baseline.bank_name);
  286 |         expect(record.employee_bank_account_cipher).toBe(baseline.bank_account_cipher);
  287 |         expect(record.hr_reviewer_email).toBeNull();
  288 |         expect(record.finance_reviewer_email).toBeNull();
  289 |         expect(record.notification_count).toBe(0);
  290 |       },
  291 |       assertInvariants: async () => {
  292 |         await expect(employee.page.getByText('Pending HR', { exact: true })).toBeVisible();
  293 |         const badge = hr.page.getByRole('button', { name: /View all Profile Change Requests signals: 1 Awaiting my action/ });
  294 |         await expect(badge).toBeVisible();
  295 |         await expect(finance.page.getByRole('button', { name: /View all Bank Account Changes signals/ })).toHaveCount(0);
  296 |         employee.diagnostics.assertClean('employee submits bank change');
  297 |         hr.diagnostics.assertClean('HR receives bank change');
  298 |         finance.diagnostics.assertClean('Finance has no pre-HR bank change');
  299 |       },
  300 |     });
  301 |     await captureAnnotatedStep(hr.page, 'hr_officer', 'HR finds bank change in actionable queue', 2, testInfo);
  302 | 
  303 |     await runProcessStep({
  304 |       name: plannedSteps[2].name,
  305 |       actor: plannedSteps[2].actor,
  306 |       uiPath: plannedSteps[2].uiPath,
  307 |       expectedStatus: plannedSteps[2].expectedStatus,
  308 |       page: hr.page,
  309 |       plannedSteps,
  310 |       context: journey,
  311 |       testInfo,
  312 |       action: async () => {
  313 |         const responsePromise = hr.page.waitForResponse((response) =>
  314 |           response.url().includes(`/hr/profile-update-requests/${requestHashId}/review`) && response.request().method() === 'PATCH',
  315 |         );
  316 |         await hr.page.getByRole('button', { name: 'HR Approve', exact: true }).click();
```