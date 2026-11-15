# INV-01 third recorded process run

**Date:** 2026-11-03 UTC  
**Environment:** Dedicated `ogami-erp-e2e` Compose project; database `ogami_e2e`  
**Process:** INV-01 — high-value inbound warehouse adjustment and Finance approval  
**Result:** Failed before transaction creation because the first sidebar-locator correction required a visible text node; the compact Warehouse sidebar exposes the label as an accessible name instead.

## Run result

Playwright ran headless Chromium with one worker and zero retries. E2E TypeScript checking passed. Real UI login setup passed for **35/35** actors. The business journey failed at the first step while navigating from the Warehouse dashboard to Stock Adjustments. No adjustment was submitted; the remaining two steps were blocked. The run returned exit code 1 with an explicit locator failure.

The test locator issue from the second attempt had two display cases: live badges alter expanded-link accessible names, and compact sidebars hide label text while keeping the link's `aria-label`. The helper now uses a role locator anchored to the exact label followed only by a word boundary, comma, or end of name. This covers both display cases without matching a longer label prefix.

## Steps

| # | Actor | UI path / action | Result |
|---:|---|---|---|
| 1 | `warehouse_staff` | Dashboard → Warehouse → Stock Adjustments | Failed before entering the adjustment form. The E2E locator could not find the link after opening the Warehouse section because it depended on a visible text node absent from the compact sidebar. No transactional action occurred. |
| 2 | `finance_officer` | Work → Notifications → matching notice | Blocked. |
| 3 | `finance_officer` | Linked adjustment row → Approve | Blocked. |

An earlier invocation in this period emitted the Playwright start line but produced no fresh last-run summary or process artifact. A read-only database check found **0** adjustments afterward, so it is not counted as a pass or failed journey. The next run used a captured exit status and explicit failure output.

## Classification and evidence

- **Classification:** Test issue, same root cause as [TST-INV-002](../FINDINGS.md#test-and-environment-issues); no product defect was observed.
- Playwright output: locator `getByRole('link').filter({ has: getByText('Stock Adjustments', { exact: true }) })` timed out in `openMenuItem` before form interaction.
- No API or database mutation was used. The E2E baseline remained without adjustment records.
- The responsive-sidebar locator correction is in test code; a new clean-baseline journey run must confirm both navigation cases.

