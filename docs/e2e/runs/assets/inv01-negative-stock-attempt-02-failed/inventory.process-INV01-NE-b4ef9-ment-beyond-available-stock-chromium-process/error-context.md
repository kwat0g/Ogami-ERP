# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: inventory.process.spec.ts >> INV01-NEGATIVE-STOCK — Warehouse cannot submit an outbound adjustment beyond available stock
- Location: e2e-process/inventory.process.spec.ts:2264:1

# Error details

```
Error: expect(locator).toHaveCount(expected) failed

Locator:  getByRole('combobox', { name: /Location/ }).locator('option').filter({ hasText: 'Main Warehouse' }).first()
Expected: 1
Received: 0
Timeout:  8000ms

Call log:
  - Expect "toHaveCount" getByRole('combobox', { name: /Location/ }).locator('option').filter({ hasText: 'Main Warehouse' }).first() with timeout 8000ms
  - waiting for getByRole('combobox', { name: /Location/ }).locator('option').filter({ hasText: 'Main Warehouse' }).first()
    20 × locator resolved to 0 elements
       - unexpected value "0"

```

# Test source

```ts
  2281 |     { name: 'Warehouse enters an outbound adjustment above available stock', actor: warehouseIdentity.actor, uiPath: 'Warehouse → Stock Adjustments → New adjustment', expectedStatus: 'not submitted; UI shows available quantity and disables Review adjustment' },
  2282 |   ];
  2283 |   const sessions: Array<{ actor: string; session: ActorPage }> = [];
  2284 |   const openActor = async (actor: string): Promise<ActorPage> => {
  2285 |     const session = await actorPage(browser, actor);
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
  2380 |         const source = location.locator('option').filter({ hasText: 'Main Warehouse' }).first();
> 2381 |         await expect(source).toHaveCount(1);
       |                              ^ Error: expect(locator).toHaveCount(expected) failed
  2382 |         await location.selectOption((await source.getAttribute('value'))!);
  2383 |         await warehouse.page.getByRole('spinbutton', { name: /Quantity/ }).fill('1.001');
  2384 |         const reasonCode = warehouse.page.getByRole('combobox', { name: /Reason code/ });
  2385 |         await reasonCode.selectOption({ index: 1 });
  2386 |         await warehouse.page.getByRole('textbox', { name: /Reason \(recorded for audit\)/ }).fill(rejectedReason);
  2387 |         await expect(warehouse.page.getByText(/Only 1\.000 .* can be removed from this location\./)).toBeVisible();
  2388 |         await expect(warehouse.page.getByRole('button', { name: 'Review adjustment', exact: true })).toBeDisabled();
  2389 |         await expect(warehouse.page.getByRole('dialog')).toHaveCount(0);
  2390 |         await captureAnnotatedStep(warehouse.page, warehouseIdentity.actor, 'Outbound quantity above stock is blocked in the UI', 3, testInfo);
  2391 |       },
  2392 |       async () => {
  2393 |         await expect(finance.page.getByRole('row').filter({ hasText: rejectedReason })).toHaveCount(0);
  2394 |         await expect(warehouse.page.getByRole('button', { name: 'Review adjustment', exact: true })).toBeDisabled();
  2395 |       },
  2396 |       async () => {
  2397 |         const rows = await readOnlyDatabaseJson<Array<{ on_hand: string; stock_adjustments: number; attempt_adjustments: number; movement_count: number; notification_count: number }>>(`
  2398 |           SELECT level.quantity::numeric(14, 3)::text AS on_hand,
  2399 |                  (SELECT COUNT(*)::int FROM stock_adjustments adjustment WHERE adjustment.reason = ${sqlText(stockReason)}) AS stock_adjustments,
  2400 |                  (SELECT COUNT(*)::int FROM stock_adjustments adjustment WHERE adjustment.reason = ${sqlText(rejectedReason)}) AS attempt_adjustments,
  2401 |                  (SELECT COUNT(*)::int FROM stock_movements movement
  2402 |                   WHERE movement.reference_type = 'stock_adjustment'
  2403 |                     AND movement.reference_id IN (SELECT adjustment.id FROM stock_adjustments adjustment WHERE adjustment.reason IN (${sqlText(stockReason)}, ${sqlText(rejectedReason)}))) AS movement_count,
  2404 |                  (SELECT COUNT(*)::int FROM notifications notice
  2405 |                   WHERE notice.data ->> 'subject_type' = ${sqlText(stockAdjustmentSubjectType)}
  2406 |                     AND notice.data ->> 'subject_id' IN (SELECT adjustment.id::text FROM stock_adjustments adjustment WHERE adjustment.reason IN (${sqlText(stockReason)}, ${sqlText(rejectedReason)}))) AS notification_count
  2407 |           FROM stock_levels level
  2408 |           JOIN stock_adjustments adjustment ON adjustment.item_id = level.item_id AND adjustment.location_id = level.location_id
  2409 |           WHERE adjustment.reason = ${sqlText(stockReason)}
  2410 |         `);
  2411 |         expect(rows).toEqual([{
  2412 |           on_hand: '1.000', stock_adjustments: 1, attempt_adjustments: 0,
  2413 |           movement_count: 1, notification_count: 0,
  2414 |         }]);
  2415 |       },
  2416 |     );
  2417 |   } finally {
  2418 |     for (const { session } of sessions.reverse()) await session.context.close();
  2419 |   }
  2420 | });
  2421 | 
  2422 | 
```