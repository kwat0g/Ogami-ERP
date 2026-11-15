# Workforce identity layer

`workforce-identities` builds the employee-linked actors needed by later approval and transaction layers, plus a scale-appropriate HR roster. It depends on `organization`; the application baseline must already contain roles, settings, shifts and leave types.

| Scale | Employees | Internal role accounts | Named payroll-persona employees |
|---|---:|---:|---:|
| small | 21 | 21 | 4 shared with role accounts |
| demo | 201 | 21 | 25 |
| full | 241 | 21 | 25 |

The roster uses current role slugs, department codes, position titles, employment types and pay-type enum values. The 21 accounts cover all 17 current roles, with additional Finance, Purchasing, Maintenance and Warehouse actors to support separate makers, approvers and stock-count finalizers. In particular, `warehouse.staff@accounts.ogami-demo.test` creates and counts, `system.admin@accounts.ogami-demo.test` approves the variance, and `warehouse.supervisor@accounts.ogami-demo.test` completes the count after all lines are recorded. Users link to real Employee rows. A subset of the later payroll personas shares those employee rows: Ana Mae Villanueva (`employee`), Bianca Mercado (`hr_officer`), Victor Tan (`finance_officer`) and Kenji Saito (`vice_president`). The other named payroll identities have stable `payroll.PAY-NN` keys in the demo record registry for later layers.

All names and contact details are synthetic. Employee and login emails use reserved `.test` subdomains. Telephone values use the non-routable demo pattern `+63 000 555 ####`. Addresses use explicitly illustrative streets and barangays in Cavite, Laguna, Batangas and Metro Manila. Kenji Saito is the sole Japanese-nationality persona in this layer; no tax treatment is inferred from nationality.

Employee records are created through `EmployeeService::create` under the employee's hire date. This exercises application document sequencing, employment history, shift assignment, onboarding initialization, leave-balance initialization, audit observation and the durable `EmployeeCreated` outbox event. The 21 privileged/internal demo accounts are created directly with the `User` model as reference identity data. `UserAdminService::createForEmployee` issues an unpredictable temporary password, marks it for forced change and requires a privileged actor; the built-in employee auto-provisioner assigns only the `employee` role and also issues a random password. Neither path can create the complete role matrix with the configured deterministic demo credential. The direct account write is therefore limited to the non-transactional, demo-only login identities; it links every user to the Employee, stores a hashed configured `DEMO_ACCOUNT_PASSWORD`, sets the role, and uses the as-of timestamp.

While employees are created, a temporary container-scoped `SettingsService` override makes `hr.auto_provision_user.enabled` return false. The original service binding is restored in `finally`; the database setting is not changed. The `EmployeeCreated` outbox row and other listeners remain enabled. Historical notification delivery is suppressed through `DemoSeedContext::withoutNotifications()`, and the runner's side-effect scope keeps mail, broadcasts and queues local.

Salary fields, salary history, government identifiers and banking fields remain null. Phase 1 leaves compensation and policy inputs unresolved, and the user profile-change flow is the required path for later banking changes. Consequently, onboarding can truthfully show incomplete government-ID and banking steps; this layer does not mark those steps complete or fabricate sensitive values. Current employees start active. Separation statuses, clearance and final-pay history belong to later lifecycle layers.

The data model supports a department hierarchy and one department head, but no employee-to-employee manager relationship. This layer sets department heads through `DepartmentService` where a current position and employee exist; it does not invent individual reporting lines. The small scale intentionally has no MOLD or ADMIN employees, while demo/full use the approved headcount plan. A weekday hire date is selected from a seeded, weighted tenure distribution; the holiday calendar is not extended by this layer.

## Rebuild and verification

```sh
php artisan demo:seed --scale=small --only=workforce-identities
php artisan demo:verify
php artisan demo:credentials
```

The focused workforce tests pass (3 tests / 269 assertions). They verify small-scale safety, idempotency and dependent-service history, and check planned profile counts and unique emails at all three scales without performing the expensive large-scale write path. The current roster has 21 / 201 / 241 employees and 21 internal role accounts; one extra warehouse account provides a finalizer distinct from the stock-count maker and variance approver. The earlier 20-person console result and SQL snapshot predate that added account. The latest guarded small scenario smoke completed all nine selected/dependency layers on `ogami_test` with the revised roster. Every employee key is a stable email; profile randomness is derived per key and the global seed so adding higher-scale staff preserves earlier profiles. Existing seed-owned employees must match their deterministic department, position, hire date, pay type and name; archived or conflicting identities stop the layer with a clear error rather than being silently replaced.

Large-scale performance is not yet verified. A test exercising 200 employee service creates took 529.24 seconds before a later account timestamp failure; a subsequent demo-to-full integration attempt exceeded 14 minutes and was stopped without a result. See [F-020](FINDINGS.md#discovery-findings). The 25-employee transaction chunking controls rollback scope and memory, but it has not demonstrated the required full-seed runtime.
