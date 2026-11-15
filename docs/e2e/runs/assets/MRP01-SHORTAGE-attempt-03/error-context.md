# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: mrp.process.spec.ts >> MRP01-SHORTAGE — confirmed order automatically creates a shortage plan, draft PR, and planned work order
- Location: e2e-process/mrp.process.spec.ts:193:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('Draft saved:')
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByText('Draft saved:') with timeout 8000ms
  - waiting for getByText('Draft saved:')

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
    - link "Sales & CRM":
      - /url: /crm
    - img
    - link "Sales Orders":
      - /url: /crm/sales-orders
    - img
    - text: SO-202611-0001
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications":
    - img
  - 'status "Real-time updates: Live. Last updated 6:00 AM"': Live
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E sales officer":
    - img "E2E sales officer": ES
    - text: E2E sales officer
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
          - 'button "View all Action Center signals: 13 Open items needing attention"': "13"
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
    - button "Overview"
    - button "Sales & CRM" [disabled] [expanded]
    - list:
      - listitem:
        - link "Sales Orders":
          - /url: /crm/sales-orders
          - img
          - text: Sales Orders
      - listitem:
        - link "Customers":
          - /url: /crm/customers
          - img
          - text: Customers
      - listitem:
        - link "Products":
          - /url: /crm/products
          - img
          - text: Products
      - listitem:
        - link "Price Agreements":
          - /url: /crm/price-agreements
          - img
          - text: Price Agreements
  - group "Module controls":
    - button "Expand all"
- main:
  - link "Sales orders":
    - /url: /crm/sales-orders
    - img
    - text: Sales orders
  - heading "SO-202611-0001 Draft" [level=1]
  - text: Honda Philippines, Inc.
  - button "Edit":
    - img
    - text: Edit
  - button "Cancel":
    - img
    - text: Cancel
  - button "Confirm order":
    - img
    - text: Confirm order
  - region "Document progress": Order Entered Nov 4, 2026 MRP Planned Ready from stock QC approved stock Delivered Invoiced Paid Closed
  - button "Checking process…" [disabled]
  - heading "Overview" [level=3]
  - term: SO number
  - definition: SO-202611-0001
  - term: Customer
  - definition: Honda Philippines, Inc.
  - term: Date
  - definition: 2026-11-04
  - term: Required delivery date
  - definition: Dec 31, 2026
  - term: Payment terms
  - definition: Net 30
  - term: Payment method
  - definition: Not selected
  - term: Delivery terms
  - definition: —
  - term: Incoterm
  - definition: —
  - term: Freight
  - definition: Responsibility and amount not recorded; confirm with customer before final charges.
  - term: Created by
  - definition: E2E sales officer
  - term: Notes
  - definition: E2E-e62e5886-b75 shortage plan through confirmed demand
  - heading "Order terms" [level=3]
  - text: SO-202611-0001
  - heading "Customer terms captured for this order" [level=3]
  - term: Agreement status
  - definition: Not recorded
  - term: Agreement period
  - definition: Not specified
  - term: Selected payment method
  - definition: Not selected
  - term: Payment terms
  - definition: 30 days
  - term: Shipping paid by
  - definition: Not specified
  - term: Delivery terms
  - definition: Not set
  - term: Incoterm
  - definition: Not set
  - paragraph: Freight is not included in the order total. Payer and fee are not recorded; confirm them with the customer.
  - paragraph: No separate order-specific revision recorded.
  - heading "Line items" [level=3]
  - text: 1 line
  - table:
    - rowgroup:
      - row "# Product Qty Unit price Total Delivered Delivery":
        - columnheader "#"
        - columnheader "Product"
        - columnheader "Qty"
        - columnheader "Unit price"
        - columnheader "Total"
        - columnheader "Delivered"
        - columnheader "Delivery"
    - rowgroup:
      - row "01 PC-001 Pivot Cap Cover Type A 10.00 ₱35.50 Agreed price ₱355.00 0.00 2026-12-31":
        - cell "01"
        - cell "PC-001 Pivot Cap Cover Type A"
        - cell "10.00"
        - cell "₱35.50 Agreed price":
          - text: ₱35.50
          - paragraph: Agreed price
        - cell "₱355.00"
        - cell "0.00"
        - cell "2026-12-31"
    - rowgroup:
      - row "Subtotal ₱355.00":
        - cell "Subtotal"
        - cell "₱355.00"
        - cell
      - row "VAT ₱42.60":
        - cell "VAT"
        - cell "₱42.60"
        - cell
      - row "Total ₱397.60":
        - cell "Total"
        - cell "₱397.60"
        - cell
  - heading "Linked records" [level=3]
  - complementary
  - heading "Activity" [level=3]
  - list:
    - listitem: Sales order SO-202611-0001 created with status Draft. By E2E sales officer Nov 4, 2026 · 2:00 PM
```

# Test source

```ts
  170 |             WHERE so.so_number = ${sqlText(soNumber)} ORDER BY plan.version DESC LIMIT 1
  171 |           ))
  172 |          OR
  173 |          (notice.data->>'subject_type' = 'App\\Modules\\CRM\\Models\\SalesOrder'
  174 |           AND notice.data->>'subject_id' = (
  175 |             SELECT so.id::text FROM sales_orders so WHERE so.so_number = ${sqlText(soNumber)}
  176 |           ))
  177 |        )
  178 |      ORDER BY notice.type, users.email
  179 |   `);
  180 | }
  181 | 
  182 | async function expectMrpCompletion(soNumber: string): Promise<ProcessSnapshot> {
  183 |   let result: ProcessSnapshot[] = [];
  184 |   await expect.poll(async () => {
  185 |     result = await processSnapshot(soNumber);
  186 |     return result[0]?.run_status ?? 'pending';
  187 |   }, { timeout: 30_000, message: 'the confirmed SO should complete its queued automatic MRP run' }).toBe('completed');
  188 |   const snapshot = result[0];
  189 |   if (!snapshot) throw new Error(`Sales order ${soNumber} disappeared while MRP was running.`);
  190 |   return snapshot;
  191 | }
  192 | 
  193 | test('MRP01-SHORTAGE — confirmed order automatically creates a shortage plan, draft PR, and planned work order', async ({ browser }, testInfo) => {
  194 |   test.setTimeout(150_000);
  195 | 
  196 |   const actors = await loadActors();
  197 |   const salesUser = actorByName(actors, 'sales_officer');
  198 |   const ppcUser = actorByName(actors, 'ppc_head');
  199 |   const purchasingUser = actorByName(actors, 'purchasing_officer');
  200 |   const sales = await actorPage(browser, salesUser.actor);
  201 |   const ppc = await actorPage(browser, ppcUser.actor);
  202 |   const purchasing = await actorPage(browser, purchasingUser.actor);
  203 |   const journey = createJourneyContext('MRP01-SHORTAGE');
  204 |   const seenNumbers = new Set<string>();
  205 |   const baseline = await readOnlyDatabaseJson<PlanningBaseline[]>(`
  206 |     SELECT customer.name AS customer_name, customer.email AS customer_email,
  207 |            product.part_number AS product_code, product.name AS product_name,
  208 |            agreement.effective_to::text AS agreement_end,
  209 |            SUM(COALESCE(level.quantity, 0))::text AS component_stock
  210 |       FROM product_price_agreements agreement
  211 |       JOIN customers customer ON customer.id = agreement.customer_id
  212 |       JOIN products product ON product.id = agreement.product_id
  213 |       JOIN bill_of_materials bom ON bom.product_id = product.id AND bom.is_active = true
  214 |       JOIN bom_items bom_item ON bom_item.bom_id = bom.id
  215 |       JOIN items component ON component.id = bom_item.item_id
  216 |       LEFT JOIN stock_levels level ON level.item_id = component.id
  217 |      WHERE customer.is_active = true AND customer.deleted_at IS NULL
  218 |        AND product.is_active = true AND product.deleted_at IS NULL
  219 |        AND agreement.deleted_at IS NULL
  220 |        AND agreement.effective_from <= CURRENT_DATE
  221 |        AND agreement.effective_to >= CURRENT_DATE
  222 |      GROUP BY customer.name, customer.email, product.part_number, product.name, agreement.effective_to
  223 |     HAVING SUM(COALESCE(level.quantity, 0)) = 0
  224 |      ORDER BY customer.name, product.part_number
  225 |      LIMIT 1
  226 |   `);
  227 |   expect(baseline, 'the E2E baseline needs an active customer price agreement and BOM').toHaveLength(1);
  228 |   const chosen = baseline[0];
  229 |   expect(Number(chosen.component_stock)).toBe(0);
  230 |   const mailBefore = await listMailpitMessages();
  231 | 
  232 |   try {
  233 |     await openInternalHome(sales);
  234 |     await openInternalHome(ppc);
  235 |     await openInternalHome(purchasing);
  236 | 
  237 |     await runProcessStep({
  238 |       name: plannedSteps[0].name,
  239 |       actor: plannedSteps[0].actor,
  240 |       uiPath: plannedSteps[0].uiPath,
  241 |       expectedStatus: plannedSteps[0].expectedStatus,
  242 |       page: sales.page,
  243 |       plannedSteps,
  244 |       context: journey,
  245 |       testInfo,
  246 |       action: async (page) => {
  247 |         await openInternalMenuItem(page, 'Sales & CRM', 'Sales Orders', '/crm/sales-orders');
  248 |         await expect(page.getByRole('heading', { name: 'Sales orders', exact: true })).toBeVisible();
  249 |         await page.getByRole('button', { name: 'New sales order', exact: true }).click();
  250 |         await expect(page.getByRole('heading', { name: 'New sales order', exact: true })).toBeVisible();
  251 | 
  252 |         const customer = page.getByRole('combobox', { name: 'Customer' });
  253 |         await customer.click();
  254 |         const customerOption = page.getByRole('option').filter({ hasText: chosen.customer_name });
  255 |         await expect(customerOption).toHaveCount(1);
  256 |         await customerOption.click();
  257 | 
  258 |         const product = page.getByRole('combobox', { name: 'Product for line 1' });
  259 |         await product.click();
  260 |         const productOption = page.getByRole('option').filter({ hasText: chosen.product_code });
  261 |         await expect(productOption).toHaveCount(1);
  262 |         await productOption.click();
  263 | 
  264 |         await page.locator('input[type="date"]').nth(1).fill(chosen.agreement_end);
  265 |         await page.getByPlaceholder('0.00').fill('10');
  266 |         await page.getByPlaceholder('Optional internal notes.').fill(`${journey.values.runPrefix} shortage plan through confirmed demand`);
  267 |         await page.getByRole('button', { name: 'Save as draft', exact: true }).click();
  268 | 
  269 |         const saved = page.getByText('Draft saved:', { exact: false });
> 270 |         await expect(saved).toBeVisible();
      |                             ^ Error: expect(locator).toBeVisible() failed
  271 |         const savedText = await saved.innerText();
  272 |         const soNumber = savedText.match(/SO[-A-Z0-9]+/i)?.[0];
  273 |         expect(soNumber, 'the draft confirmation should display its generated SO number').toBeTruthy();
  274 |         journey.values.soNumber = soNumber;
  275 |         assertDocumentNumberUnique(seenNumbers, soNumber!);
  276 |         await page.getByRole('button', { name: 'Open draft', exact: true }).click();
  277 |         await expect(page.getByRole('heading', { name: new RegExp(soNumber!, 'i') })).toBeVisible();
  278 |         await expect(page.getByText('Draft', { exact: true }).first()).toBeVisible();
  279 |         await expect(page.getByRole('button', { name: 'Confirm order', exact: true })).toBeEnabled();
  280 |       },
  281 |       verify: async (_page, context) => {
  282 |         const rows = await processSnapshot(contextValue<string>(context, 'soNumber'));
  283 |         expect(rows).toHaveLength(1);
  284 |         expect(rows[0].so_status).toBe('draft');
  285 |         expect(rows[0].so_created_by).toBe(salesUser.email);
  286 |         expect(rows[0].plan_id).toBeNull();
  287 |         expect(rows[0].run_id).toBeNull();
  288 |         expect(rows[0].pr_id).toBeNull();
  289 |         expect(rows[0].work_order_count).toBe(0);
  290 |         expect(Number(rows[0].so_total)).toBeGreaterThan(0);
  291 |       },
  292 |       assertInvariants: async () => {
  293 |         sales.diagnostics.assertClean('sales order draft creation');
  294 |         expect(journey.values.soNumber).toBeTruthy();
  295 |       },
  296 |     });
  297 | 
  298 |     await runProcessStep({
  299 |       name: plannedSteps[1].name,
  300 |       actor: plannedSteps[1].actor,
  301 |       uiPath: plannedSteps[1].uiPath,
  302 |       expectedStatus: plannedSteps[1].expectedStatus,
  303 |       page: ppc.page,
  304 |       plannedSteps,
  305 |       context: journey,
  306 |       testInfo,
  307 |       action: async (page, context) => {
  308 |         await openInternalMenuItem(page, 'Sales & CRM', 'Sales Orders', '/crm/sales-orders');
  309 |         await expect(page.getByRole('heading', { name: 'Sales orders', exact: true })).toBeVisible();
  310 |         await page.getByPlaceholder(/Search SO number or customer/).fill(contextValue<string>(context, 'soNumber'));
  311 |         const row = page.getByRole('row').filter({ hasText: contextValue<string>(context, 'soNumber') });
  312 |         await expect(row).toHaveCount(1);
  313 |         await expect(row).toContainText('Draft');
  314 |         await row.click();
  315 |         await expect(page.getByRole('heading', { name: new RegExp(contextValue<string>(context, 'soNumber')) })).toBeVisible();
  316 |         await expect(page.getByText('Draft', { exact: true }).first()).toBeVisible();
  317 |         await expect(page.getByRole('button', { name: 'Confirm order', exact: true })).toHaveCount(0);
  318 |         await expect(page.getByRole('status', { name: /Real-time updates: Live/ })).toBeVisible();
  319 |       },
  320 |       verify: async (_page, context) => {
  321 |         const rows = await processSnapshot(contextValue<string>(context, 'soNumber'));
  322 |         expect(rows).toHaveLength(1);
  323 |         expect(rows[0].so_status).toBe('draft');
  324 |         expect(rows[0].plan_id).toBeNull();
  325 |       },
  326 |       assertInvariants: async () => {
  327 |         ppc.diagnostics.assertClean('PPC opens the draft as observer');
  328 |         await expect(ppc.page.getByRole('button', { name: 'Confirm order', exact: true })).toHaveCount(0);
  329 |       },
  330 |     });
  331 | 
  332 |     await runProcessStep({
  333 |       name: plannedSteps[2].name,
  334 |       actor: plannedSteps[2].actor,
  335 |       uiPath: plannedSteps[2].uiPath,
  336 |       expectedStatus: plannedSteps[2].expectedStatus,
  337 |       page: sales.page,
  338 |       plannedSteps,
  339 |       context: journey,
  340 |       testInfo,
  341 |       action: async (page, context) => {
  342 |         await expect(page.getByRole('heading', { name: new RegExp(contextValue<string>(context, 'soNumber')) })).toBeVisible();
  343 |         await page.getByRole('button', { name: 'Confirm order', exact: true }).click();
  344 |         const dialog = page.getByRole('dialog');
  345 |         await expect(dialog.getByRole('heading', { name: new RegExp(`Confirm ${contextValue<string>(context, 'soNumber')}`) })).toBeVisible();
  346 |         await expect(dialog).toContainText('Run MRP and check material availability');
  347 |         await dialog.getByRole('button', { name: 'Confirm & Start Chain', exact: true }).click();
  348 |         await expect(page.getByText('Confirmed', { exact: true }).first()).toBeVisible();
  349 |         await expect(page.getByRole('button', { name: 'Confirm order', exact: true })).toHaveCount(0);
  350 |       },
  351 |       verify: async (_page, context) => {
  352 |         await expect.poll(async () => (await processSnapshot(contextValue<string>(context, 'soNumber')))[0]?.so_status)
  353 |           .toBe('confirmed');
  354 |         await expect(ppc.page.getByText('Confirmed', { exact: true }).first(), 'PPC observer should update without refreshing').toBeVisible();
  355 |         const rows = await processSnapshot(contextValue<string>(context, 'soNumber'));
  356 |         expect(rows[0].confirm_actor_email).toBe(salesUser.email);
  357 |         expect(rows[0].confirm_audit_count).toBe(1);
  358 |       },
  359 |       assertInvariants: async () => {
  360 |         sales.diagnostics.assertClean('sales order confirmation');
  361 |         ppc.diagnostics.assertClean('PPC live confirmation observer');
  362 |         await expect(ppc.page.getByRole('button', { name: 'Confirm order', exact: true })).toHaveCount(0);
  363 |       },
  364 |     });
  365 | 
  366 |     await runProcessStep({
  367 |       name: plannedSteps[3].name,
  368 |       actor: plannedSteps[3].actor,
  369 |       uiPath: plannedSteps[3].uiPath,
  370 |       expectedStatus: plannedSteps[3].expectedStatus,
```