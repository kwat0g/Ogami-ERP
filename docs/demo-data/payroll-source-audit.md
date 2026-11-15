# Payroll rule sources found in the application

This is a source-code audit, not confirmation that the seed values match current Philippine statutory guidance. The application reads payroll contribution rows from `government_contribution_tables`, with one effective schedule per agency: `GovernmentContributionTableService::bracketsEffectiveOn()` chooses the latest active `effective_date` on or before the payroll date. If none qualifies, it falls back to the active rows. Admin edits to the table can therefore change runtime results.

## Statutory contribution and tax rows

| Agency | Code source and effective date | Seeded basis |
| --- | --- | --- |
| SSS | `GovernmentTableSeeder`, 2024-01-01 | 53 flat-contribution brackets. EE amounts run ₱180–₱1,350; ER amounts ₱390–₱2,910. The seeder comments describe these as regular employee SS + EC + WISP. |
| SSS | `GovernmentTable2025Seeder`, 2025-01-01 | 61 monthly salary credit steps from ₱5,000 to ₱35,000 in ₱500 increments. EE is 5% and ER 10% of MSC; amounts run ₱250–₱1,750 and ₱500–₱3,500. The lowest bracket begins at zero; the top extends to ₱999,999.99. |
| PhilHealth | `GovernmentTableSeeder`, 2024-01-01 | Monthly basis floor ₱10,000 and ceiling ₱100,000; stored EE and ER rates are each 0.0225. That totals 4.5%, although the seeder comments call it a 5% premium split at 2.5% each. This is an internal code inconsistency. |
| PhilHealth | `GovernmentTable2025Seeder`, 2025-01-01 | Same floor and ceiling; stored EE/ER rates are each 0.025. |
| Pag-IBIG | Both seeders, 2024-01-01 and 2025-01-01 | EE rate 1% for salary basis through ₱1,500.00 and 2% above; ER is 2% in both brackets. `SettingsSeeder` sets the monthly compensation ceiling to ₱10,000. |
| BIR withholding | `GovernmentTableSeeder`, 2018-01-01 and 2023-01-01 | Six semi-monthly brackets for each date. The code comments attribute these to BIR RR 11-2018 Annex D (2018 schedule) and Annex E (2023 schedule). The calculation is fixed tax plus rate-on-excess. No later BIR schedule is seeded by `GovernmentTable2025Seeder`. |

The 2024 seeder comments name an SSS schedule effective January 2024, the 2024 PhilHealth premium and Pag-IBIG rates. The 2025 seeder comments name SSS Circular 2024-006, a PhilHealth 2024 circular and an HDMF memorandum circular. Those comments do not provide direct source URLs or enough citation detail to verify the 2025 values; the 2025 seeder explicitly says to re-verify its values before live filing. Treat these as the app's stored demo rules only. No 2026 contribution schedule is in the inspected seeders.

The payroll calculator applies SSS, PhilHealth, Pag-IBIG and BIR on first-half regular payroll rows only; BIR computation rejects non-semi-monthly periods. Current `SettingsSeeder` defaults say semi-monthly, cutoffs on the 15th and 31st, and a ₱10,000 Pag-IBIG cap. Because database settings are editable, inspect the target environment's values before computing a real payslip.

## Attendance-derived payroll defaults

The app defaults found in `SettingsSeeder` and migration `0296_seed_attendance_labor_rule_settings.php` are:

- Daily rate divisor: 22 workdays; hourly divisor: 8 hours.
- Ordinary-day OT multiplier: 1.25; rest-day/holiday OT multiplier: 1.30.
- Night differential: 0.10; night band: 22:00–06:00.
- Regular holiday rates: 2.00, or 2.60 when it is also a rest day.
- Special non-working holiday rates: 1.30, or 1.50 when it is also a rest day.
- Rest-day rate: 1.30; half-day threshold: 50% of scheduled work minutes.
- Automatic OT detection defaults enabled, with a 30-minute threshold; approved OT is capped at 240 minutes/day.

These are configurable application defaults, not independent confirmation of statutory rules. `HolidaySeeder` contains a hardcoded list for 2026 only. The latest read-only database audit found no current holiday rows, so the 2026 calendar must be seeded before the app can use it, and no later-year calendar is supplied.

`PayrollCalculatorService` currently computes flat cutoff basic pay for both pay types. It aggregates DTR overtime, night-differential, holiday/rest-day premiums, tardiness and undertime; paid leave is already inside basic pay and the dedicated `leave_pay` column remains zero. `LeaveTypeSeeder` defines only paid leave types. It provides no unpaid-leave type or unpaid-leave deduction rule. An absence or an unpaid leave cannot be expected to reduce basic pay in the current calculator; do not create a payroll worksheet that assumes it does.

## Environment snapshot and next step

The earlier read-only audit reported that the current `ogami` database had BIR effective rows but no SSS, PhilHealth or Pag-IBIG rows, and no holiday rows. This implementation did not query or write the live database. Confirm those counts in the target environment before demo-day payroll.

For a defensible independent payslip worksheet, first confirm an authoritative effective-year contribution schedule and holiday calendar, seed or enter those values through the app's existing configuration, and reconcile the PhilHealth 2024 mismatch. Keep payroll seeding separate from `HrTransactionHistoryLayer`: the HR layer intentionally creates attendance, leave and loan records only.
