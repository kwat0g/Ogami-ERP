# Workforce identity layer

`workforce-identities` builds the employee-linked actors needed by later approval and transaction layers, plus a scale-appropriate HR roster. It depends on `organization`; the application baseline must already contain roles, settings, shifts, leave types and the salary-adjustment approval workflow.

| Scale | Employees | Employee-linked login accounts | Named payroll-persona employees |
|---|---:|---:|---:|
| small | 21 | 21 | 4 shared with role accounts |
| demo | 241 | 241 | 25 |
| full | 241 | 241 | 25 |

The roster uses the first 21 names at small scale and all 241 supplied names at demo/full scale, with surname-first Gmail addresses. Duplicate name-derived addresses receive a numeric suffix. The 21 core actors cover all 17 current roles, with additional Finance, Purchasing, Maintenance and Warehouse actors for separate makers, approvers and stock-count finalizers; all remaining employees use the current `employee` role. Every user links to an Employee row. A subset of payroll personas shares the actor employee rows; all persona employees keep stable `payroll.PAY-NN` registry keys for later layers.

The supplied roster names are retained. Addresses, emergency contacts, phone values and other non-supplied profile details remain synthetic. Employee and login emails use the requested surname-first `@gmail.com` format and are not sent mail; the demo runner uses local mail/broadcast/queue drivers. Employee phone fields use deterministic, sequential 11-digit placeholders: employee contact `09000000NNN` and emergency contact `09000001NNN`, where `NNN` is the roster sequence. They match the current employee form's `09` format and are demo-only. Addresses use fictional illustrative streets paired with deterministic, city-matched barangay names in Cavite, Laguna, Batangas and Metro Manila. The roster names are not used to infer tax treatment.

New Employee records are created through `EmployeeService::create` under the employee's hire date; existing seed-owned records are updated through `EmployeeService::update` so the roster can be renamed without changing employee IDs or breaking linked history. This exercises application document sequencing, employment history, shift assignment, onboarding initialization, leave-balance initialization, audit observation and the durable `EmployeeCreated` outbox event. Employee-linked demo accounts are reference identity data written through the `User` model because the normal employee provisioner assigns only the `employee` role. Core actors retain their current role slugs; other employees receive the `employee` role. Accounts store a hash of the configured `DEMO_ACCOUNT_PASSWORD` and use the as-of timestamp.

On demo/full scale, active employees outside the supplied roster are archived through `EmployeeService::delete` in chunks of 50 after the roster accounts are provisioned. This preserves employee history and deactivates linked accounts through the normal service lifecycle. Small scale does not archive unrelated records.

While employees are created, a temporary container-scoped `SettingsService` override makes `hr.auto_provision_user.enabled` return false. The original service binding is restored in `finally`; the database setting is not changed. The `EmployeeCreated` outbox row and other listeners remain enabled. Historical notification delivery is suppressed through `DemoSeedContext::withoutNotifications()`, and the runner's side-effect scope keeps mail, broadcasts and queues local.

The application has no authoritative Ogami pay policy, so the salary values below are a demo-only schedule. The one floor is sourced from NWPC Wage Order IVA (CALABARZON)-23, effective 26 October 2026: its Region IV-A page lists ₱645/day for the Extended Metropolitan Area, and NWPC states this is ₱16,824/month on a six-day workweek. Dasmariñas is in that area classification. PAY-01 uses that monthly equivalent as the minimum-wage persona; all other amounts below are synthetic demo recommendations, not Ogami actuals, legal classifications, or a compensation survey. The dataset applies these rates from each employee's hire date so historical payroll can run; this is a demo history convention and does not claim that the rates were the employees' actual past pay.

| Demo position/persona | Monthly equivalent |
|---|---:|
| PAY-01 minimum-wage persona | ₱16,824 |
| Entry operator, warehouse, driver and admin roles | ₱18,000 |
| QC, maintenance, mold and IT technical roles | ₱24,000 |
| Office staff and officers | ₱28,000 |
| Supervisors and department heads | ₱42,000 |
| Managers | ₱60,000 |
| Chairman, President and non-Japanese Vice President | ₱95,000 |
| Japanese Vice President | ₱120,000 |

The current `PayType` enum controls storage: monthly employees receive `basic_monthly_salary`; semi-monthly employees receive exactly half of the monthly equivalent as `semi_monthly_rate`, and the model derives the monthly equivalent by doubling it. New hires get their initial pay through `EmployeeService::create`, which creates salary history. If an existing roster employee has no positive applicable pay, the layer requests a `SalaryAdjustment` and completes the active application approval chain using the configured role actors. Nonzero existing pay values are preserved. It does not change contribution tables, tax rules, holiday multipliers, overtime formulas, or any other statutory configuration.

Sources: [NWPC Region IV-A current minimum wage rates](https://nwpc.dole.gov.ph/region-iva/) and [NWPC release on Wage Order IVA-23](https://nwpc.dole.gov.ph/wage-hikes-approved-for-calabarzon-workers-kasambahays/). The wage order date/effectivity and official wage floor are included for traceability; demo values remain clearly identified as synthetic except for the minimum-wage persona amount.

Blank government-ID fields receive deterministic, visibly synthetic, length-valid placeholders: `999` + employee ID padded to 7 digits for SSS, `999` + employee ID padded to 9 digits for PhilHealth, `998` + employee ID padded to 9 digits for Pag-IBIG, and `997` + employee ID padded to 9 digits for TIN. They are test values, not issued IDs, and must never be used outside the demo. Any pre-existing nonblank government identifiers are preserved. Blank bank fields receive a deterministic name from a list of Philippine banks and a 12-digit numeric synthetic account string. These are not payable banking details. The layer submits bank changes through `ProfileUpdateRequestService`, then records separate HR and Finance approvals; nonblank bank details are preserved, except for the layer's exact legacy placeholders, which are replaced through the same approval flow. New requests use the readable note `Bank details update for employee {employee number}`. During migration the layer also recognizes the legacy `DEMO-WORKFORCE-BANK:{employee id}` note when its approved changes match the current generated values, preventing duplicate requests. Onboarding is recomputed after the approved synthetic values are present. Current employees start active. Separation statuses, clearance and final-pay history belong to later lifecycle layers.

The data model supports a department hierarchy and one department head, but no employee-to-employee manager relationship. This layer sets department heads through `DepartmentService` where a current position and employee exist; it does not invent individual reporting lines. The small scale intentionally has no MOLD or ADMIN employees, while demo/full use the approved headcount plan. A weekday hire date is selected from a seeded, weighted tenure distribution; the holiday calendar is not extended by this layer.

## Rebuild and verification

```sh
php artisan demo:seed --scale=small --only=workforce-identities
php artisan demo:verify
php artisan demo:credentials
```

The focused workforce test checks employee/account Gmail alignment, required employee profile values and shift assignments, pay by enum type, correctly shaped synthetic identifiers, the HR/Finance bank approval chain, preservation of pre-existing sensitive values and nonblank salaries, and idempotent request behavior. Every employee key remains stable; profile randomness is derived per key and global seed so higher-scale profiles preserve earlier values. Existing seed-owned employees are matched by their previous seed email, then updated through the employee service while retaining their IDs. Archived or conflicting identities stop the layer with a clear error.

Large-scale performance is not yet verified. A test exercising 200 employee service creates took 529.24 seconds before a later account timestamp failure; a subsequent demo-to-full integration attempt exceeded 14 minutes and was stopped without a result. See [F-020](FINDINGS.md#discovery-findings). The 25-employee transaction chunking controls rollback scope and memory, but it has not demonstrated the required full-seed runtime.
