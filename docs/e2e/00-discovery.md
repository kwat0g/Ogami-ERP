# Phase 0 — E2E discovery

**Snapshot date:** 2026-11-03 (UTC)  
**Repository:** `/opt/ogami-erp`, branch `realtime/cache-p2p`  
**Scope:** read-only source and environment inventory. No browser journeys, API mutations, database queries, tests, or application changes were made.

## Evidence rules and limits

This discovery is derived from the checked-out source, seeders, route declarations, enums/state machines, current project documentation, and read-only `docker compose ps --all`. It does not claim to know the contents of the live development database: that database is shared/persistent and was deliberately not queried. The worktree already had 1,325 modified or untracked paths when this review began. The findings describe this checkout, including any pre-existing uncommitted code; they are not proof that every such change is deployed or running.

Primary evidence paths are linked inline. Important source groups are `api/app/Modules/*`, `api/database/seeders/*`, `api/routes/console.php`, `spa/src/routes/*`, `spa/src/App.tsx`, `spa/src/components/layout/Sidebar.tsx`, `spa/playwright*.config.ts`, `docker-compose.yml`, `api/tests/Feature/*`, and the historical audit documents listed below.

## A. Roles, accounts, permissions

### Internal roles and seeded accounts

`RolePermissionSeeder` defines **17 internal roles** and **290 permission slugs**. `DemoAccountSeeder` seeds **20 demo users** across those roles; these accounts are development data with a shared development-only password and are not an E2E account baseline. The role/user counts and organizational scopes below come from those seeders, not from the prompt.

| Role slug | Demo users | Seeded scope / typical ownership |
|---|---:|---|
| `system_admin` | 1 | IT; wildcard effective permission bypass |
| `hr_officer` | 1 | HR operations and payroll setup |
| `finance_officer` | 2 | FIN; AP/AR, journals, periods, budgets |
| `vice_president` | 1 | EXEC; configured higher-value approvals |
| `customer_service_officer` | 1 | CS; customer service and return/complaint work |
| `sales_officer` | 1 | SALES; customers, pricing, sales orders |
| `production_manager` | 1 | PROD; production and configured approvals |
| `ppc_head` | 1 | PPC; planning and production scheduling |
| `purchasing_officer` | 2 | PUR; requisitions, RFQs, purchase orders |
| `warehouse_staff` | 1 | WH; receiving, inventory, picking and dispatch |
| `qc_inspector` | 1 | QC; inspection and nonconformance |
| `maintenance_tech` | 2 | MAINT; assigned maintenance work |
| `maintenance_supervisor` | 1 | MAINT; maintenance assignment/oversight |
| `impex_officer` | 1 | IMPEX; inbound/outbound shipment and customs |
| `department_head` | 1 | PROD in demo data; department-scoped approval |
| `employee` | 1 | PROD in demo data; self-service actions |
| `driver` | 1 | WH in demo data; delivery execution |

Each internal admin user has one `role_id`, optional employee linkage, per-user permission grants/revocations, and optional expiry. Effective permission evaluation and wildcard behavior are implemented in the Admin/Auth area; the backend remains the authorization authority. References: `api/database/seeders/RolePermissionSeeder.php`, `api/database/seeders/DemoAccountSeeder.php`, `api/app/Modules/Admin/`, `api/app/Modules/Auth/`.

There are additional identity realms for customer and supplier portal actors. Portal identities do not appear as additional internal roles in the 17-role catalog. Customer and supplier portal routes are separate from the internal sidebar. The current demo seeder is not a disposable, comprehensive portal-account baseline. `AuditPortalActorSeeder` is evidence of an audit actor, not a general E2E portal account solution.

### Effective permissions, scope, landing pages and surfaces

The source permission set is grouped by functional namespace in `RolePermissionSeeder.php`: accounting, admin, alerts, approvals, assets, attendance, auth, b2b, budgeting, business policies, calendar, CRM, dashboard, DSS, forecasting, HR, inventory, leave, loans, maintenance, MRP, notifications, payroll, production, purchasing, quality, return management, search, and supply chain. The Phase 1 permission matrix maps every permission slug to a positive exercise and a negative actor or explicitly marks it read-only/uncovered with reason.

The internal sidebar groups links into Overview, Sales & CRM, Production, Production Planning (MRP), Procurement, Warehouse, Supply Chain, Quality, Finance, Human Resources, Maintenance, Assets, and Administration (`spa/src/components/layout/Sidebar.tsx`). The `/dashboard` landing is selected by backend permission-aware dispatch (`GET /dashboard/dispatch`); `/dashboard/default` is a fallback. Role-to-page reachability is permission-filtered in route and sidebar layers. A complete rendered landing/sidebar check is not possible without an isolated E2E account set and a safe browser environment.

Portal landing/navigation is based on the separate route modules (`spa/src/routes/portalRoutes.tsx`, `careersRoutes.tsx`, `landingRoutes.tsx`, `driverRoutes.tsx`, `maintenanceMobileRoutes.tsx`). Floor/PWA views include production/factory and maintenance/mobile surfaces; delivery/driver workflows are another role-specific surface. These screens require separate tablet/touch journeys in the catalog where the code exposes a distinct surface.

### Approval configuration, delegation, locked users and RBAC risks

`WorkflowSeeder.php` declares 17 workflow types. Ten are active/wired by the current workflow configuration; seven are reserved/inactive definitions. The active seeded chains are:

| Workflow | Configured steps / condition |
|---|---|
| leave request | Department Head → HR Officer |
| cash advance | Department Head → Finance Officer → Vice President |
| company loan | Department Head → HR Officer → Finance Officer → Vice President when amount is at least ₱50,000 |
| purchase request | Finance Officer → Vice President when amount is at least ₱50,000 |
| purchase order | Finance Officer → Vice President when amount is at least ₱50,000 |
| bill payment | Vice President when amount is at least ₱50,000 |
| salary adjustment | Production Manager → Vice President |
| asset disposal | Finance Officer → Vice President |
| return request | Department Head → Production Manager |
| finance-only return request | Finance Officer |

The configured but reserved/inactive types are department transfer, payroll, work order, NCR, maintenance request, separation clearance and 8D report. They are not assumed to be live approval paths. Source: `api/database/seeders/WorkflowSeeder.php` and the workflow registry/dispatch code in `api/app/Modules/*`.

Other bespoke approval actions are visible in module services/controllers and must be enumerated in Phase 1: overtime, budget/transfer, high-value stock adjustment, stock-count verification/adjustment, item cost review, supplier listing, profile changes with finance-sensitive fields, payroll period, purchase-order response, price offers, return disposition, and separation/clearance. Their actual approver resolver, self-approval behavior, and no-approver behavior are marked for code-path tracing and later process verification; seeded generic workflow configuration alone does not prove those rules.

Approval delegation is permission-gated and time-bounded in Admin/Auth. Admin users have active/inactive/locked status, login history/failure states, and lockout behavior. The source contains delegation and inactive/locked controls; the demo account seeder only supplies active users. Dedicated E2E scenarios must seed isolated test identities to prove expiry, out-of-office/delegation, locked/inactive login, and requester/approver separation. A `system_admin` can effectively bypass ordinary permission checks, but that must not be treated as permission to satisfy a business approval step; workflow step authorization is a separate check to verify.

### Frontend/backend alignment findings

No live UI/API authorization mismatch was reproduced in Phase 0 because the dev server and database were not used for browser/API probing. Existing source and historical evidence identifies the following targets:

1. `docs/role-access-alignment-audit.md` (2026-10-02) reports 864 browser route checks and 176 read checks across 16 roles, with no effective permission mismatch in that historical snapshot. The current source has 17 roles, so this result is not current full-system proof.
2. The same audit records a `system_admin` stored-role permission pivot mismatch (a missing shipment-view grant and a retired journal permission); wildcard effective access masks part of the difference. Reconcile the current matrix from source and test both stored and effective authorization.
3. The audit calls out `hr_officer` retaining `loans.approve` for a form lookup while the actual approval step is separately enforced. Test UI availability and backend step authorization independently.
4. A historical set of five apparently unused grants needs rechecking against the current source. The permission matrix must include all 290 current slugs, including grants with no UI route.
5. Current frontend route guards/sidebar visibility and backend policies/controllers are distinct enforcement layers. The catalog records each protected action's UI role and planned direct-API negative assertion; static route presence alone is not an authorization pass.

Historical evidence is not treated as current behavior. See `docs/role-access-alignment-audit.md` and `docs/e2e-audit.md`.

## B. Derived process and module inventory

### Repository scope found

The code has **23 backend module directories**, not the prompt's illustrative 17: Accounting, Admin, Assets, Attendance, Auth, B2B, CRM, DSS, Dashboard, Forecasting, HR, Inventory, Landing, Leave, Loans, MRP, Maintenance, Payroll, Production, Purchasing, Quality, ReturnManagement, and SupplyChain. Frontend route declarations span 24 route modules, with 365 unique route-template declarations in the current static scan. The backend composer constraint is Laravel `^12.64` and PHP `^8.3`; this checkout does not match the prompt's Laravel 11 assumption. The SPA uses React 18, React Router 7, Vite 7 and Playwright 1.63 per the package manifests.

The static scan counted 254 model files, 830 migrations, 923 Feature-test files and 69 Unit-test files. These are file counts, not proof that each model/state/route is covered. Existing unit/feature tests are discovery evidence only and were not run.

### Process families derived from routes, models, status types and cross-module hooks

The Phase 1 catalog groups related screens and records into **31 process specifications**. Each specification has distinct journeys for material variants (rather than an assertion that every enum value is a business process):

1. Identity, authentication and account lifecycle (internal users, password, lock/inactive, sessions).
2. Role/permission administration, delegation and approval configuration.
3. Administration/settings, audit, imports, exports and operational configuration.
4. Dashboards, notifications, alerts, DSS recommendations and action inboxes.
5. Public website contact/newsletter/careers intake.
6. HR master data and employee organizational records.
7. CRM customer, product, price, agreement, inquiry and complaint management.
8. Procurement sourcing: purchase request, RFQ, supplier quote, purchase order and supplier response.
9. Receiving: goods receipt, incoming quality handoff, putaway and AP bill creation.
10. Accounts payable, three-way match, payment approval and disbursement.
11. Warehouse stock adjustments, cycle counts, transfers and high-value approval.
12. Reservations, picking, production material issue and unused-material return.
13. Forecasting/MRP, plan review, replenishment proposal and production planning.
14. Production scheduling, work-order execution, output receipt and finished goods.
15. Quality inspection, NCR/CAPA/effectiveness, calibration, traceability and PPAP.
16. Inbound supply-chain shipment, manifest/customs and receipt.
17. Outbound shipment, delivery, driver proof, CoC and invoice/cost handoffs.
18. Customer and supplier returns and return-case/problem reporting.
19. Order-to-cash sales order, confirmation, credit/availability checks and downstream trigger.
20. Accounts receivable invoice, collection/payment proof and credit note.
21. General ledger, journals, reconciliation, accounting period close and finance reports.
22. Budget lifecycle, transfers and budget-limit exceptions.
23. Recruitment, offer, hiring and onboarding.
24. Attendance/DTR, schedules, holidays, overtime and exceptions.
25. Leave request, approval, accrual and attendance/payroll consumption.
26. Loans and cash advances, repayment, write-off and payroll consumption.
27. Payroll period, inputs, adjustments, approval, posting, bank/statutory exports and payslips.
28. Employee self-service, profile change, salary adjustment, review and training/skills.
29. Separation, clearance, property return and final pay.
30. Maintenance preventive/breakdown work orders, assignment, mobile execution and downtime.
31. Asset register, custody/issue, transfer, depreciation, maintenance and disposal.

These are grouped process specifications, not 31 assumed happy paths. The catalog breaks each family into separately identified journeys, including portal actors and tablet/floor UI where present. Reports, dashboards, notifications and admin are included because they are routes and side effects, rather than treated as invisible infrastructure.

### Cross-module triggers and side effects

The service, observer, event/listener, queued-job and route scan found these high-value chains to verify:

| Trigger | Expected related records/effects to inspect in E2E |
|---|---|
| Sales order / demand plan | MRP run/plan, PR or planned work order, reservation/availability, sales-order status, audit/notifications |
| PR approval and sourcing | RFQ/quote selection, PO, approval, supplier portal dispatch, PDF/email, document number, workflow inbox/badge |
| PO receipt | GRN, incoming inspection, accepted/rejected quantities, stock ledger/balance, PO partial/received status, possible supplier bill/AP, audit and notification |
| Production material/output | reservation and material issue, operation/work-order state, output receipt, inspection, finished goods movement, cost/GL handoff |
| Delivery | stock reservation/consumption/release, shipment/driver proof, CoC, discrepancy, invoice/cost handoff, customer email and real-time status |
| AP/AR and accounting | bill/invoice/payment records, duplicate detection, balanced journal, period lock, GL handoff, aging/dashboard values |
| HR approvals and payroll | approved attendance/overtime/leave/loan/salary inputs, adjustments, payroll totals, GL/bank/statutory artifacts and payslip |
| Quality / returns | lot inspection, MRB disposition, NCR/CAPA, supplier/customer return, inventory disposition, notifications/email, trace links |
| Maintenance / assets | work order, assignment, asset state, downtime, PM schedule, parts issue/return, depreciation/disposal accounting |
| Any approval/update | audit actor, workflow state, inbox/sidebar badge, intended recipient notifications, outbox/queue delivery, realtime update |

Exact email recipients, accounting treatment, document contents, badge counts, and WebSocket behavior require process execution in the isolated E2E platform and business-rule answers in `01-process-catalog.md`; they are not inferred as passing facts here.

### State machines

The source exposes explicit state machines for journals, sales orders, employees, recruitment applications/postings, training, loans, maintenance work orders, production work orders, quality inspections/NCR effectiveness and return requests; delivery and shipment have status enums/transition services. Their explicit edges are mapped in the Phase 1 matrix. Other statuses are service-managed. Phase 1 includes all discovered enum values and distinguishes entity states from classification/handoff states; transitions are mapped only where source proves an edge. Every unexplained or UI-unreachable state is assigned a gap ID, not silently counted as covered.

## C. Side-effect model

Side effects considered for every catalog step:

- **Identity/workflow:** role and scope, approval event/step, audit entry and actor, delegation, inbox item, recipient-only notification and sidebar badge.
- **Documents:** linked source record, sequential/unique document number, totals/quantities, revision/parent, generated PDF/export and source fields.
- **Inventory:** on-hand/reserved/available balances, stock ledger movement, location/lot/serial, disposition and valuation.
- **Finance:** AP/AR balances, journal debit-credit balance, GL handoff, budget consumption, period constraints and reconciliation.
- **People:** employee status, attendance/DTR, leave accrual, loan deductions, payroll components/statutory output, issued property/clearance.
- **Operations:** work order, schedule, machine/mold, production receipt, inspection/NCR/CAPA, maintenance downtime and asset status.
- **Async/realtime:** queue/outbox state, email recipient/content/link in Mailpit, live second-viewer status/button update, scheduler-produced record, retry/idempotency behavior.

The code includes events, observers and listeners in multiple modules (for example return request email/realtime under `api/app/Modules/ReturnManagement/{Listeners,Observers,Events,Services}`). The current shared Compose stack includes Horizon, a mail queue worker and a scheduler. Their presence is not evidence of correct delivery; the E2E plan requires read-only assertions after UI actions.

## D. Test infrastructure and safe environment

### Current Playwright setup

- `spa/playwright.config.ts` is primarily mock/API-stub oriented, defaults to Firefox, has one worker, CI retries, trace on first retry, failure screenshots and video retention.
- `spa/playwright.real.config.ts` uses Chromium, one worker, zero retries, failure trace/screenshots and no video. It now calls the shared fail-closed environment guard at config load, requiring the explicit E2E flag, dedicated database and Compose identifiers, testing PostgreSQL service identity, and the loopback/private E2E URL allowlist. It still has no login-UI setup project/storageState or custom Markdown process reporter. The managed process config separately runs the same guard in global setup.
- `playwright.live/*` and other one-off configs are separate and have divergent assumptions.
- Some current real-E2E helpers authenticate through API instead of the requested real login UI. Existing Playwright files are not accepted as compliant journeys without review.

### Current Compose state and guard status

Read-only `docker compose ps --all` showed a running development stack: API, Postgres, Redis/cache, Reverb, Horizon, mail queue worker, scheduler, SPA and Nginx. The active configuration is `docker-compose.yml`. There is no `docker-compose.e2e.yml`, root/API/SPA `.env.e2e`, E2E-specific database guard, Mailpit/MailHog service, configured per-container memory limits, isolated E2E seeder/snapshot reset, or gated clock/scheduler control found in the checked paths. Do not launch Playwright against this running developer stack. The database name and URL must be checked in the isolated platform before any future E2E run.

The reset method cannot be assumed yet: migrations plus an isolated E2E seeder or a versioned baseline snapshot must be designed in Phase 2. Docker memory limits, worker concurrency, separate Postgres/Redis storage, Mailpit, file storage, Reverb, and scheduler hook are requirements, not present capabilities.

### Async, document and time behavior

`api/routes/console.php` schedules recurring work: minute-level production reminders and RFQ close checks; 15-minute incoming-QC retry/alert processing; hourly outgoing-QC and stale MRP/payroll/DSS processing; daily maintenance preventive generation and MRP/payroll work; monthly depreciation/forecast work; and periodic exports/Horizon/leave accrual work. Payroll auto-period logic has mid-month/month-end triggers. Exact timing and effects must be read from command implementations before finalizing each timed journey.

PDF/email/export generation and queue jobs exist in module code. Current Compose has queue workers but no mail catcher. No safe time-travel clock or on-demand scheduler/queue drain is installed. Phase 2 proposes E2E-gated clock and scheduler/queue controls; if not approved, those variants will be explicitly manual/unautomatable. Use explicit UI/network readiness and read-only inspection, never fixed sleeps.

Timezone-sensitive behavior should be tested against the app's actual storage and formatting. The user requested Asia/Manila display and ₱ formatting; business defaults such as cutoffs, payroll periods, due dates, probation milestones and weekend rules need confirmation or a documented code-derived default.

### Floors, tablets and PWA

Distinct factory, driver and maintenance mobile route modules exist. Phase 1 maps their workflows to internal process specifications and flags required tablet/touch projects. No separate PWA install/offline contract or live floor-device behavior was validated in this phase.

## E. Known gaps and risks — initial Process Gap Register

Severity is provisional and reflects the stated mission's ability to test safely and complete process journeys, not a production incident assessment.

| Gap ID | Severity | Finding / evidence | Phase 1 treatment |
|---|---|---|---|
| GAP-ENV-01 | High | No dedicated E2E Compose environment, isolated DB/seeder/reset, mail catcher, or memory caps found; only shared `docker-compose.yml`. | Block all browser runs until Phase 2 exists and guard passes. |
| GAP-ENV-02 | Resolved and config-verified | Direct real-config invocation previously accepted `E2E_BASE_URL=https://ogamiph.dev` and continued into spec discovery. The shared E2E guard is now called while loading `spa/playwright.real.config.ts`, before test discovery. Unsafe list mode fails on the allowlist; safe loopback list mode lists the targeted spec. No browser or application request was made. | Keep the allowlist/database guard at config load; never run this config against the shared dev or production stack. |
| GAP-ENV-03 | Medium | Real setup helper uses API login; no role-by-role real-login setup project/storageState or required Markdown process report. | Plan compliant multi-context setup; no login shortcut. |
| GAP-ENV-04 | Medium | No Mailpit/MailHog service or mail assertion client in current Compose. | Cannot verify recipient/content until isolated mail catcher exists. |
| GAP-ENV-05 | Medium | No E2E-gated clock/scheduler/queue drain control found. | Time-driven flows blocked/manual absent approval and implementation. |
| GAP-UI-01 | High | Quality exposes PPAP API routes and PPAP status types, but no PPAP SPA route/page in `spa/src/routes/qualityRoutes.tsx` or Quality page tree. | PPAP UI journey marked blocked at first unavailable step; never API-create/update. |
| GAP-UI-02 | High | Source recheck confirmed the salary-adjustment API request endpoint, HR request permission, and active PM → VP approval workflow have no SPA initiation form/action. `EmployeeForm.tsx` removes compensation fields and directs users to the reviewer queue; `SalaryAdjustmentsTab` has list/approve/reject controls only. Confirmed during Phase 3 on 2026-11-04; see [blocked journey report](runs/2026-11-04-HR07-SALARY-APPROVAL-blocked.md). | Mark HR07-SALARY-APPROVAL blocked at UI request creation with `test.fixme` and GAP-UI-02; never create the salary record through API. |
| GAP-HR-08-02 | High | Later source/UI cross-check: `SeparationService::cancel()` and `PATCH /hr/clearances/{clearance}/cancel` support cancellation, and `ClearanceResource` advertises the action to HR, but `spa/src/api/separations.ts` has no cancellation method and `spa/src/pages/hr/separations/detail.tsx` renders no cancel control. A rescinded/mistyped separation can leave the employee on leave without a UI reversal. | Block HR08-CANCEL-REOPEN at the absent UI action with `test.fixme`; do not call the positive cancellation route. Verify after an approved UI fix. |
| GAP-APPR-01 | Medium | Workflow seeder's payroll chain is defined but payroll is not among the wired generic workflow types; payroll has its own period states. | Trace bespoke payroll checker path; ask whether generic payroll chain is intended. |
| GAP-APPR-02 | Medium | Seven workflow definitions are reserved/inactive (transfer, payroll, work order, NCR, maintenance request, separation clearance, 8D). | Map active controller/service flows; inactive definitions are not presumed usable. |
| GAP-RMA-01 | Medium | Customer returns have internal and customer-portal paths. Supplier return requests are internal; supplier portal exposes problem reports, not a supplier return-request screen. | Treat internal supplier RMA as source-supported; ask if supplier-portal initiation is a requirement. |
| GAP-RMA-02 | Medium | Historical audit mentions customer return notification risk. | Revalidate recipient-only notifications and portal/internal email behavior during journey. |
| GAP-AUTH-01 | High | Historical 16-role access audit is stale against current 17 roles; admin wildcard can obscure stored grants and must not bypass workflow-step checks. | Rebuild source-derived matrix and run per-role negative checks after approval. |
| GAP-AUTH-02 | Medium | Historical audit lists unused grants; current 290 slugs must be reconciled to routes and backend handlers. | List all exact permissions in Phase 1 matrix, including no-UI exemptions. |
| GAP-STATE-01 | Medium | Some explicit state machines coexist with service-managed status transitions. | Map every enum/status; unknown edge or UI-unreachable status gets a separate gap ID. |
| GAP-ASYNC-01 | Medium | Queue, outbox, email, Reverb and realtime paths are present but not run or observed. | Require second-viewer and durable delivery assertions once platform exists. |
| GAP-DYN-01 | Medium | Historical E2E audit reported dynamic templates without linked records. | Recheck current route-to-model association; old count is not treated as current fact. |
| GAP-AUD-01 | Medium | Historical deep module audit records 18 remaining P2 items after seven items marked fixed/verified in October 2026 (money bounds, accounting close, PM schedules, maintenance qualifications, logistics null/weight/lock behavior, forecasting of blocked/expired stock, mobile reporting overflow, among others). | Link `docs/testing/remaining-business-modules-deep-audit-20261005.md`; verify current HEAD before classifying any as an E2E finding. |
| GAP-DATA-01 | Medium | Demo accounts are development identities; no comprehensive disposable role/scope/portal baseline is present. | Do not use demo credentials; seed unique E2E accounts only in isolated platform. |
| GAP-OBS-01 | Low | Live DB, server UI, socket and actual mail were deliberately not inspected because only the shared dev stack is running. | Environment finding, not an application defect; revisit only after isolation exists. |

Historical docs used as pointers, not authority over current source: `docs/e2e-audit.md`, `docs/role-access-alignment-audit.md`, `docs/testing/remaining-business-modules-deep-audit-20261005.md`, and `docs/SYSTEM-AUDIT-EXECUTIVE-SUMMARY-2026-08-13.md`.

## Phase 0 exit and Phase 1 boundary

The source inventory is sufficient to produce the candidate process catalog and coverage plan. It is not sufficient to claim behavioral correctness, to certify every permission, or to run against the current stack. Phase 1 records the source-derived ordered steps and maps every known route/module/status/approval/permission to a journey or a gap. **No tests are authored or run before user review of that catalog.**
