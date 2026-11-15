# Phase 1 — Demo data plan (approval gate)

Status: approved by user; implementation is in progress by phase. Based on [00-discovery.md](00-discovery.md). Policy-dependent volumes and payroll data remain subject to the decisions in section L; approval does not resolve those open questions.

## A. Time anchor

- Default as-of is the actual run date. Discovery date: 2026-11-14. Use Asia/Manila dates and 18 prior months by default, configurable to 12–24 months.
- Generate in chronological order with controlled time for document, created, approval and audit timestamps; restore real time in a finally block.
- Overdue/aging/dashboard/scheduler logic follows runtime time across API and workers. Safe default: seed the day before defense or rehearsal. A fixed future demo date requires approval and an app-wide demo-clock design.
- On 2026-11-14, Nov 1–15 is not processable because payroll cutoff has not ended. Leave it draft; park Oct 16–31 as the latest completed live-ready period. Leave current-day attendance/newest transactions as live-entry buffer.
- Open question: defense date and rehearsal dates.

## B. Scale targets

| Entity/history | small | demo | full |
|---|---:|---:|---:|
| Employees | 20 | 200–210 | 230–250 |
| Customers / suppliers | 4 / 6 | 10–12 / 18–22 | 12–15 / 25 |
| Raw/consumable items | 12–18 | 70–90 | 100–120 |
| Finished goods | 8–12 | 45–60 | 70–80 |
| Injection machines / molds | 6 / 12 | 28–36 / 60–80 | 40 / 100–120 |
| Spare parts | 20 | 100+ | 120–160 |
| Attendance span | 3 months | 18 months | 24 months |
| Closed payroll periods + in-flight | 2 + 1 | 36 + 1 draft | 48 + 1 draft |
| O2C/P2P/production/quality/maintenance | ~20 per family | 40–100 per family over history | 100–250 per family |

Small is the test/E2E baseline. Demo is the thesis target. Scale selects from fixed personas/scenarios; no uniform random row generator.

The command contract supports demo:seed with --as-of, --scale, --seed, --only and --no-history; guarded demo:reset, demo:snapshot, demo:restore and demo:credentials are under Phase 2 implementation. demo:verify remains planned for Phase 6. A fixed seed must yield stable reference identities and checksums; reset/reseed must not duplicate or drift. See [02-architecture.md](02-architecture.md) for the current tooling state.

## C. Module-by-module data plan

| Module | Demo target/status spread | Data links and demo purpose |
|---|---|---|
| Accounting | Accounts, periods, journals, budgets/transfers, bills/payments, invoices/collections, credit notes/advances; 12–20 budgets, 25–40 bills, 40–60 invoices, 30+ collections; draft/submitted/approved/active/closed and unpaid/partial/paid/cancelled | Match source documents and ledger; AR/AP aging, statements, payment approval |
| Admin/Auth | User per role plus approvers, permissions/settings/sessions/audit/activity/exports | Fake role accounts, audit trail and operations pages; no real outbound effects |
| Assets | 30–40 plant/vehicle/support assets; active/under maintenance/disposed; transfers pending/approved/rejected/completed | Machines, vehicles, depreciation and asset review |
| Attendance | ~200 employees × 18 months; all seven attendance statuses; overtime pending/approved/rejected | Valid shifts/holidays/rest days feed DTR, payroll and badges |
| B2B | 8–12 customer portals, 1–2 users per customer; delivery schedule and order response states | Shared sales/delivery records with customer-visible response history |
| CRM | 10–12 customers, 45–60 products, 80–120 SOs; all SO/price request/complaint lifecycle states where transitions allow | Same part/customer across MRP, production, delivery, invoice, return, complaint |
| DSS | Run every current detector; recommendation lifecycle states where service supports them | Identify stockout, late supplier, NCR SLA, stuck approval/document, collections and at-risk order |
| Dashboard/Forecasting | 76 widgets populated where applicable, 11 KPIs with 12–18 monthly points, 12–24 forecast/history periods | Role dashboards, seasonal trends and decision support |
| HR | 200–210 employees weighted to production; recruiting/interview stages, trainings/skills and expiries, profile changes, separations/clearances | Hiring/onboarding, certifications, profile and separation workflows |
| Inventory | 70–90 RM/consumables + 45–60 FG; 4 warehouses/zones/bins/lots; healthy/low/critical/out/overstock/near-expiry/slow-moving spread | Ledger, lot trace, counts/adjustments, QC quarantine and alerts |
| Landing | 4–8 contact inquiries new/in-progress/closed; newsletter subscribed/unsubscribed | Public forms/admin list; static home/legal pages have no list data |
| Leave | Leave types/balances/requests across pending_dept/pending_hr/approved/rejected/cancelled | Department/HR approval; payroll follows existing paid-leave behavior |
| Loans | 8–12 company loans/cash advances across pending/active/paid/cancelled/rejected/write-off states where supported | Approval and amortization; exclude unsupported statutory loan requests |
| MRP | 45–60 BOM/routing products; 28–36 injection machines plus auxiliary assets; 60–80 molds; plan/run states | Demand → capacity/material shortage → production/purchasing |
| Maintenance | 100+ spare items; calendar/shot PM; WO open/assigned/in-progress/completed/cancelled; calibration due/overdue | Breakdown, repair, PM, downtime, MTBF/MTTR and mold threshold. The current item model does not enforce spare-part compatibility with machines/molds; see F-024. |
| Payroll | 36 closed semi-monthly periods + latest completed live period + current draft; supported period/adjustment/bank/GL states | Live processing and independent worksheet; policy input required |
| Production | 40–100 WOs over history, all valid WO/operation/schedule/report stages, assigned machine/mold/crew, outputs and scrap | Gantt, shop-floor queue, yield/OEE and customer-order causality |
| Purchasing | 18–22 suppliers; 60–100 PR/PO/RFQ chains; partial/complete receipts, rejected lots; all valid request/order/quote/dispatch states | Approval, RFQ, supplier portal, QC, stock and AP |
| Quality | Incoming/in-process/final inspection; NCR/CAPA/PPAP/effectiveness states; complaints/8D/calibration | Link defects to lot/vendor/item/mold/machine/WO/customer; IATF trail |
| ReturnManagement | 8–15 cases/requests across draft/pending/approved/received/inspected/completed/rejected/cancelled and case lifecycle | Approval, quarantine, replacement and credit-note chain |
| SupplyChain | 40–80 deliveries across scheduled/loading/transit/delivered/confirmed/returned/cancelled; 6–10 shipments | Proof, overdue deliveries, vehicle and shipment dashboard |
| Maintenance mobile | Assigned maintenance WOs/logs and due work | Mobile queue over same maintenance records |
| Driver | Assigned delivery/vehicle/proof stages | Role-specific delivery and proof screen |

Volumes are proposals, subject to service rules and performance verification. No transactional raw inserts are planned by default.

### Reference master-data shape

- Organization: use the current departments/positions as the starting taxonomy, subject to review of the legacy rows Gnehm Ryien Rabe and JILAY. Proposed headcount weighting is 52–58% production, 8–11% maintenance/engineering, 7–10% quality, 7–10% warehouse/logistics, 4–6% planning, 3–5% purchasing, 3–5% sales/customer service, 2–4% HR, 2–4% finance, 3–5% IT/admin and remaining leadership/import-export/support. Reporting chain: executive → function head → supervisor/team lead → employee, with named active approvers from current workflow config.
- Company profile: use the app’s company/legal fields with obviously synthetic demo-marked tax/registration values; never copy real identifiers.
- Customers: 10–12 fictional automotive/motorcycle Tier-1-style accounts with customer part references, delivery cadence, supported payment terms and credit-limit values. Suppliers: 18–22 fictional resin, masterbatch, insert, packaging, consumable, tool and spare-part vendors; assign lead-time/on-time/reject personalities that history can substantiate.
- Materials/products: 70–90 resin/colorant/insert/packaging/consumable items; 45–60 finished plastic parts. Preserve app codes/UOMs. BOMs include resin, masterbatch, inserts and packaging where applicable; define scrap/yield, cavity count, cycle and packaging only in fields the app supports.
- Inventory: opening lots by warehouse/bin through the inventory opening/adjustment mechanism; deliberately allocate healthy, low, critical, zero, overstock, near-expiry and slow-moving groups. Keep ledgers and on-hand values reconcilable.
- Assets: 28–36 injection presses with varied tonnage/makes and 60–80 molds in demo scale, linked through compatibility/routing. Add auxiliary support equipment, PM plans by calendar/shot threshold, spare-part catalog entries with intended equipment classes (the app has no enforced spare-to-asset compatibility relation; F-024), and calibration instruments with due/overdue evidence.
- Quality/finance: inspection specifications/plans and defect codes by part family; chart of accounts and tax/payment lookups from system seeders; budgets/cost centers only where the current schema supports them.

## D. Scenario catalog

All actors start with a role account and records must be created through current app flows. If the real process cannot produce a park point, document a finding; do not fake a status.

| Scenario | Actor / start page | Park point → live action | Expected result |
|---|---|---|---|
| Awaiting order confirmation | customer_service_officer / CRM orders | Order awaits response → accept/negotiate | Response history and order badge |
| Shortage to auto-PR | ppc_head / MRP | Confirmed SO lacks resin → run plan/create linked PR | Shortage and purchasing inbox |
| Partial delivery | warehouse_staff / deliveries | QC-released FG partly allocated → deliver balance | Proof, reservation and invoice handoff |
| Overdue unpaid invoice | finance_officer / AR aging | Delivery-backed invoice overdue, no collection | Badge and collection-priority DSS |
| Reject and resubmit order | customer_service_officer / order detail | Rejected response then corrected response | Superseded/rejected history |
| Cancelled order | sales_officer / orders | Cancel before production through valid transition | Terminal status and audit |
| PR through partial PO receipt | finance_officer then warehouse_staff / approvals, GRN | Pending PR → approve/convert → partial receipt/QC | Approval, inventory and supplier quality |
| Supplier bill payment | finance_officer / bills | Unpaid bill → submit payment for VP review | Payment approval and AP/journal |
| Interview and onboarding | hr_officer / recruitment | Candidate interview → hire/onboard | Employee, training due and recruitment counts |
| Payroll continuation | hr_officer / payroll | Latest ended cutoff ready → compute/review/approve | Payslip lines and bank/GL handoff |
| Separation clearance | hr_officer / separation | Resigned employee has supported clearance tasks pending | Clearance and final-pay state |
| Count variance | warehouse_staff then finance_officer / inventory | Count variance → submit adjustment approval | Count, stock health and badge |
| Quality containment | qc_inspector / inspections | Failed lot links supplier or mold/WO → contain/open NCR | Quarantine, CAPA, quality trend |
| Customer complaint | customer_service_officer / CRM | Open complaint inside SLA → supported 8D/NCR step | SLA clock and handoff |
| Breakdown and PM | maintenance_tech / maintenance WO | Press breakdown in progress → log repair/spare; separate PM due | Downtime, spare balance and maintenance queue |
| Mold shot threshold | maintenance_supervisor / mold/PM | Usage near interval → schedule PM | Due schedule/workload |
| Supplier portal RFQ | purchasing_officer / RFQ | Open RFQ with supplier response pending → compare/award | Quote comparison and portal badge |

Phase 5 verifies each click path, actor permissions, approval records, badges and dashboard changes before publishing a demo script.

## E. Role × pending-work coverage

Active approvers need actual approval records. Other roles receive a role-visible task/badge, not a fabricated approval. Current app permissions and role filters must be verified.

| Role | Planned current work |
|---|---|
| system_admin | DSS/system alert, audit or export action |
| vice_president | bill/PR/PO/company-loan approval |
| finance_officer | PR/PO/bill/loan/return approval and AR/AP action |
| department_head | leave/advance/company-loan approval |
| hr_officer | leave/loan step, payroll, profile request, training expiry |
| production_manager | return/salary approval and schedule exception |
| purchasing_officer | quote/listing review and overdue PO |
| warehouse_staff | GRN/QC handoff, pick/issue or count variance |
| qc_inspector | inspection review, incoming QC or NCR |
| maintenance_tech | assigned breakdown/PM work |
| maintenance_supervisor | PM due, unassigned WO, mold threshold |
| ppc_head | MRP shortage and WO release |
| sales_officer | order, price request or customer response |
| customer_service_officer | inquiry, complaint or return response |
| driver | assigned delivery/proof |
| impex_officer | in-transit shipment/customs document |
| employee | own DTR, leave, payslip/request only |

Portal actors get an actionable order response or RFQ. Test role excluded. Notifications only for realistic currently open work, never historical bulk notices.

## F. Payroll personas

Expected worksheet will independently calculate base/effective salary, eligible days, approved OT by date type, night hours, late/undertime, leave/absence rule, loan balance/payment, statutory/tax lines, gross, deductions and net. It will not call payroll application code. Numerical expected payslips are blocked until policy tables and open questions are resolved.

| Persona / fixture | Fictional demo employee | Pattern | Reconciliation |
|---|---|---|---|
| Clean control | PAY-01 Ana Mae Villanueva | Full scheduled attendance | Regular semi-monthly baseline |
| Chronic late | PAY-02 Joel Mercado | Repeated late punches | Hourly rate × late minutes |
| Absences | PAY-03 Lorna Bautista | Several, clustered Monday/holiday-adjacent | Engine presently leaves basic unchanged |
| Undertime | PAY-04 Carlo Dizon | Early departure | Hourly rate × minutes |
| Half-day | PAY-05 Mariel Santos | Half-day status/punches | DTR hours |
| Ordinary OT | PAY-06 Rogelio Cruz | Approved normal-day hours | Configured 1.25 |
| Rest-day OT | PAY-07 Joanne Reyes | Approved rest work | Confirmed multiplier |
| Regular-holiday OT | PAY-08 Paolo Garcia | Work on approved regular holiday | Calendar and holiday multiplier |
| Special-holiday OT | PAY-09 Leandro Mendoza | Work on special holiday | Calendar and multiplier |
| Night shift | PAY-10 Luz Navarro | Eligible night hours | Configured 0.10 |
| Paid leave | PAY-11 Angela Ramos | Approved paid leave | Basic retained, leave-pay field zero |
| Unpaid leave | PAY-12 Renan Flores | Approved unpaid type | Engine presently does not deduct |
| Active company loan | PAY-13 Josephine Castro | Mid-amortization | Half-cutoff amortization/outstanding |
| Loan exceeds net | PAY-14 Nestor Villanueva | Installment exceeds gross | Net clamp and deduction behavior |
| Final loan payment | PAY-15 Evelyn Manalo | Outstanding below installment | Cap at balance |
| Cash advance | PAY-16 Danilo Reyes | Active advance | Current engine takes full monthly amount each run |
| Salary change | PAY-17 Patricia Lim | Effective mid-cutoff | Effective segment prorating |
| Mid-period hire | PAY-18 Diego Bautista | Hire date inside cutoff | Eligible-day proration |
| Mid-period separation | PAY-19 Cora Mendoza | Final cutoff plus clearance | Proration/final-pay chain |
| Minimum wage | PAY-20 Marites Flores | At confirmed Cavite rate | Blocked pending authoritative rate/date |
| Higher earner | PAY-21 Victor Tan | Confirmed higher tax band | Approved table/tax band |
| Monthly pay type | PAY-22 Dennis Aquino | Monthly employee cycle | Monthly processing path |
| Semi-monthly pay type | PAY-23 Rosemarie Castillo | Semi-monthly cycle | Semi-monthly processing path |
| Pending profile change | PAY-24 Bianca Mercado | Change awaiting approval | No unapproved payroll mutation |
| Japanese manager | PAY-25 Kenji Saito | Fictional manager if HR model supports | No special tax assumption without policy |

Names are synthetic demo personas and must be paired only with reserved email domains and demo-marked synthetic IDs. They do not represent real employees.

Unpaid leave/absence deduction, minimum wage, expatriate treatment, holiday calendar and statutory loans remain explicit gaps. Do not alter data to conceal an app-vs-worksheet discrepancy.

## G. Realism and naming

- Fictional Filipino names with regional/surname variety; fictional Japanese managers only if model permits. Plausible Cavite/Laguna/Batangas/Metro Manila locations, not real private addresses.
- Default fictional automotive/motorcycle customers and suppliers; no real brands without confirmation.
- Emails use reserved .test domains such as ogami-demo.test. Phones are explicit non-routable placeholders. IDs are synthetic only, format-valid only if required, and flagged demo in safe metadata. Mail goes to local catcher/fake transport.
- Preserve validated item/UOM conventions and customer/supplier part cross-references. Model kg/lot, cavity, cycle, yield/scrap, packaging and shelf life only where supported.
- Demand seasonality/modest growth; overtime near delivery peaks; supplier delay/reject profiles and scrap clusters are deliberate and coherent.
- No negative stock, unbalanced journals, future-completed records, or weekend approvals without a valid calendar.
- Opening balances and transactions use domain actions. Raw/bulk inserts only for high-volume attendance/log history if later verifier proves invariants, with exception documented.

## H. Page × data coverage

All 369 literal paths are in 00-discovery grouped by route file. Detail/create/edit pages use the family’s lookups and records.

| Route file | Data coverage |
|---|---|
| accountingRoutes | COA, journals, bills/payments, invoices/collections, customers/vendors, credit notes, aging/statements |
| adminRoutes | users/roles/audit/sessions/export, DSS, alerts, health |
| advancedRoutes | forecasts, budgets/transfers, returns |
| assetsRoutes | assets/transfers/depreciation |
| authRoutes | demo accounts; recovery forms use fake mail |
| careersRoutes | jobs and staged applications |
| crmRoutes | customers/products/prices/orders/inquiries/complaints |
| dashboardRoutes | role layouts, widgets, KPIs, approvals, activity, notifications |
| driverRoutes | assigned deliveries, vehicles, proof |
| factoryRoutes | released WOs, output and QC |
| hrRoutes | org/employees/recruitment/training/attendance/leave/loans/separation/profile |
| inventoryRoutes | items/UOM/stock/locations/count/adjust/GRN/issues/MRB |
| landingRoutes | inquiries/newsletter; static home content |
| legalRoutes | static legal content, intentionally no list data |
| maintenanceMobileRoutes | assigned maintenance work/logs |
| maintenanceRoutes | equipment/downtime/maintenance WOs/PM |
| mrpRoutes | BOM/machines/molds/plans/runs/shortages |
| payrollRoutes | payroll periods/payslips/adjustments/statutory |
| portalRoutes | fake customer/supplier accounts tied to O2C/P2P |
| productionRoutes | schedules/WOs/operations/crews/output/defects |
| purchasingRoutes | requests/RFQ/quotes/PO/shipments/receipts/supplier performance |
| qualityRoutes | inspections/measurements/NCR/CAPA/PPAP/calibration |
| selfServiceRoutes | current employee DTR/leave/payslip/requests |
| supplyChainRoutes | deliveries/schedules/proofs/shipments/vehicles |

Data-bearing list/dashboard endpoints must return records for at least one authorized role in Phase 6. Static/legal/auth forms are intentional non-list exceptions.

## I. Status coverage matrix

Use every current enum in 00-discovery. For each workflow, seed valid historical terminal states plus current actionable states. All reachable status values must appear; code-only handoffs, inactive approval definitions, unsupported loan types and states that cannot be created through domain logic are documented exceptions. Phase 6 reports each enum/value with count and evidence. Seed no direct status mutations.

| Workflow family | Planned state coverage |
|---|---|
| Sales/order response/price/complaint | Every supported lifecycle status; include rejected/cancelled and open live response/complaint |
| PR/PO/RFQ/quote/supplier dispatch | Draft, pending, approved/rejected, sent/responded, partial/received/closed/cancelled as valid |
| Delivery/shipment/returns | Scheduled through transit/delivered/confirmed/returned/cancelled; return request and case lifecycles |
| Inventory/GRN/count/adjustment/transfer | Draft/pending/approved/rejected, accepted/partial/rejected QC, count/adjusted, transferred/completed/cancelled |
| Production/MRP/maintenance | All valid plan/run/WO/operation/schedule/report stages; open/assigned/in-progress/completed/cancelled maintenance |
| Quality/NCR/PPAP/effectiveness/calibration | Draft/in-progress/review/pass/fail; open/in-progress/closed; due/overdue; applicable effectiveness values |
| HR/attendance/leave/loan/profile/training/clearance | All enum values reachable in current services, including seven attendance statuses and terminal/rejected cases |
| Payroll/accounting/budget/payment | Period, invoice/bill, payment, journal, budget, transfer and adjustment states across history and current item |
| DSS/admin/assets/vehicle | Supported recommendation, asset/transfer, vehicle and admin lifecycle states |

## J. Planned planted issues

After approval, PLANTED-ISSUES.md will contain exact IDs, location, causal links and expected report/widget/DSS signal. Candidates: resin stockout, near-expiry lot, slow mover, late supplier, delivery-driven OT spike, downtime cluster on one press, mold defect/NCR, overdue CAPA, complaint within SLA, chronic lateness, >60-day AR and stuck approval/document. Every anomaly must remain internally consistent.

## K. Seeder disposition

00-discovery contains one row per existing seeder. Keep baseline/reference seeders distinct, preserve test factories/audit fixtures, rewrite legacy demo transaction builders and migrate test callers before archive/delete. DatabaseSeeder must make supported baseline and demo paths clear.

## L. Open questions (proposed defaults)

1. Defense date and rehearsal dates? Default: actual run date, reseed day before; no fixed date without explicit clock design.
2. Customer/supplier names? Default fictional industry-style names; no brands.
3. Statutory effective year/rates? Current app tables through 2025; provide/confirm intended policy year and values.
4. Holiday calendar and shift/rest patterns? Confirm authoritative dates and plant patterns; current holiday data is only 2026.
5. Pay frequency, salary grades, minimum wage/location/date, expatriate treatment, unpaid leave/absence rules? Use confirmed app config; do not guess missing rules.
6. Loan products? Company loan and cash advance only by default; statutory request paths are unsupported.
7. Approval limits? Selected flows use ₱50,000 in current source; confirm.
8. Which workflows get priority in live defense? Proposed: order→MRP→purchasing/receipt→production/quality→delivery/invoice; payroll; inventory; maintenance.
9. Are synthetic format-valid government IDs allowed when visibly flagged demo? Default: synthetic-only values and explicit demo marker, no real contact data.
10. Opening stock accounting counterpart? The current app marks stock openings `not_required` for GL and the former combined opening-balance workflow was removed. Default: create stock through the inventory movement service only; do not infer an equity account or create a balancing journal until Finance confirms the supported workflow and account mapping.
11. This plan was approved by the user before Phase 2; unresolved statutory, payroll, date, shift, customer/supplier, approval-limit, and opening-equity policy questions remain gated as documented above.
