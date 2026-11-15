# Payroll compute integrity decisions

**Status:** Approved implementation defaults; release gates remain where source evidence or a compliance decision is still required.

**Approval record:** The user approved the payroll compute recommendations in this conversation on 2026-11-15. The repository does not identify the user's legal/compliance authority, so this records product approval and does not attest to employer-specific legal applicability.

## Approved defaults

1. Regular payroll uses exact calendar cutoffs: days 1–15 and day 16 through month end. No regular-period proration is allowed. A shorter or off-cycle run needs a separately approved type and formula.
2. Regular computation starts the day after the cutoff ends, using the application business timezone.
3. Confirmed unpaid leave stays manually valued. It needs an approved, applied payroll adjustment linked to the leave request and exact cutoff before finalization.
4. Apply statutory employee deductions and withholding first, approved negative adjustments next, and voluntary loan/cash-advance recovery last in stable loan-ID order. Cap recovery to available net pay. Carry the remainder and record only actual withheld payments.
5. Reject finalization and GL posting when gross, deductions, signed adjustment, and net pay do not reconcile or raw net pay is negative. A net-pay floor is not an accounting treatment.
6. Require a successful anomaly evaluation tied to the current payroll inputs and policy before finalization. Failed, missing, stale, or unresolved findings block finalization.
7. Do not rewrite finalized/disbursed historical payroll. Correct it through the established void and adjustment lifecycle.

## Worked examples

### Early computation

For a November 1–15 cutoff, a compute request on November 3 is refused. The first eligible date is November 16 in the payroll application's business timezone.

### Voluntary loan recovery cap

Given gross pay of 1,000, mandatory deductions of 100, an approved negative adjustment of 50, and loan requests of 600 then 400, available recovery is 850. The allocator withholds 600 and 250, defers 150, and records loan payments only for the 850 withheld. Net pay is zero.

### Negative raw net

If mandatory deductions plus an approved negative adjustment exceed gross pay, voluntary recovery is zero. The calculation may be reviewed and corrected, but finalization and GL posting refuse a negative raw net or a zero-floor mismatch.

### Annual source periods

The annual run refuses to proceed while a known regular period in that calendar year is still draft, processing, computed, or approved. It refuses future years; a historical year requires a previously voided annual run as correction authorization. The annual run stores the computation policy version and fingerprints source periods and payroll rows used. It does not infer a cutoff for periods that have not been created or an annual true-up schedule.

### Stale evaluation

If a payroll row or currently eligible employee roster changes after anomaly evaluation, the fingerprint changes and finalization is refused until recomputation produces a current evaluation.

## Release gates requiring further evidence

- Compliance owner: approve the statutory schedule coverage matrix for each agency, including applicable floors, ceilings, and permitted gaps. Schedule publication retains independent source attestation until then.
- Payroll/compliance owner: define the 13th-month source cutoff and any provisional-payment/true-up process that meets the applicable deadline. A full calendar-year source set and a December 24 payment deadline can conflict; do not resolve that conflict by silently omitting later earnings.
- Payroll operations: run the read-only preflight on the intended production database, review its classifications, and authorize remediation. The command is read-only; this implementation did not run a production migration or alter production data.
- Payroll and accounting owners: shadow-calculate representative employer payrolls and reconcile payslips, loan ledgers, bank totals, and GL before release.

## Source note

The official [DOLE Labor Advisory No. 21, series 2026](https://dole.gov.ph/php_assets/uploads/2026/11/LA-21-26-Guidelines-on-the-Payment-of-Thirteenth-Month-Pay.pdf) sets a December 24 payment deadline for 2026. The effective employer-specific source cutoff and true-up treatment still need compliance confirmation.
