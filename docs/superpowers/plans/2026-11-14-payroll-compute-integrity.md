# Payroll compute integrity plan

## Goal

Make payroll computation predictable, complete, auditable, and consistent across regular runs, recomputes, statutory calculations, deductions, and 13th-month pay. Prevent payroll from being computed before its earning period has ended, while making the date rule and pay-period rules explicit and enforced by the server.

## Execution status

**Implementation substantially complete; not release-cleared.** The API blocks a November 1–15 computation attempted on November 3, including employee recompute and queue claim paths; the cutoff becomes eligible on November 16. Regular periods require canonical half-month ranges in Laravel and the SPA. Voluntary loan/cash-advance recovery uses a shared allocator, records requested/withheld/deferred amounts, and writes loan payments only for actual withholdings. Final pay uses the same cap and prevents repeat compute from collecting the same payout twice. Finalization and GL posting reject negative or unreconciled net pay instead of balancing it with an unrecovered-deduction line. Batch, direct employee, and annual calculation paths run anomaly detection; evaluations are fingerprinted and stale inputs block finalization. A read-only `payroll:integrity:preflight` report and period-detail evaluation status are implemented. Approved unpaid leave has a source-linked, target-cutoff adjustment flow and a hard finalization guard. Future annual runs are blocked, and a prior-year correction requires a previously voided annual run. Statutory schedule source coverage and the annual source cutoff/true-up still need compliance-owner evidence. Production preflight, shadow calculations, migration, and release have not been run.

The user approved the recommended policy defaults on 2026-11-14. Employer-specific statutory exceptions and the annual payment deadline still require source evidence and compliance-owner confirmation before release.

## Architecture and technical approach

- Keep payroll lifecycle rules in payroll domain services and enforce them on every mutation path. UI controls should explain and mirror those server rules, but are not the security or integrity boundary.
- Treat an approved payroll run as a calculation over a stable input set: period, attendance, adjustments, salary history, statutory schedule version, and policy version.
- Use explicit policy/version references and immutable calculation evidence where a later edit could otherwise change a computed result.
- Preserve existing claim fencing, maker-checker, status gates, adjustment locks, idempotent artifacts, and historical finalized/disbursed runs.
- Implement in dependency order. Product owners must decide the financial policies in Gate 0 before the dependent calculation changes begin.

## Tech stack and likely files

- Laravel services, jobs, requests, migrations, and feature tests under `api/app/Modules/Payroll`, `api/database`, and `api/tests/Feature/Payroll`.
- React/TypeScript period screens under `spa/src/pages/payroll/periods`.
- Existing payroll lifecycle, calculator, government schedule, GL, anomaly, and 13th-month services should be extended rather than introducing parallel calculation paths.

## Current evidence and scope

- `api/app/Modules/Payroll/Services/PayrollPeriodService.php` already has a server-side period-end guard and the SPA mirrors it. Keep the rule that a regular period is not computable until the day after `period_end`; add regression coverage for every compute entry point and date/timezone boundaries. The original example (November 1–15 computed on November 3) should be rejected by this rule.
- `PayrollPeriodService` and `spa/src/pages/payroll/periods/create.tsx` currently permit arbitrary ranges within a calendar half, while `api/app/Modules/Payroll/Services/PayrollCalculatorService.php` pays a full half-month salary for a full-period employee. This can overpay a range such as November 3–15 even when computation happens after November 15.
- Attendance affects several variable earnings and deductions, but unpaid leave is currently handled through manual payroll adjustment. Existing tests encode that behavior, so policy must decide whether to preserve it with a readiness control or automate the deduction.
- `GovernmentContributionTableImportService` validates rows, but can accept mixed or incomplete effective-date schedules; `GovernmentContributionTableService` reads active brackets per employee and does not pin a schedule version to the run.
- `PayrollCalculatorService` caps loan deductions by outstanding balance and amortization, but not by available earnings. Net pay is floored at zero and `PayrollGlPostingService` has a balancing path for unrecovered deductions; policy must define the intended allocation and ledger result.
- `ThirteenthMonthService` supports a configurable late window, reads annual tax inputs only from finalized/disbursed regular periods, and accrues basic pay. Annual cutoff, pending-period treatment, eligible earnings, and payment deadline need explicit policy. The official [DOLE Labor Advisory No. 21, series 2026](https://dole.gov.ph/php_assets/uploads/2026/11/LA-21-26-Guidelines-on-the-Payment-of-Thirteenth-Month-Pay.pdf) states the 2026 payment deadline as December 24; the compliance owner should confirm applicability and keep the payment deadline distinct from the date payroll is computed.
- Batch computation records anomaly evaluation, but single-row recompute and 13th-month paths can skip it. Finalization must require a current, successful evaluation or a recorded authorized override.

## Policy and owner gates

The user approved the recommended defaults. The following policy choices are now treated as approved for implementation; any employer-specific statutory or contract exception still needs source evidence:

1. **Regular periods:** Exact `1–15` and `16–last calendar day` ranges. Shorter ranges are not valid regular payroll. A separate off-cycle type and proration formula remain future work; do not invent one in the regular path.
2. **Unpaid absence:** Preserve manual payroll adjustment and require an approved adjustment before finalization when an absence is confirmed unpaid. Open or unapproved attendance affecting pay must block finalization until resolved.
3. **Statutory schedules:** Use the issuing agency's current circular/table as source of truth for SSS, PhilHealth, Pag-IBIG, and BIR. Publish immutable effective-dated versions and pin a run to those versions. Imports must retain source title/URL, effective date, and file hash; a correction is a new version.
4. **Deductions:** Compute statutory employee contributions and withholding tax first, then approved negative adjustments, then voluntary loan/cash-advance recovery in existing stable loan-ID order. Cap the voluntary recovery to available net pay; carry unpaid balance forward and record only amounts actually withheld. Keep the established company-loan limit of one month salary and one company loan plus one cash advance at a time. Do not create an arbitrary percentage cap from the anomaly threshold.
5. **13th month:** Use the statutory basic-salary basis unless a documented contract/company practice includes more. Require a complete, reconciled source-period set; do not silently omit pending payroll. Preserve the single active/separated payout path and the annual tax true-up. The compliance owner must verify the applicable year-specific deadline before release.
6. **Anomalies:** Every path needs a successful evaluation tied to current rows. Failed/stale evaluation blocks finalization; current unresolved anomaly flags continue to block under existing policy. Only an authorized payroll control owner may resolve/override with a reason and audit record.
7. **Existing runs:** Hold unsafe open drafts/computed/approved periods for review or recomputation under the new rules. Finalized/disbursed history remains immutable; corrections use existing void/adjustment controls.

Record the owner's approval, effective date, and evidence alongside the implemented payroll policy/configuration. Historical finalized/disbursed amounts remain unchanged.

## Implementation plan

### 0. Characterize behavior and settle policy gates

- [x] Review current period lifecycle and calculator behavior in `PayrollPeriodService`, `PayrollCalculatorService`, `ProcessPayrollJob`, `PayrollController`, and `ThirteenthMonthService`.
- [x] Create a decision record for Gate 0, including worked examples for normal periods, off-cycle ranges, unpaid absence, deductions exceeding net pay, annual tax inputs, and anomaly overrides.
- [ ] Inventory open periods and identify noncanonical ranges, missing schedule evidence, and runs with incomplete anomaly evaluation. Read only; do not mutate historical payroll.
- [x] Add or update characterization and regression tests for the approved behavior, preserving manual unpaid-leave adjustments.

**Exit:** all amount-changing decisions have named owners and approved values; remediation paths are agreed for open runs.

### 1. Enforce period dates and range semantics

- [x] Keep `PayrollPeriodService::assertPeriodEnded()` as the authoritative regular-run gate across create, initial compute, retry/recovery, employee recompute, and queue/service entry points. Use the payroll application's business timezone; computation is eligible only after `period_end`.
- [x] Enforce exact regular-period ranges in period creation and the domain service. Reject inverted dates, cross-month ranges, invalid calendar dates, and noncanonical ranges.
- [x] Keep arbitrary off-cycle ranges out of regular payroll; no separate off-cycle type or proration formula is approved.
- [x] Update period creation/detail messaging to explain why computation is unavailable and when it becomes eligible.
- [x] Add focused tests for the early-compute example, date boundaries, shortened ranges, and compute/recompute entry points.

**Likely tests:** `PayrollPeriodScopeTest`, `PayrollPeriodLifecycleTest`, `PayrollComputeLifecycleTest`, and SPA period creation/detail tests.

**Exit:** the Nov 3 example cannot compute through any path; approved valid periods compute at the correct boundary; invalid ranges cannot get full-period pay accidentally.

### 2. Make attendance and unpaid-absence readiness explicit

- [x] Keep unpaid absence manually valued; require an approved, applied adjustment linked to the source leave and target cutoff before finalization, and expose the missing source in anomaly details.
- [ ] Define finalized attendance/leave states and a cutoff for late edits. Reuse the attendance mutability and approval rules instead of creating a conflicting payroll-specific interpretation.
- [x] At compute/finalize, detect the covered open attendance and unpaid-leave inputs that can change pay; record source-linked anomaly evidence and invalidate stale evaluations when payroll inputs change.
- [x] Ensure a recompute cannot double-apply absence deductions and that approved corrections are reflected in the payslip, net pay, and GL handoff.

**Likely tests:** `PayrollCalculatorServiceTest`, `OpenAttendanceAnomalyTest`, attendance/leave approval and mutability tests, `PayrollRecomputeIntegrityTest`.

**Exit:** every unpaid absence affecting a run has a traceable approved payroll outcome; no open source silently produces a final amount.

**Current:** approved unpaid leave requires a manually valued adjustment linked to the leave request and the exact overlapping regular cutoff. A missing applied adjustment or a source that changed after calculation raises a blocking anomaly and independently blocks finalization. Recompute releases and reapplies the adjustment through the existing idempotent path. Open attendance remains blocking. Source snapshots and invalidation for other late attendance/leave changes, plus a policy cutoff for those edits, remain open.

### 3. Validate and pin statutory schedule versions

- [x] Introduce immutable schedule-version/publication records with effective date, agency, source/import metadata, independent publisher, and activation state. Link regular payroll periods to exact versions and expose the snapshot in the period detail.
- [x] Validate imports for one effective date per version, duplicate brackets, numeric row validity, and bracket overlap before publication. Validate source evidence and row-count integrity again at publish time.
- [ ] Define and apply an owner-approved completeness matrix for required bracket ranges, statutory floor/ceiling, and permitted gaps. No such matrix or applicable source set is recorded in the repository; publication requires an independent reviewer to attest that the imported schedule matches its cited source. Do not infer limits or rates in code.
- [x] Publish atomically after validation. Preserve prior versions; a correction is a new draft and publication supersedes the old same-date version. Failed imports/publications leave the active schedule unchanged.
- [x] Prevent publication or edits from changing a pinned run. Periods pin exact effective versions before compute claim; recompute validates and reuses those pins, including versions later marked superseded. Versioned rows and pins are immutable through application services/models.
- [x] Update calculator guards to require all four published agency versions, verify pin-to-version and row-count integrity, and calculate using the pinned version IDs.
- [x] Update the statutory UI/API to import source evidence, show version history/hash/status, support independent publication, and show each period's pinned version snapshot.

**Likely tests:** `ImportGovContributionTableTest`, `EffectiveDatedBracketsTest`, `MissingGovTableComputeBlockTest`, `GovernmentTableRealtimeTest`, and concurrency/claim-fencing tests.

**Current:** mixed-date, duplicate, overlapping, invalid-row, incomplete-agency, unreviewed, and row-count-mismatched schedules cannot reach payroll computation; failed imports/publications are atomic; statutory calculations use period-pinned version rows. A completeness attestation is retained, but objective range/coverage validation remains open until an owner-approved agency matrix and source coverage rules are supplied.

### 4. Apply deduction caps and reconcile ledgers

- [x] Encode the approved deduction waterfall and cap in a single allocation service shared by regular payroll and final pay.
- [x] Allocate only against policy-defined available earnings; distinguish mandatory deductions, voluntary deductions, and loan recovery. Store requested, withheld, and deferred amounts separately with reasons.
- [x] Create loan-payment ledger entries only for actual withheld amounts. Carry forward the remaining regular-pay balance; final-pay residue continues to require an explicit manual settlement or write-off before separation can finalize.
- [x] Remove the “Unrecovered Deductions” GL balancing line. Finalization and GL posting reconcile gross, deductions, signed adjustments, net pay, loan allocations, and payment ledger.
- [x] Define negative/zero net handling and approval requirements. Preserve money precision and idempotency across retries/recomputes.

**Likely tests:** `PayrollCalculatorServiceTest`, `PayrollGlPostingTest`, `PayrollUnclassifiedDeductionTest`, loan payment/ledger tests, `PayrollRecomputeIntegrityTest`, and disbursement reconciliation tests.

**Exit:** no deduction or loan recovery exceeds the approved cap; every withheld amount reconciles across payslip, ledger, bank/disbursement, and GL.

### 5. Close the 13th-month annual calculation consistently

- [x] Restrict annual runs to the current year; refuse future years and require a prior voided annual run before computing a historical year. Store the computation policy version on the annual run.
- [ ] Confirm the allowed compute/payment window and source period cutoff, including any provisional payment and true-up behavior.
- [ ] Apply the approved earnings components to the annual accrual calculation and make the payslip/report explain the included components and source payroll periods.
- [ ] Reconcile pending, computed, approved, finalized, and disbursed regular periods before annual calculation. Either block until the annual source set is complete or use an approved accrual/true-up process; do not silently omit pending periods.
- [ ] Ensure active and separated employees are covered by exactly one 13th-month payout path, including final pay, with no duplicate accrual or omission.
- [ ] Apply the approved annual tax true-up basis and date controls. Store source-period and policy-version evidence on the annual run.

**Likely tests:** `ThirteenthMonthTest`, `ThirteenthMonthAccrualInvariantTest`, `FinalPayService` tests, and payroll/tax export tests.

**Exit:** the annual run is limited to an approved year/window, fully reconciles its source periods, and pays each eligible employee once under an approved earnings and tax policy.

### 6. Require complete anomaly evaluation on every calculation path

- [x] Use one anomaly evaluation service for batch compute, single-employee recompute, recovery, and 13th-month runs. Persist evaluation status, calculation/input fingerprint, policy version, evaluated timestamp, findings, and failure details.
- [x] Recompute or invalidate the evaluation whenever payroll rows, source inputs, eligible roster, or relevant policy versions change.
- [x] Block finalization when evaluation is absent, failed, stale, or has unresolved findings. Existing role-authorized, reasoned anomaly resolution remains the audited review path.
- [x] Ensure a failed detector cannot appear as a clean result; retain findings as reviewable evidence and show evaluation status in the period detail.

**Likely tests:** `PayrollAnomalyPolicyTest`, `PayrollAnomalyReviewTest`, `PayrollRecomputeIntegrityTest`, `PayrollPeriodLifecycleTest`, plus 13th-month lifecycle tests.

**Exit:** every finalizable run has a successful evaluation tied to its current exact inputs, or a valid documented override.

**Current:** batch, employee-level recompute, recovery, and 13th-month computation invoke the shared anomaly evaluator. Persisted fingerprints cover payroll rows, relevant source inputs, eligible employees, and policy/schedule versions. Failed, missing, stale, or unresolved evaluations prevent finalization.

### 7. Migrate, release, and verify

- [ ] Run read-only preflight reports for open periods, schedules, loan deductions, annual runs, and anomaly states; classify each record under the approved remediation matrix.
- [x] Add backward-compatible schema/config changes and backfill only where evidence is deterministic. Do not invent versions for incomplete historical imports; mark those records as legacy/unverified and prevent them from silently serving as current evidence.
- [ ] Shadow-calculate representative payrolls in a controlled environment and compare gross, deductions, net, statutory amounts, 13th-month accrual, GL entries, and bank totals. Resolve every variance with an owner before activation.
- [ ] Release in dependency order: policy/config and schedule version support; period/range and attendance gates; deduction allocation; annual calculation; anomaly finalize gate. Coordinate queue worker restarts and ensure jobs carry pinned version identifiers.
- [ ] Keep finalized/disbursed payroll immutable. Put unsafe drafts/computed/approved runs on hold for explicit review or recompute; use established void/adjustment paths for corrections.
- [ ] Verify authorization, audit evidence, retry/idempotency, exports, payslips, GL, bank files, and operational dashboards. Monitor blocked compute reasons, schedule validation failures, deduction carry-forward, annual mismatches, and anomaly scan failures.
- [ ] Prepare rollback by disabling new run creation or reverting feature gates while preserving published schedule versions and immutable run evidence. Do not roll back by deleting payroll ledger or audit records.

## Global constraints

- Do not change finalized or disbursed historical payroll amounts in place.
- No financial formula, statutory table, deduction priority, or legal deadline is inferred from code defaults; it needs documented owner approval and applicable compliance review.
- Server-side guards remain authoritative even when the SPA disables actions.
- Every compute/recompute path must be idempotent, fenced against concurrent claims, and tied to the same input and policy versions.
- Keep employee-level payroll and attendance data behind existing payroll permissions and audit detail access.

## Review focus

- Check that each gate has an owner and explicit approval evidence before amount-affecting code is merged.
- Verify a direct endpoint/job/service call cannot bypass period-end, range, input-readiness, schedule-version, deduction, or anomaly gates.
- Review concurrent changes to attendance, adjustments, salary history, statutory schedules, and payroll rows during compute.
- Reconcile one representative payroll from source inputs through payslip, loan ledger, disbursement/bank artifact, and GL.
- Check that all six findings have an acceptance check and that old completed payroll remains reproducible from retained evidence.

## Verification commands for implementation

Run targeted tests after each phase, then the full payroll feature suite and relevant SPA checks. Candidate targeted commands include:

```bash
cd api && php artisan test --filter='PayrollPeriod|PayrollCompute|PayrollCalculator|ImportGovContribution|EffectiveDatedBrackets|ThirteenthMonth|PayrollAnomaly|PayrollGlPosting'
cd spa && npm run typecheck
```

Add browser/E2E coverage for the compute eligibility and period-creation flow if the existing harness supports it. These commands are implementation-time checks; they were not run while preparing this plan.
