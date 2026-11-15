# Phase 2 — E2E platform

**Updated:** 2026-11-05 UTC  
**State:** Platform implemented and smoke-checked. The environment guard identity remains `ogami-erp-e2e`; the runner uses the fixed runtime label `ogami-erp-e2e-session` after repeated shutdowns under the original container label. The dedicated E2E network and explicitly named disposable volumes are unchanged. The root cause remains open under ENV-E2E-002. The first procurement journey is authored and has one partial failed run; see the [run report](runs/2026-11-03-PR01-first-attempt.md).  
**Catalog:** [01-process-catalog.md](01-process-catalog.md) was approved before this work.

## What is in place

`docker-compose.e2e.yml` defines a dedicated internal Compose network and named data volumes. The PHP source is mounted read-only; only E2E storage and bootstrap cache are writable. The E2E database is `ogami_e2e`; the shared development Compose project and its database were not used.

| Service | Purpose | Memory cap |
|---|---|---:|
| `db` | PostgreSQL 16; dedicated E2E database and read-only verifier role | 384 MiB |
| `api` | Laravel 11 / PHP 8.3 API | 512 MiB |
| `spa` | E2E image builds the checked-out React source with Vite and serves the production bundle | 256 MiB |
| `redis`, `redis-cache` | Separate queue and cache stores | 128 / 64 MiB |
| `reverb` | Laravel Reverb websocket server | 96 MiB |
| `queue-main`, `queue-broadcasts`, `queue-dss` | Dedicated workers for the configured queues | 96 MiB each |
| `scheduler` | Idle by default; scheduled tasks run only through the guarded `runner schedule` command | 96 MiB |
| `nginx` | Private browser entry point and API proxy | 96 MiB |
| `mailpit` | Isolated SMTP capture and message API | 64 MiB |
| `playwright` | Headless Chromium and read-only verification tools; one worker | 1280 MiB; 256 MiB shared memory |

Only Nginx and Mailpit have host-loopback bindings, on ports 18080/18443 and 18025. The browser runs inside Compose against `https://nginx`; its environment guard rejects other origins. In this Docker environment, host access to the loopback bindings could not be verified. Internal browser-to-Nginx access was verified by the UI sign-in run.

The SPA uses a production preview because Vite's development dependency scan exceeded the E2E memory budget and unoptimized icon modules caused Chromium to crash. `spa/vite.config.ts` permits the exact `nginx` preview host only when `E2E_ENV=true`. This is flagged E2E-only tooling; ordinary dev and production builds retain Vite's default host checks. Vite's [preview host allowlist](https://vite.dev/config/preview-options) is the mechanism used here.

The first multi-context stock-count run produced a Docker `oom` event for the Playwright container at its 768 MB cap while Chromium captured a step screenshot with the acting user and live observer pages open. The runner cap is now 1280 MB. All other service caps are unchanged; configured E2E service limits total 2480 MiB, including the Playwright runner. The browser remains headless, single-worker, with video disabled and failure-only traces/screenshots.

The image build runs `npx vite build` without the repository's full-project `tsc -b` phase: that phase exhausted a 1 GiB Node heap before bundling. The E2E TypeScript project is separately checked before each Playwright run. The full application TypeScript build has not been verified in this environment.

The guarded runner now starts the stack detached with `up`, checks all 12 long-running services through `wait`, and requires a healthy stack for `seed` and `test`. The journey command reuses the checked-out stack and only builds the isolated Playwright image; it does not rebuild the application stack for each run. This keeps startup/readiness polling separate from the bounded single-journey command. Unit coverage for the environment guard, transient Redis reset, and Compose readiness helper is **14/14 passed**.

## Environment guard and baseline

`scripts/e2e/runner.mjs` validates `.env.e2e` before operational commands. The guard requires the explicit E2E flag, `APP_ENV=testing`, exact database/project identifiers, PostgreSQL host `db`, and an allowlisted base URL. The generated `.env.e2e` is ignored by Git and written with mode `0600`; its generated E2E-only credentials were rotated after setup diagnostics. Do not display or commit that file.

`E2EAccountSeeder` creates reference/master data and a run-independent actor manifest, not process transactions. The current manifest contains **37 actors**: 35 internal user accounts across the 17 seeded roles, plus customer and supplier portal actors. Two additional `warehouse_staff` accounts provide distinct count-verifier and session-completer identities. The current baseline has 37 actors, 17 roles, and no purchase requests, purchase orders, sales orders, or invoices. It includes opening leave entitlements for the seeded `employee` actor, copied from active leave types' `default_balance` into the current Asia/Manila year; these are reference baseline balances, not leave requests or approvals. The read-only DB role is transaction-read-only. The new actors completed real-UI sign-in in the count-variance journey; a full 37-actor login run remains pending.

`E2EAccountSeeder` also calls the separately gated `E2EReferenceDataSeeder`. That seeder provides one active WB-001 inspection specification with one visual acceptance criterion so the stock-production UI can exercise its real quality hand-offs. It records the fixture as E2E-only reference data, attributed to the seeded Quality Inspector. It creates no stock levels, reservations, inspections, work orders, or other transactional records. The QC specification editor cannot publish a measurement specification in this baseline while both checklist-capture feature flags are disabled; that UI/config limitation is recorded for review, and this fixture does not attempt to test spec authoring. The required opening material balance remains a Warehouse UI stock adjustment within the production journey.

The E2E template explicitly sets the Philippine company policy values needed by the app-wide policy endpoint: VAT Registered, 12% VAT, and PHP functional currency. The E2E account seeder also sets `inventory.adjustment_approval_threshold` to ₱25,000 so the cataloged high-value maker-checker path is reachable; the product's general seed default is ₱0 (approval gate disabled). Both are isolated E2E reference settings documented here, not application behavior changes. The first UI attempt exposed the required blank `company.vat_status` setting as a 422; this was corrected in E2E baseline configuration, not application business logic. The default is documented for owner review.

## Commands

Run from the repository root. `config` is read-only; `up` builds and detaches the isolated stack, and `wait` polls Docker health without holding a Compose `up --wait` process open; `seed` requires a healthy stack, clears transient Redis state only in the guarded E2E queue/cache services, then resets the dedicated E2E schema. `test` seeds a fresh baseline and runs the selected process project with its real-UI auth dependency. `test-split` also starts from a fresh baseline, but runs selected-role UI auth separately, restarts the disposable Compose services while preserving volumes, then runs one exact journey ID without rerunning auth. Use it when the environment stops the stack during a combined invocation; this mitigates ENV-E2E-002 but does not fix its unknown cause. `test-split` requires `E2E_AUTH_ACTORS` to name the actors needed by the selected journey. After changing application source or Compose configuration, run `up` again before `wait`, `seed`, and tests. `reset` removes and recreates only this E2E project's volumes; `rotate` also regenerates its disposable credentials.

```sh
node scripts/e2e/runner.mjs init
node scripts/e2e/runner.mjs config
node scripts/e2e/runner.mjs up
node scripts/e2e/runner.mjs wait
node scripts/e2e/runner.mjs test
node scripts/e2e/runner.mjs test --grep "INV01-HIGH-VALUE-APPROVAL"
E2E_AUTH_ACTORS=hr_officer,system_admin node scripts/e2e/runner.mjs test-split --grep HR02-HAPPY
node scripts/e2e/runner.mjs clock 2026-04-01T08:00:00+08:00
node scripts/e2e/runner.mjs clock reset
node scripts/e2e/runner.mjs schedule
node scripts/e2e/runner.mjs drain redis default
node scripts/e2e/runner.mjs reset
node scripts/e2e/runner.mjs down
node scripts/e2e/runner.mjs destroy
```

`test` and `test-split` start every journey invocation from a fresh E2E baseline; run `seed` separately only when inspecting the baseline without starting a journey. Journey selectors are exact catalog IDs, so one guarded invocation cannot use a broad regular expression to start multiple journeys.

`up` intentionally detaches without waiting for health. Follow it with `wait`; that command checks all 12 long-running E2E services until they report running and healthy (or the configured wait limit expires). `test` requires the dedicated services to be running and healthy, then clears only E2E Redis state, recreates the isolated schema, seeds reference data and E2E actors, and holds the runner lock through TypeScript checking and Playwright completion. `test-split` holds the same lock through baseline reset, typecheck, selected actor login, a volume-preserving service restart, and one journey. All runner commands that can mutate or act on E2E services share a nonblocking project lock in the system temp directory and refuse to overlap. A selected journey runs on Chromium with one worker and no retries. `seed` remains available for baseline inspection outside a journey.

Operational Compose calls should go through `scripts/e2e/runner.mjs`. If a read-only direct inspection is necessary, always pass `-p ogami-erp-e2e-session --env-file .env.e2e -f docker-compose.e2e.yml` and confirm the container name contains `ogami-erp-e2e-session` before using `exec`; omitting the project name selects the default `ogami-erp` project. The attempt-09 diagnostic mistake and its failed, non-mutating connection attempts are recorded as [ENV-E2E-005](FINDINGS.md#env-e2e-005--manual-compose-diagnostics-omitted-the-dedicated-e2e-project-name).

Clock, scheduler, and queue controls are gated by the dedicated E2E configuration. The clock HTTP endpoint also requires `X-E2E-Control`. Freeze/read/clear and unauthenticated rejection were checked in the platform run; `HR03-PUNCH` also used the gated clock to simulate the Manila work date and shift end. The scheduler container idles by default; `node scripts/e2e/runner.mjs schedule` invokes the E2E-guarded `e2e:run-scheduled` command for deterministic one-shot scheduler checks. This prevents recurring jobs such as stock-alert emails from racing journey assertions. Scheduled-task effects, queue draining behavior, queue recovery, and websocket recovery remain unverified.

The E2E clock rebases database-backed session `last_activity` timestamps whenever it freezes or clears time. Without this E2E-only synchronization, Laravel compares a simulated Carbon time against a session timestamp written using the real clock and expires the authenticated browser session. The support is guarded by `E2E_ENV=true` plus the dedicated E2E environment assertion in `api/app/Common/Support/E2EClock.php`; `api/tests/Unit/E2E/E2EClockTest.php` covers the freeze/clear behavior. Its focused PHP run exited successfully with 17 assertions; PHPUnit reported a result-cache warning because the API source/vendor mount is read-only. This is E2E infrastructure and does not alter process behavior outside the isolated environment.

Several guarded runs, including `HR02-HAPPY` attempt 12, were interrupted when the dedicated services received stop signals without an OOM indication. A later guarded recovery was stopped again about 2m54s after `up`, while read-only verification ran. The initiating process remains unknown. `test-split` runs a journey after a new service start while preserving its freshly seeded database and actor storage states; it does not claim to resolve [ENV-E2E-002](FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated). Still run only one guarded journey at a time with one worker and no retries.

## Playwright framework

`spa/playwright.process.config.ts` sets headless Chromium, `workers: 1`, `fullyParallel: false`, serial execution, retries `0`, trace and screenshot retention on failure, video off, and HTML/JSON reporters. The desktop process and tablet/floor projects depend on `auth-setup`; `platform-checks` runs after setup. The custom runner, step screenshots, diagnostics, read-only API/DB helpers, Mailpit reader, PDF text helper, clock helper, negative authorization helper, and invariant scaffolding are under `spa/e2e-process/`.

The E2E Nginx server terminates HTTPS on its private Compose network using a self-signed certificate generated into the disposable `e2e_tls` volume by `e2e-tls-init`. Playwright accepts that test certificate only in its isolated contexts; the platform check asserts `window.isSecureContext` and `crypto.randomUUID` before journeys. Browser API calls, secure session cookies, and Reverb WebSockets use the same HTTPS origin. This is needed because the stock-adjustment form creates its idempotency key with `crypto.randomUUID()` before dispatching the API request.

The auth setup signs in each actor through the real `/sign-in` UI in a separate context and writes ignored `.auth` storage state. Historical full setup signed in all 35 actors present then. The current manifest contains 37 actors; full login against this updated manifest remains pending. For a single journey after an interrupted setup, `E2E_AUTH_ACTORS` can select a comma-separated subset of manifest actor names; each selected actor still signs in through the real UI, and unknown or duplicate names fail setup. The guarded runner honors a shell-provided selector over the file's blank default while retaining all other environment identity and credentials from `.env.e2e`. A focused runner test covers this narrow override. A precise console-noise entry permits the expected unauthenticated `GET /api/v1/auth/user` response on a fresh login context. Other console errors, uncaught page errors, failed requests, and HTTP 5xx responses still fail the step.

After either guarded Playwright test command, the runner sanitizes and then checks `spa/test-results/process` before returning. The standard-library utility [`scripts/e2e/sanitize_traces.py`](../../scripts/e2e/sanitize_traces.py) supports trace ZIPs and loose trace/network/JSON files, redacts sensitive fields and recognizable credential strings, and prints paths/counts only. Its CLI accepts only `docs/e2e/runs/`, `spa/test-results/process/`, and `spa/playwright-report/process/`. It preserves non-text trace resources such as screenshots. To check or sanitize archived evidence manually, run `python3 scripts/e2e/sanitize_traces.py --sanitize docs/e2e/runs/assets` followed by `python3 scripts/e2e/sanitize_traces.py --check docs/e2e/runs/assets`; do not archive an artifact set unless the check exits 0. The helper uses only Python's standard library. All 24 Node E2E support tests and four Python sanitizer tests pass.

The guarded runner also fails closed when the host process list shows an active `phpunit` or `php artisan test` command. It checks before starting the E2E stack, each baseline mutation, the TypeScript check, and Playwright. The matching code never prints process arguments. A process can still start between checks, so the operator must monitor a long journey and stop the isolated E2E container if a PHP test appears. The process matcher has two focused Node tests.

The first PR creation emitted `net::ERR_ABORTED` for the request-list GET and two duplicate detail GETs as the SPA left the list and opened the created PR. The UI action returned HTTP 201, one detail GET returned 200, and the draft/status/database assertions passed. These two GET path prefixes are allowlisted only for `net::ERR_ABORTED` in `known-network-noise.json`; HTTP failures, other methods, other endpoints, and 5xx responses remain failures. Revisit this entry if later traces show a cancellation outside that route transition.

The first count-variance attempt emitted `net::ERR_ABORTED` for the periodic `GET /api/v1/realtime/health` while navigating into Warehouse Map. The isolated Nginx log recorded the canceled request as 499 after 0.379 seconds, followed by successful 200 health checks (including one after the transition); the page continued to report the real-time connection as Live. This exact GET cancellation is allowlisted in `known-network-noise.json`. Other health methods/endpoints and all 5xx responses remain failures. Revisit if a later run shows the cancellation without a successful subsequent health check.

The public landing page's `PlantMap` requests raster tiles from OpenStreetMap. On 2026-11-04, browser DNS could not resolve its `a`, `b`, and `c.tile.openstreetmap.org` hosts while the candidate used the landing page before Careers; this happened alongside a successful public application request. The E2E noise allowlist now matches only console and failed-request evidence from those three hosts under `/16/`. Console diagnostics preserve the full source origin to keep this rule specific. Application/API failures and all HTTP 5xx responses remain failures; revisit this allowlist if the external tiles become reachable in the E2E network.

The first submitted-PR hand-off showed delayed observer refresh and a `degraded` realtime health response. Source evidence showed `DispatchOutboxMessage` targets `redis_broadcasts` queue `high`, while the E2E `queue-broadcasts` worker listened only to `broadcasts`; the health check monitors both queues. The E2E worker now listens to `high,broadcasts`. This was an isolated Compose configuration defect, not an application-code change. In the latest PR-01 run, the second viewer displayed Pending without refresh after submission. Other realtime paths and reconnection behavior remain unverified.

PR-01 also surfaced [FND-PR-001](FINDINGS.md#fnd-pr-001--pr-submission-creates-no-approval-notification): `PurchaseRequestService` submits workflow key `purchase_request`, but `ApprovalService`'s on-submit notification allowlist uses `pr`. The submitted status and Finance approval row are persisted, while the expected Finance notification is absent. The journey failed at that recipient invariant and blocked the Finance action and every downstream PO step; no application fix was made.

The DB helper accepts one `SELECT` or `WITH` statement through the dedicated read-only database user and wraps it in a read-only transaction. The API helper is GET-only for assertions; mutation helpers are limited to negative authorization checks. The Mailpit helpers read captured messages; its [health endpoint](https://mailpit.axllent.org/docs/integration/healthcheck/) is checked by the platform test. PDF text extraction uses `pdftotext` inside the Playwright image.

## Platform verification results

| Check | Result |
|---|---|
| E2E runner guard/baseline/readiness Node tests | 20 passed, including fixed runtime project identity |
| E2E TypeScript harness check | Passed (`tsc -p tsconfig.process-e2e.json`) |
| E2E Compose stack | All long-running services reached healthy status |
| Baseline | Migrations and reference-data seed completed; current manifest has 37 actors (35 internal, 2 portal); no transactional demo records; guarded seed clears E2E Redis state first (validated at 2,720 queue keys / 8.92 MiB with no OOM logs) |
| UI account setup | Historical manifest: 35/35; latest count-variance setup: 4/4 and HR-07 setup: 3/3 targeted actors signed in through the UI; full current 37-actor setup remains pending |
| Playwright platform checks | 2 tests passed in 2.7 minutes: auth setup and gated clock/read-only DB/Mailpit checks |
| Focused PHP E2E unit tests | 6 passed, 13 assertions; PHPUnit warned that its result cache is unwritable because the API source/vendor mount is read-only |
| Business journey PR-01 | 10 steps planned; first run had 2 passed, submission step failed on missing notification, 7 downstream steps blocked |
| Business process tests | `INV01-COUNT-VARIANCE` completed; latest `HR07-BANK-CHANGE` run passed targeted Employee/HR/Finance login and all four journey steps, including audit, notification, mail, badge, and live-update checks |
| No-refresh observer update | Finance saw a stock adjustment change to Withdrawn; system admin observed count session and line changes through completion without refresh |

## Remaining limits at this gate

- Host access to the two loopback port bindings could not be tested in this Docker environment; the Compose-internal browser origin worked.
- The full app `tsc -b` build was not completed because it exceeded the configured 1 GiB Node heap. Vite production bundling and the dedicated E2E TypeScript check passed.
- Scheduler behavior, queue draining/resilience, Mailpit message assertions beyond health, PDF contents, most live second-viewer updates, and Phase 4 negative business permissions have not been exercised.
- PR-01 is not complete. Finance approval, PO conversion/approval, supplier dispatch/acceptance, PDF verification, and downstream inventory/ledger effects remain blocked by FND-PR-001. Phase 3 continues with other journeys; no app fixes are authorized before the Phase 7 approval gate.

## Local review notes

The workspace contained more than a thousand pre-existing modified/untracked paths at discovery. E2E work is confined to the E2E stack, guard, support code, docs, and the explicitly gated Vite preview host setting. No commit or PR was created from that shared dirty worktree; review the scoped changes before isolating them into the requested PRs.
