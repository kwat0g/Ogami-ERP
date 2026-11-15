# Demo inventory count scenario

## Goal

Add one deterministic inventory scenario parked in the current application's stock-count workflow: an active count session with one recorded variance and remaining uncounted lines, ready to continue in the UI.

## Current-code constraints

- Use `StockCountService::createSession()`, `startSession()`, and `recordCount()` for every count transition.
- Use the seeded warehouse actor and current zone/item/stock rows. Do not insert scenario transaction rows from the seeder.
- Keep the scenario inside the demo layer registry with its `opening-stock-balances` and `workforce-identities` dependencies.
- Create and transition records at a stable Asia/Manila timestamp derived from `--as-of`, backing up to the previous business day if as-of is not a business day.
- Use a stable scenario marker in the session title and validate state on replay so drift is reported instead of silently repaired.
- Suppress notifications for historical setup, but retain normal notifications if the services emit any for current live work; verify service behavior first.

## Acceptance checks

- A focused feature test fails before the layer is registered, then passes after implementation.
- The seeded session is `in_progress`, the chosen count line is `counted` with a non-zero variance, and other stock lines remain `pending`.
- Session and line timestamps are controlled by the as-of clock; the actor is the active warehouse staff demo user.
- A second seed run leaves session, lines, audit rows, and stock balances unchanged.
- Run the focused Laravel feature test, Pint, and PHPStan for changed PHP files. Do not run the full PHP suite.

## Scope

One inventory count scenario and its documentation/coverage entry. Approval thresholds remain governed by the current settings and unresolved Phase 1 policy decision; this slice does not change them or claim a pending high-value adjustment.
