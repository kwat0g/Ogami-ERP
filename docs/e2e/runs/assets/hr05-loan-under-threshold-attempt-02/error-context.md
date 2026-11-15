# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-loans.process.spec.ts >> HR05-LOAN-UNDER-THRESHOLD — Company loan under ₱50,000 skips VP and completes through Finance
- Location: e2e-process/hr-loans.process.spec.ts:652:1

# Error details

```
Error: expect(received).toEqual(expected) // deep equality

- Expected  - 3
+ Received  + 1

- Array [
-   "e2e+department_head@ogami-e2e.test",
- ]
+ Array []
```

# Test source

```ts
  1  | import { expect, type Locator, type Page } from '@playwright/test';
  2  | 
  3  | export async function assertStatusBadge(badge: Locator, expectedStatus: string): Promise<void> {
  4  |   await expect(badge).toHaveText(expectedStatus);
  5  | }
  6  | 
  7  | export async function assertAvailableActions(
  8  |   page: Page,
  9  |   available: string[],
  10 |   unavailable: string[],
  11 | ): Promise<void> {
  12 |   for (const action of available) await expect(page.getByRole('button', { name: action, exact: true })).toBeEnabled();
  13 |   for (const action of unavailable) await expect(page.getByRole('button', { name: action, exact: true })).toHaveCount(0);
  14 | }
  15 | 
  16 | export async function assertActionDisabledWithReason(page: Page, action: string, reason: string): Promise<void> {
  17 |   await expect(page.getByRole('button', { name: action, exact: true })).toBeDisabled();
  18 |   await expect(page.getByText(reason, { exact: false })).toBeVisible();
  19 | }
  20 | 
  21 | export async function assertPhpCurrency(locator: Locator, amount: number): Promise<void> {
  22 |   const expected = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
  23 |   await expect(locator).toContainText(expected);
  24 | }
  25 | 
  26 | export function assertTotalsEqual(label: string, left: number, right: number): void {
  27 |   const leftCents = Math.round((left + Number.EPSILON) * 100);
  28 |   const rightCents = Math.round((right + Number.EPSILON) * 100);
  29 |   expect(leftCents, `${label} totals should match to the centavo`).toBe(rightCents);
  30 | }
  31 | 
  32 | export function assertDocumentNumberUnique(context: Set<string>, documentNumber: string): void {
  33 |   expect(documentNumber, 'document number should be present').not.toBe('');
  34 |   expect(context.has(documentNumber), `duplicate document number: ${documentNumber}`).toBe(false);
  35 |   context.add(documentNumber);
  36 | }
  37 | 
  38 | export async function assertSecondViewerRealtime(locator: Locator, expectedText: string): Promise<void> {
  39 |   await expect(locator, 'the observer screen should update without a refresh').toHaveText(expectedText);
  40 | }
  41 | 
  42 | export async function assertSidebarBadgeCount(locator: Locator, expectedCount: number): Promise<void> {
  43 |   await expect(locator).toHaveText(String(expectedCount));
  44 | }
  45 | 
  46 | export function assertAuditActor(auditEntry: { actor_email?: string; actor?: { email?: string } }, expectedEmail: string): void {
  47 |   expect(auditEntry.actor_email ?? auditEntry.actor?.email).toBe(expectedEmail);
  48 | }
  49 | 
  50 | export function assertRecipientsExactly(actual: string[], expected: string[]): void {
> 51 |   expect([...actual].map((email) => email.toLowerCase()).sort()).toEqual(
     |                                                                  ^ Error: expect(received).toEqual(expected) // deep equality
  52 |     [...expected].map((email) => email.toLowerCase()).sort(),
  53 |   );
  54 | }
  55 | 
```