# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: inventory.process.spec.ts >> INV01-NEGATIVE-STOCK — Warehouse cannot submit an outbound adjustment beyond available stock
- Location: e2e-process/inventory.process.spec.ts:2264:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText(/Only 1\.000 .* can be removed from this location\./)
Expected: visible
Timeout: 8000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByText(/Only 1\.000 .* can be removed from this location\./) with timeout 8000ms
  - waiting for getByText(/Only 1\.000 .* can be removed from this location\./)

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
    - link "Warehouse":
      - /url: /inventory
    - img
    - link "Stock Adjustments":
      - /url: /inventory/stock-adjustments
    - img
    - text: Create
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications, 1 unread":
    - img
  - 'status "Real-time updates: Live. Last updated 4:40 AM"': Live
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E warehouse staff":
    - img "E2E warehouse staff": EW
    - text: E2E warehouse staff
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
    - 'button "Warehouse: Active items with no usable stock: 11" [disabled] [expanded]': Warehouse
    - list:
      - listitem:
        - link "Inventory Items":
          - /url: /inventory/items
          - img
          - text: Inventory Items
        - 'button "View all Inventory Items signals: 11 Active items with no usable stock, 12 Items at or below reorder point"': "11"
      - listitem:
        - link "Units of Measure":
          - /url: /inventory/uoms
          - img
          - text: Units of Measure
      - listitem:
        - link "Goods Receipts (GRN)":
          - /url: /inventory/grn
          - img
          - text: Goods Receipts (GRN)
      - listitem:
        - link "MRB / Quarantine":
          - /url: /inventory/mrb
          - img
          - text: MRB / Quarantine
      - listitem:
        - link "Material Issues":
          - /url: /inventory/material-issues
          - img
          - text: Material Issues
      - listitem:
        - link "Inventory Stock Levels":
          - /url: /inventory/stock-levels
          - img
          - text: Inventory Stock Levels
      - listitem:
        - link "Stock Adjustments":
          - /url: /inventory/stock-adjustments
          - img
          - text: Stock Adjustments
      - listitem:
        - link "Warehouse Map":
          - /url: /inventory/warehouse-map
          - img
          - text: Warehouse Map
      - listitem:
        - link "Inventory Transfers":
          - /url: /inventory/transfer-orders
          - img
          - text: Inventory Transfers
      - listitem:
        - link "Material Handover":
          - /url: /inventory/picking
          - img
          - text: Material Handover
    - button "Supply Chain"
    - button "Sales & CRM"
    - button "Human Resources"
  - group "Module controls":
    - button "Expand all"
- main:
  - link "Stock adjustments":
    - /url: /inventory/stock-adjustments
    - img
    - text: Stock adjustments
  - heading "Stock adjustment" [level=1]
  - text: Correct stock at a specific location with a recorded reason.
  - group:
    - heading "Stock and location" [level=3]
    - text: Item
    - combobox "Item": RM-001 — Plastic Resin Type A (ABS)
    - button "Show Item"
    - text: Location
    - combobox "Location": MW-A-A-01 · Main Warehouse
    - button "Show Location"
    - text: Filled from the item’s stocked location.
    - heading "Adjustment details" [level=3]
    - text: Direction
    - combobox "Direction":
      - option "Select direction…"
      - option "In"
      - option "Out" [selected]
    - text: Quantity
    - spinbutton "Quantity" [invalid]: "1.001"
    - text: kg
    - alert: Only 1 kg can be removed from this location.
    - text: Unit cost
    - spinbutton "Unit cost" [disabled]: "120.0000"
    - text: Current weighted-average cost. Rechecked when posted. Reason code
    - combobox "Reason code":
      - option "Select reason…"
      - option "Cycle Count Variance" [selected]
      - option "Damage"
      - option "Expiry"
      - option "Theft"
      - option "Found Stock"
      - option "System Correction"
    - text: Reason (recorded for audit)
    - textbox "Reason (recorded for audit)":
      - /placeholder: Explain why the stock needs correction.
      - text: E2E-7e800eee-f56 outbound exceeds available
    - text: At least 10 characters. 43 / 500
    - heading "Adjustment preview" [level=3]
    - paragraph: RM-001 · MW-A-A-01
    - term: On hand
    - definition: 1 kg
    - term: Reserved
    - definition: 0 kg
    - term: Quality held
    - definition: 0 kg
    - term: Can remove
    - definition: 1 kg
    - 'meter "On hand vs reorder point: 1 of 500, below target"': On hand vs reorder point 1 / 500
    - text: Stock after posting — Estimated value ₱120.12
    - paragraph: Adjustments above ₱25,000.00 require Finance approval.
  - button "Cancel"
  - button "Review adjustment" [disabled]
```

# Test source

```ts
  2286 |     sessions.push({ actor, session });
  2287 |     return session;
  2288 |   };
  2289 |   const warehouse = await openActor(warehouseIdentity.actor);
  2290 |   const finance = await openActor(financeIdentity.actor);
  2291 |   const checkDiagnostics = () => {
  2292 |     for (const { actor, session } of sessions) session.diagnostics.assertClean(`${journey.journeyId}:${actor}`);
  2293 |   };
  2294 |   const step = (
  2295 |     actor: string,
  2296 |     name: string,
  2297 |     uiPath: string,
  2298 |     expectedStatus: string,
  2299 |     session: ActorPage,
  2300 |     action: () => Promise<void>,
  2301 |     verify: () => Promise<void>,
  2302 |     invariants: () => Promise<void>,
  2303 |   ) => runProcessStep({
  2304 |     name, actor, uiPath, expectedStatus, page: session.page, plannedSteps, context: journey, testInfo,
  2305 |     action,
  2306 |     verify,
  2307 |     assertInvariants: async () => { await invariants(); checkDiagnostics(); },
  2308 |   });
  2309 | 
  2310 |   try {
  2311 |     await step(
  2312 |       financeIdentity.actor,
  2313 |       'Finance opens Stock Adjustments before the Warehouse attempt',
  2314 |       'Finance → Stock Adjustments',
  2315 |       'no matching adjustment',
  2316 |       finance,
  2317 |       async () => {
  2318 |         await openInternalHome(finance);
  2319 |         await openMenuItem(finance.page, 'Finance', 'Stock Adjustments', '/inventory/stock-adjustments');
  2320 |         await expect(finance.page.getByRole('heading', { name: 'Stock Adjustments', exact: true })).toBeVisible();
  2321 |         await expect(finance.page.getByRole('row').filter({ hasText: rejectedReason })).toHaveCount(0);
  2322 |       },
  2323 |       async () => {
  2324 |         const rows = await readOnlyDatabaseJson<Array<{ count: number }>>(`
  2325 |           SELECT COUNT(*)::int AS count FROM stock_adjustments
  2326 |           WHERE reason IN (${sqlText(stockReason)}, ${sqlText(rejectedReason)})
  2327 |         `);
  2328 |         expect(rows).toEqual([{ count: 0 }]);
  2329 |       },
  2330 |       async () => await expect(finance.page).toHaveURL(/\/inventory\/stock-adjustments(?:\?.*)?$/),
  2331 |     );
  2332 | 
  2333 |     await step(
  2334 |       warehouseIdentity.actor,
  2335 |       'Warehouse posts 1 kg of prerequisite stock through the UI',
  2336 |       'Warehouse → Stock Adjustments → New adjustment → Submit',
  2337 |       'approved; 1.000 kg available',
  2338 |       warehouse,
  2339 |       async () => {
  2340 |         await openInternalHome(warehouse);
  2341 |         await submitLowValueInboundAdjustment(warehouse, stockReason);
  2342 |       },
  2343 |       async () => {
  2344 |         await expect(finance.page.getByRole('row').filter({ hasText: stockReason })).toContainText('Approved by');
  2345 |         await expect(finance.page.getByRole('row').filter({ hasText: rejectedReason })).toHaveCount(0);
  2346 |       },
  2347 |       async () => {
  2348 |         const rows = await readOnlyDatabaseJson<Array<{ status: string; quantity: string; on_hand: string; movement_count: number }>>(`
  2349 |           SELECT adjustment.status, adjustment.quantity::numeric(14, 3)::text AS quantity,
  2350 |                  level.quantity::numeric(14, 3)::text AS on_hand,
  2351 |                  (SELECT COUNT(*)::int FROM stock_movements movement
  2352 |                   WHERE movement.reference_type = 'stock_adjustment' AND movement.reference_id = adjustment.id) AS movement_count
  2353 |           FROM stock_adjustments adjustment
  2354 |           JOIN stock_levels level ON level.item_id = adjustment.item_id AND level.location_id = adjustment.location_id
  2355 |           WHERE adjustment.reason = ${sqlText(stockReason)}
  2356 |         `);
  2357 |         expect(rows).toEqual([{ status: 'approved', quantity: '1.000', on_hand: '1.000', movement_count: 1 }]);
  2358 |       },
  2359 |     );
  2360 | 
  2361 |     await step(
  2362 |       warehouseIdentity.actor,
  2363 |       'Warehouse enters an outbound adjustment above available stock',
  2364 |       'Warehouse → Stock Adjustments → New adjustment',
  2365 |       'not submitted; UI shows available quantity and disables Review adjustment',
  2366 |       warehouse,
  2367 |       async () => {
  2368 |         await openMenuItem(warehouse.page, 'Warehouse', 'Stock Adjustments', '/inventory/stock-adjustments');
  2369 |         await expect(warehouse.page.getByRole('heading', { name: 'Stock Adjustments', exact: true })).toBeVisible();
  2370 |         await warehouse.page.getByRole('link', { name: 'New adjustment', exact: true }).click();
  2371 |         await expect(warehouse.page.getByRole('heading', { name: 'Stock adjustment', exact: true })).toBeVisible();
  2372 |         await warehouse.page.getByRole('combobox', { name: /Direction/ }).selectOption('out');
  2373 |         const itemInput = warehouse.page.getByRole('combobox', { name: 'Item', exact: true });
  2374 |         await itemInput.fill('RM-001');
  2375 |         const itemOption = warehouse.page.getByRole('option').filter({ hasText: 'RM-001' }).first();
  2376 |         await expect(itemOption).toBeVisible();
  2377 |         await itemOption.click();
  2378 |         const location = warehouse.page.getByRole('combobox', { name: /Location/ });
  2379 |         await expect(location).toBeEnabled();
  2380 |         await expect(location).toHaveValue(/Main Warehouse/);
  2381 |         await expect(warehouse.page.getByText(/Filled from the item.s stocked location\./)).toBeVisible();
  2382 |         await warehouse.page.getByRole('spinbutton', { name: /Quantity/ }).fill('1.001');
  2383 |         const reasonCode = warehouse.page.getByRole('combobox', { name: /Reason code/ });
  2384 |         await reasonCode.selectOption({ index: 1 });
  2385 |         await warehouse.page.getByRole('textbox', { name: /Reason \(recorded for audit\)/ }).fill(rejectedReason);
> 2386 |         await expect(warehouse.page.getByText(/Only 1\.000 .* can be removed from this location\./)).toBeVisible();
       |                                                                                                      ^ Error: expect(locator).toBeVisible() failed
  2387 |         await expect(warehouse.page.getByRole('button', { name: 'Review adjustment', exact: true })).toBeDisabled();
  2388 |         await expect(warehouse.page.getByRole('dialog')).toHaveCount(0);
  2389 |         await captureAnnotatedStep(warehouse.page, warehouseIdentity.actor, 'Outbound quantity above stock is blocked in the UI', 3, testInfo);
  2390 |       },
  2391 |       async () => {
  2392 |         await expect(finance.page.getByRole('row').filter({ hasText: rejectedReason })).toHaveCount(0);
  2393 |         await expect(warehouse.page.getByRole('button', { name: 'Review adjustment', exact: true })).toBeDisabled();
  2394 |       },
  2395 |       async () => {
  2396 |         const rows = await readOnlyDatabaseJson<Array<{ on_hand: string; stock_adjustments: number; attempt_adjustments: number; movement_count: number; notification_count: number }>>(`
  2397 |           SELECT level.quantity::numeric(14, 3)::text AS on_hand,
  2398 |                  (SELECT COUNT(*)::int FROM stock_adjustments adjustment WHERE adjustment.reason = ${sqlText(stockReason)}) AS stock_adjustments,
  2399 |                  (SELECT COUNT(*)::int FROM stock_adjustments adjustment WHERE adjustment.reason = ${sqlText(rejectedReason)}) AS attempt_adjustments,
  2400 |                  (SELECT COUNT(*)::int FROM stock_movements movement
  2401 |                   WHERE movement.reference_type = 'stock_adjustment'
  2402 |                     AND movement.reference_id IN (SELECT adjustment.id FROM stock_adjustments adjustment WHERE adjustment.reason IN (${sqlText(stockReason)}, ${sqlText(rejectedReason)}))) AS movement_count,
  2403 |                  (SELECT COUNT(*)::int FROM notifications notice
  2404 |                   WHERE notice.data ->> 'subject_type' = ${sqlText(stockAdjustmentSubjectType)}
  2405 |                     AND notice.data ->> 'subject_id' IN (SELECT adjustment.id::text FROM stock_adjustments adjustment WHERE adjustment.reason IN (${sqlText(stockReason)}, ${sqlText(rejectedReason)}))) AS notification_count
  2406 |           FROM stock_levels level
  2407 |           JOIN stock_adjustments adjustment ON adjustment.item_id = level.item_id AND adjustment.location_id = level.location_id
  2408 |           WHERE adjustment.reason = ${sqlText(stockReason)}
  2409 |         `);
  2410 |         expect(rows).toEqual([{
  2411 |           on_hand: '1.000', stock_adjustments: 1, attempt_adjustments: 0,
  2412 |           movement_count: 1, notification_count: 0,
  2413 |         }]);
  2414 |       },
  2415 |     );
  2416 |   } finally {
  2417 |     for (const { session } of sessions.reverse()) await session.context.close();
  2418 |   }
  2419 | });
  2420 | 
  2421 | 
```