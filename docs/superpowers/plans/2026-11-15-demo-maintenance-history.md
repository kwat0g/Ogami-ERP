# Demo Maintenance History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add deterministic, time-correct demo maintenance work orders across every status supported by the current application.

**Architecture:** Add a `maintenance-history` demo layer that depends on existing equipment, workforce, PM schedules, spare-parts catalog, and stock layers. It will create and advance orders through `MaintenanceWorkOrderService`, `MaintenancePartRequestService`, and existing stock issuance logic; no transactional table rows will be inserted directly. A feature test will seed the layer through `DemoSeedRunner` and verify status coverage, linked downtime/parts/logs, timestamps, and replay stability.

**Tech Stack:** Laravel 11, PHP 8.3, PostgreSQL 16, existing demo-layer framework, PHPUnit.

**Spec:** `docs/demo-data/01-data-plan.md`, Maintenance module plan and scenario catalog.

## Global Constraints

- Transactions use application domain services and state transitions.
- Dates are controlled by the as-of option and timestamps are time-correct.
- Seed data is deterministic, idempotent, synthetic, and guarded to local/demo databases.
- Do not change application logic to accommodate demo seeding.
- Verify only against the explicitly allowlisted `ogami_test` database.

## Review Focus

- A live assignment or start may depend on technician shift presence and eligibility; exercise the real attendance-aware assignment workflow.
- Spare-part issues must reconcile stock movement, handover, usage, and work-log links.
- Calendar and usage-based PM orders must respect due checks and keep schedule history coherent.
- Historical lifecycle timestamps must not drift on replay or appear after the as-of date.
- Machine downtime and machine status must follow the work-order lifecycle and reconcile when work completes or is cancelled.

---

### Task 1: Add the maintenance history layer

**Files:**
- Create: `api/database/seeders/Demo/MaintenanceHistoryLayer.php`
- Modify: `api/config/demo.php`
- Test: `api/tests/Feature/Demo/MaintenanceHistoryLayerTest.php`
- Create: `docs/demo-data/maintenance-history.md`
- Modify: `docs/demo-data/02-architecture.md`
- Modify: `docs/demo-data/PROGRESS.md`

**Interfaces:**
- Consumes `DemoSeedContext`, `MachineService` (breakdown event/outbox path), `MaintenanceWorkOrderService`, `MaintenancePartRequestService` (which invokes the spare-part usage and stock movement path), and `StockLocationSummaryService`. It reads due schedules created by the maintenance master-data layer; it does not create schedule rows directly.
- Produces layer key `maintenance-history` with every supported `MaintenanceWorkOrderStatus` represented when business rules permit.

- [x] Write a failing integration test that seeds through `DemoSeedRunner` and asserts five-status coverage, service-generated logs and machine downtime, linked spare-part issue/handover, as-of timestamps, and stable fingerprints after replay.
- [x] Run the focused test against the isolated `ogami_test` database and confirm the expected failure.
- [x] Implement deterministic historical completed/cancelled records and live open/assigned/in-progress scenarios through the current maintenance services; create a due-PM record from an actually due schedule.
- [x] Run the focused test and formatting/static checks; correct issues without bypassing application rules.
- [x] Run guarded small-scale console seed and full dependency replay against `ogami_test`, then reconcile orders, schedules, downtime, logs, stock movements, usages, and handovers read-only.
- [x] Document supported coverage, planted scenarios, and any blocked states in the module note and `PROGRESS.md`; create `FINDINGS.md` entries only for reproducible application gaps.
