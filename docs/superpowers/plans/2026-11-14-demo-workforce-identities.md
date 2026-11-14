# Demo Workforce Identities Implementation Plan

> **For agentic workers:** This plan implements the approved Phase 1 plan in `docs/demo-data/01-data-plan.md`. Use the repository's Laravel TDD workflow and the existing native Codex execution path; each task ends in a focused reviewable change.

**Goal:** Add a deterministic, safe demo layer with scale-sized employee rosters and employee-linked internal role accounts.

**Architecture:** Depend on the `organization` layer and create employee records with `EmployeeService`, preserving its sequence, employment-history, onboarding, shift and leave behavior. Create only the internal demo credential masters through the user model because the app's account services generate random temporary passwords/invite tokens; use the required configured shared demo password, with its one-way framework hash.

**Tech Stack:** Laravel 11, PHP 8.3, PostgreSQL, Eloquent services, Carbon test clock, PHPUnit.

**Spec:** `docs/demo-data/01-data-plan.md`, sections B, C, E, G, H and L; current app definitions in `OrganizationReferenceDataLayer`, `RolePermissionSeeder`, `EmployeeService`, `User` and `PayType`.

## Global Constraints

- Use the application's current role catalog, department/position masters, `EmployeeService`, and `PayType` enum.
- All internal demo emails end in the reserved `.test` domain; use no real phone or government identifier values.
- Do not set compensation, bank details or government IDs without an authoritative source; payroll remains gated on the approved plan's open policy questions.
- Use Asia/Manila dates relative to `DemoSeedOptions::asOf`, controlled time, fixed-seed Faker, and idempotent stable identities.
- Scale counts follow the approved plan: small 20; demo 200; full 240, with current roles and approver multiplicity represented at every scale.
- Do not run the full PHP suite or any database reset for this unit; targeted tests run sequentially on the isolated test DB.

## Review Focus

- Missing demo password must fail before creating employees or accounts; pin with a no-partial-write test.
- Missing role, department or position master must produce a keyed failure rather than silently omit a role actor; pin with a dependency assertion test.
- Rerun must not duplicate employee, user, employment-history, onboarding or shift rows; pin by comparing counts and stable lookup identities.
- Dates and service-generated timestamps must follow the selected hire date/as-of clock; pin created-at and employment-history assertions.
- Employee PII must remain synthetic or null and all emails must be `.test`; pin assertions over every roster row and account.

---

### Task 1: Workforce identity layer

**Files:**
- Create: `api/database/seeders/Demo/WorkforceIdentityLayer.php`
- Modify: `api/config/demo.php`
- Test: `api/tests/Feature/Demo/WorkforceIdentityLayerTest.php`
- Modify: `docs/demo-data/02-architecture.md`, `docs/demo-data/PROGRESS.md`

**Interfaces:**
- Consumes: `DemoSeedContext`, `EmployeeService::create(array): Employee`, `Role`, `Department`, `Position`, `User`, `PayType`.
- Produces: layer key `workforce-identities`, module `HR` and `Admin/Auth`, dependency `organization`; deterministic employees and configured internal role actors discoverable by email/role.

- [x] Write focused tests for small-scale service behavior, safe fields, missing password guard, idempotence, and planned roster sizes at all scales. Large-scale database writes are excluded from routine tests after the integration attempt exceeded the runtime budget; see F-020.
- [x] Run the initial test first and confirm it failed because the layer was not registered/implemented.
- [x] Implement roster distributions and role actor mapping; create each employee through `EmployeeService` at its hire instant and link its role account with the configured demo password.
- [x] Register the layer and verify the small-scale service path, timestamps, account safety and no duplicate service-derived rows on rerun. Full-scale write throughput remains unverified.
- [x] Run Pint on changed PHP files and focused tests sequentially; update architecture/progress docs with the account-master exception and compensation limitation.
- [ ] Commit and open a stacked draft PR on the current demo-data branch.

**Implementation choices:** User account hashes are not byte-for-byte deterministic because Laravel salts password hashes; business identity, row counts, dates, roles and non-secret attributes are deterministic. The verifier should normalize password hashes when computing dataset checksums. Employee salaries, government IDs and bank data remain null until their sources are confirmed. One account is created for every current internal role, with additional same-role users for maker/checker workflows.
