# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-loans.process.spec.ts >> HR05-CASH-ADVANCE — Employee request advances through Department Head, Finance, and VP before disbursement
- Location: e2e-process/hr-loans.process.spec.ts:232:1

# Error details

```
Error: the observer screen should update without a refresh

expect(locator).toHaveText(expected) failed

Locator: getByRole('row').filter({ hasText: 'CA-202611-0001' }).getByText('Active', { exact: true })
Expected: "Active"
Timeout: 8000ms
Error: element(s) not found

Call log:
  - the observer screen should update without a refresh getByRole('row').filter({ hasText: 'CA-202611-0001' }).getByText('Active', { exact: true }) with timeout 8000ms
  - waiting for getByRole('row').filter({ hasText: 'CA-202611-0001' }).getByText('Active', { exact: true })

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
    - link "Self-service":
      - /url: /self-service
    - img
    - text: Loans
  - button "Search… ⌘K":
    - img
    - text: Search… ⌘K
  - button "Toggle theme":
    - img
  - button "Notifications, 1 unread":
    - img
  - 'status "Real-time updates: Live. Last updated 9:49 PM"': Live
  - button "Keyboard shortcuts":
    - img
  - button "Profile menu for E2E employee":
    - img "E2E employee": EE
    - text: E2E employee
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
          - link "Approval Queue":
            - /url: /approvals
            - img
            - text: Approval Queue
        - listitem:
          - link "Notifications":
            - /url: /notifications
            - img
            - text: Notifications
- main:
  - heading "Loans & Cash Advances" [level=1]
  - text: 1 active · 0 past
  - button "Apply for a loan" [disabled]:
    - img
    - text: Apply for a loan
  - status: You already have a pending or active loan or cash advance. No new request is allowed until it is closed.
  - region "Active loans":
    - heading "Active · 1" [level=2]
    - text: 1 result
    - button "Customize columns":
      - img
      - text: Columns
    - radiogroup "Table density":
      - radio "Compact" [checked]
      - radio "Default"
      - radio "Spacious"
    - table:
      - rowgroup:
        - row "Type Type column width Principal Principal column width Outstanding Outstanding column width Amortization Amortization column width Periods Periods column width Filed Filed column width Status Status column width Actions Actions column width":
          - columnheader "Type Type column width":
            - text: Type
            - separator "Type column width"
          - columnheader "Principal Principal column width":
            - text: Principal
            - separator "Principal column width"
          - columnheader "Outstanding Outstanding column width":
            - text: Outstanding
            - separator "Outstanding column width"
          - columnheader "Amortization Amortization column width":
            - text: Amortization
            - separator "Amortization column width"
          - columnheader "Periods Periods column width":
            - text: Periods
            - separator "Periods column width"
          - columnheader "Filed Filed column width":
            - text: Filed
            - separator "Filed column width"
          - columnheader "Status Status column width":
            - text: Status
            - separator "Status column width"
          - columnheader "Actions Actions column width":
            - text: Actions
            - separator "Actions column width"
      - rowgroup:
        - row "Cash Advance ₱1,000.00 ₱1,000.00 ₱500.00 0/2 months paid Nov 4, 2026 Active":
          - cell "Cash Advance"
          - cell "₱1,000.00"
          - cell "₱1,000.00"
          - cell "₱500.00"
          - cell "0/2 months paid"
          - cell "Nov 4, 2026"
          - cell "Active"
          - cell
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
> 39 |   await expect(locator, 'the observer screen should update without a refresh').toHaveText(expectedText);
     |                                                                                ^ Error: the observer screen should update without a refresh
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
  51 |   expect([...actual].map((email) => email.toLowerCase()).sort()).toEqual(
  52 |     [...expected].map((email) => email.toLowerCase()).sort(),
  53 |   );
  54 | }
  55 | 
```