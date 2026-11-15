# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: inventory.process.spec.ts >> INV01-TRANSFER — Warehouse creates and another Warehouse user executes a location transfer
- Location: e2e-process/inventory.process.spec.ts:1812:1

# Error details

```
Error: expect(locator).toContainText(expected) failed

Locator: getByRole('status')
Expected substring: "Another warehouse user must execute a transfer you created."
Error: strict mode violation: getByRole('status') resolved to 4 elements:
    1) <span role="status" aria-live="polite" title="Last updated 3:39 AM" aria-label="Real-time updates: Reconnecting. Last updated 3:39 AM" class="inline-flex h-7 items-center gap-1.5 whitespace-nowrap rounded-md px-2 text-xs text-secondary">…</span> aka getByRole('status', { name: 'Real-time updates:' })
    2) <p role="status" class="text-caption text-muted self-center">Another warehouse user must execute a transfer yo…</p> aka getByText('Another warehouse user must')
    3) <div role="status" aria-live="polite" class="relative group">…</div> aka getByRole('status').filter({ hasText: 'Transfer order created.' })
    4) <div role="status" aria-live="polite" class="relative group">…</div> aka getByRole('status').filter({ hasText: 'Low Stock — RM-' })

Call log:
  - Expect "toContainText" getByRole('status') with timeout 8000ms
  - waiting for getByRole('status')

```

# Test source

```ts
  1870 | 
  1871 |   try {
  1872 |     await step(
  1873 |       observerIdentity.actor,
  1874 |       'Observer opens Inventory Transfers before work exists',
  1875 |       'Warehouse → Inventory Transfers',
  1876 |       'no transfer',
  1877 |       observer,
  1878 |       async () => {
  1879 |         await openTransfers(observer);
  1880 |         await expect(observer.page.getByRole('heading', { name: 'No transfer orders', exact: true })).toBeVisible();
  1881 |       },
  1882 |       async () => {
  1883 |         const rows = await readOnlyDatabaseJson<Array<{ count: number }>>(`
  1884 |           SELECT COUNT(*)::int AS count FROM transfer_orders WHERE reason = ${sqlText(reason)}
  1885 |         `);
  1886 |         expect(rows).toEqual([{ count: 0 }]);
  1887 |       },
  1888 |       async () => await expect(observer.page.getByRole('heading', { name: 'No transfer orders', exact: true })).toBeVisible(),
  1889 |     );
  1890 | 
  1891 |     await step(
  1892 |       makerIdentity.actor,
  1893 |       'Warehouse posts 1 kg as a UI-created stock prerequisite',
  1894 |       'Warehouse → Stock Adjustments → New adjustment → Submit',
  1895 |       'approved; source has 1 kg',
  1896 |       maker,
  1897 |       async () => {
  1898 |         await openInternalHome(maker);
  1899 |         await submitLowValueInboundAdjustment(maker, stockReason);
  1900 |       },
  1901 |       async () => await expect(maker.page.getByRole('row').filter({ hasText: stockReason })).toContainText('Approved by'),
  1902 |       async () => {
  1903 |         const rows = await readOnlyDatabaseJson<Array<{ quantity: string; movement_count: number; source_code: string }>>(`
  1904 |           SELECT COALESCE(level.quantity, 0)::numeric(14, 3)::text AS quantity,
  1905 |                  (SELECT COUNT(*)::int FROM stock_movements movement
  1906 |                   WHERE movement.reference_type = 'stock_adjustment'
  1907 |                     AND movement.reference_id = adjustment.id) AS movement_count,
  1908 |                  location.code AS source_code
  1909 |           FROM stock_adjustments adjustment
  1910 |           JOIN warehouse_locations location ON location.id = adjustment.location_id
  1911 |           LEFT JOIN stock_levels level ON level.item_id = adjustment.item_id AND level.location_id = adjustment.location_id
  1912 |           WHERE adjustment.reason = ${sqlText(stockReason)}
  1913 |         `);
  1914 |         expect(rows).toHaveLength(1);
  1915 |         expect(rows[0]).toMatchObject({ quantity: '1.000', movement_count: 1 });
  1916 |         expect(rows[0].source_code).toBeTruthy();
  1917 |         journey.values.sourceCode = rows[0].source_code;
  1918 |       },
  1919 |     );
  1920 | 
  1921 |     await step(
  1922 |       makerIdentity.actor,
  1923 |       'Warehouse maker creates a pending transfer order',
  1924 |       'Warehouse → Inventory Transfers → New transfer → Create transfer order',
  1925 |       'pending; maker cannot execute',
  1926 |       maker,
  1927 |       async () => {
  1928 |         await openTransfers(maker);
  1929 |         await maker.page.getByRole('button', { name: 'New transfer', exact: true }).click();
  1930 |         const modal = maker.page.getByRole('dialog');
  1931 |         await expect(modal.getByRole('heading', { name: 'New transfer order', exact: true })).toBeVisible();
  1932 |         const item = modal.getByRole('combobox', { name: 'Item', exact: true });
  1933 |         await item.fill('RM-001');
  1934 |         // SearchableSelect renders its listbox into a document-level portal.
  1935 |         const itemOption = maker.page.getByRole('option').filter({ hasText: 'RM-001' }).first();
  1936 |         await expect(itemOption).toBeVisible();
  1937 |         await itemOption.click();
  1938 | 
  1939 |         const source = modal.getByRole('combobox', { name: 'From location', exact: true });
  1940 |         await expect(source).toBeEnabled();
  1941 |         const sourceOption = source.locator('option').filter({ hasText: '1 kg available' }).first();
  1942 |         await expect(sourceOption).toHaveCount(1);
  1943 |         const sourceId = await sourceOption.getAttribute('value');
  1944 |         expect(sourceId).toBeTruthy();
  1945 |         await source.selectOption(sourceId!);
  1946 | 
  1947 |         const destination = modal.getByRole('combobox', { name: 'To location', exact: true });
  1948 |         await expect(destination).toBeEnabled();
  1949 |         const destinationId = await destination.locator('option').nth(1).getAttribute('value');
  1950 |         expect(destinationId, 'E2E baseline must expose a second active stock destination').toBeTruthy();
  1951 |         journey.values.destinationId = destinationId;
  1952 |         await destination.selectOption(destinationId!);
  1953 |         await modal.getByRole('spinbutton', { name: 'Quantity', exact: true }).fill('0.500');
  1954 |         await modal.getByRole('textbox', { name: 'Reason (optional)', exact: true }).fill(reason);
  1955 |         const create = modal.getByRole('button', { name: 'Create transfer order', exact: true });
  1956 |         await expect(create).toBeEnabled();
  1957 |         const responsePromise = maker.page.waitForResponse((response) => {
  1958 |           const url = new URL(response.url());
  1959 |           return response.request().method() === 'POST' && url.pathname === '/api/v1/inventory/transfer-orders';
  1960 |         }, { timeout: 12_000 });
  1961 |         await create.click();
  1962 |         const response = await responsePromise;
  1963 |         expect(response.status(), 'transfer order creation should be accepted by the API').toBe(201);
  1964 |         const payload = await response.json() as { data?: { id?: string; transfer_number?: string } };
  1965 |         expect(payload.data?.id, 'UI creation response should identify its transfer order').toBeTruthy();
  1966 |         expect(payload.data?.transfer_number).toMatch(/^TO-/);
  1967 |         journey.values.transferId = payload.data?.id;
  1968 |         journey.values.transferNumber = payload.data?.transfer_number;
  1969 |         await expect(maker.page.getByRole('region', { name: 'Transfer details' })).toContainText('Pending');
> 1970 |         await expect(maker.page.getByRole('status')).toContainText('Another warehouse user must execute a transfer you created.');
       |                                                      ^ Error: expect(locator).toContainText(expected) failed
  1971 |         await expect(maker.page.getByRole('button', { name: 'Execute', exact: true })).toHaveCount(0);
  1972 |       },
  1973 |       async () => {
  1974 |         const transferNumber = contextValue<string>(journey, 'transferNumber');
  1975 |         await selectTransfer(observer, transferNumber);
  1976 |         const details = observer.page.getByRole('region', { name: 'Transfer details' });
  1977 |         await expect(details).toContainText('Pending');
  1978 |         await expect(details).toContainText('RM-001');
  1979 |         await expect(details).toContainText('0.5 kg');
  1980 |         await captureAnnotatedStep(observer.page, observerIdentity.actor, 'Observer finds the pending transfer live', 3, testInfo);
  1981 |       },
  1982 |       async () => {
  1983 |         const state = await transferState(journey);
  1984 |         expect(state).toMatchObject({
  1985 |           status: 'pending', quantity: '0.500', reason,
  1986 |           creator_email: makerIdentity.email, transferrer_email: null,
  1987 |           source_on_hand: '1.000', destination_on_hand: '0.000', movement_count: 0,
  1988 |           movement_type: null, movement_actor_email: null,
  1989 |         });
  1990 |         await assertTransferAuditActors(journey, [makerIdentity.email]);
  1991 |       },
  1992 |     );
  1993 | 
  1994 |     await step(
  1995 |       executorIdentity.actor,
  1996 |       'Independent Warehouse executor finds and executes the transfer',
  1997 |       'Warehouse → Inventory Transfers → select pending transfer → Execute',
  1998 |       'transferred; balances moved once',
  1999 |       executor,
  2000 |       async () => {
  2001 |         await openTransfers(executor);
  2002 |         const transferNumber = contextValue<string>(journey, 'transferNumber');
  2003 |         await selectTransfer(executor, transferNumber);
  2004 |         const details = executor.page.getByRole('region', { name: 'Transfer details' });
  2005 |         await expect(details.getByRole('button', { name: 'Execute', exact: true })).toBeEnabled();
  2006 |         await expect(details.getByRole('button', { name: 'Cancel', exact: true })).toBeEnabled();
  2007 |         await details.getByRole('button', { name: 'Execute', exact: true }).click();
  2008 |         const confirmation = executor.page.getByRole('dialog');
  2009 |         await expect(confirmation.getByRole('heading', { name: 'Execute transfer?', exact: true })).toBeVisible();
  2010 |         await expect(confirmation).toContainText('0.5 kg');
  2011 |         await expect(confirmation).toContainText('A stock movement will be recorded.');
  2012 |         const execute = confirmation.getByRole('button', { name: 'Execute', exact: true });
  2013 |         await expect(execute).toBeEnabled();
  2014 |         const responsePromise = executor.page.waitForResponse((response) => {
  2015 |           const url = new URL(response.url());
  2016 |           return response.request().method() === 'POST'
  2017 |             && url.pathname.endsWith(`/inventory/transfer-orders/${contextValue<string>(journey, 'transferId')}/execute`);
  2018 |         }, { timeout: 12_000 });
  2019 |         await execute.click();
  2020 |         const response = await responsePromise;
  2021 |         expect(response.status(), 'transfer execution should be accepted by the API').toBe(200);
  2022 |         await expect(details).toContainText('Transferred');
  2023 |         await expect(details.getByRole('button', { name: 'Execute', exact: true })).toHaveCount(0);
  2024 |         await expect(details.getByRole('button', { name: 'Cancel', exact: true })).toHaveCount(0);
  2025 |       },
  2026 |       async () => {
  2027 |         const details = observer.page.getByRole('region', { name: 'Transfer details' });
  2028 |         await expect(details, 'observer should receive the transfer status without refresh').toContainText('Transferred');
  2029 |         await expect(details).toContainText('Transferred by');
  2030 |         await expect(details.getByRole('button', { name: 'Execute', exact: true })).toHaveCount(0);
  2031 |         await expect(details.getByRole('button', { name: 'Cancel', exact: true })).toHaveCount(0);
  2032 |         await captureAnnotatedStep(observer.page, observerIdentity.actor, 'Observer confirms transfer and actions close live', 4, testInfo);
  2033 |       },
  2034 |       async () => {
  2035 |         const state = await transferState(journey);
  2036 |         expect(state).toMatchObject({
  2037 |           status: 'transferred', quantity: '0.500', reason,
  2038 |           creator_email: makerIdentity.email, transferrer_email: executorIdentity.email,
  2039 |           source_on_hand: '0.500', destination_on_hand: '0.500', movement_count: 1,
  2040 |           movement_type: 'transfer', movement_quantity: '0.500',
  2041 |           movement_unit_cost: '120.0000', movement_total_cost: '60.00',
  2042 |           movement_actor_email: executorIdentity.email,
  2043 |         });
  2044 |         expect(state.source_code).toBe(contextValue<string>(journey, 'sourceCode'));
  2045 |         await assertTransferAuditActors(journey, [makerIdentity.email, executorIdentity.email]);
  2046 |       },
  2047 |     );
  2048 |   } finally {
  2049 |     for (const { session } of sessions.reverse()) await session.context.close();
  2050 |   }
  2051 | });
  2052 | 
  2053 | 
```