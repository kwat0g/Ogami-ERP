# Warehouse Map Management and Capacity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let warehouse managers manage zones and bins from the Warehouse Map and review configured maximum kg against current kg stock.

**Architecture:** Reuse the existing zone/location API and `WarehouseService` rules. Add zone capacity storage and expose the existing bin capacity field, calculate occupancy from kg quantities and configured gross kg per base unit, and render CRUD controls and capacity usage in the map.

**Tech Stack:** Laravel, Eloquent, PostgreSQL migrations, React, TypeScript, React Query, React Hook Form, Zod.

**Spec:** `docs/superpowers/specs/2026-11-14-warehouse-map-capacity-design.md`

## Global Constraints

- Show utilization/status but do not block receipts or transfers above configured limits.
- Count kg stock directly; count non-kg stock only when an item gross kg weight per base unit is configured. Never invent item weights.
- Use the existing `inventory.warehouse.manage` permission and warehouse service safeguards.
- Null capacity means no configured limit.
- Seed unset bins with an editable 250 kg planning estimate and unset zones with the sum of their bin limits; label estimates as unverified.
- Store capacity basis as `unverified`, `estimated`, `derived`, or `rated`; keep derived zones synchronized with active bins.
- Mark inactive warehouses/bins and flag stock with unknown gross weight; identify the affected items in bin details.
- Require a capacity when the UI creates a zone or bin; allow existing capacities to be cleared when editing.

## Review Focus

- A zone containing multiple bins reports the sum of known kg stock weights in those bins; items with unknown weight are flagged and excluded.
- Null capacity remains unlimited and does not show a misleading percentage.
- Capacity values below or equal to zero are rejected, while a limit below current stock can still be saved and shown as over capacity.
- Deletes with stock, reservations, or pending adjustments continue to fail with the existing business error.
- A successful map edit refreshes both zone/bin occupancy and selected-bin details.
- Inactive warehouses/bins are visibly marked; non-kg stock exclusions are called out near capacity usage.
- Derived zone estimates follow active-bin changes; manual estimates and posted ratings do not.

---

### Task 1: Persist capacity and add kg occupancy to the map API

**Files:**
- Create: `api/database/migrations/2026_11_14_160000_add_capacity_kg_to_warehouse_zones.php`
- Modify: `api/app/Modules/Inventory/Models/WarehouseZone.php`
- Modify: `api/app/Modules/Inventory/Models/WarehouseLocation.php`
- Modify: `api/app/Modules/Inventory/Requests/StoreWarehouseZoneRequest.php`
- Modify: `api/app/Modules/Inventory/Requests/StoreWarehouseLocationRequest.php`
- Modify: `api/app/Modules/Inventory/Resources/WarehouseZoneResource.php`
- Modify: `api/app/Modules/Inventory/Resources/WarehouseLocationResource.php`
- Modify: `api/app/Modules/Inventory/Services/StockLocationSummaryService.php`
- Modify: `api/app/Modules/Inventory/Services/WarehouseMapService.php`
- Modify: `api/app/Modules/Inventory/Resources/WarehouseMapResource.php`
- Modify: `api/app/Modules/Inventory/routes.php`
- Modify: `api/database/seeders/WarehouseSeeder.php`

**Interfaces:**
- Add nullable `capacity_kg` decimal (10,2) to zones; use the existing nullable bin `capacity_kg` column.
- Add string `capacity_used_kg` values to map zones and bins, and to bin detail; `capacity_kg` stays nullable.
- Add `public function occupiedKg(Collection $stockLevels): string` to `StockLocationSummaryService`; sum stock-level quantities only when the related item's unit is `kg`.

- [x] Add the schema migration and model fillable/cast entries for zone capacity. Rollback removes the new zone field but preserves capacity values in the pre-existing bin field.
- [x] Backfill only null bin capacities to `250.00` and null zone capacities to the sum of active bin capacities; configure seeded locations the same way without overwriting existing values.
- [x] Validate optional nullable capacities as numeric positive decimals with at most two fractional digits and a maximum of `99999999.99` in zone and location requests.
- [x] Include configured capacities in structure resources; retain existing hash-id and permission behavior.
- [x] Implement `occupiedKg()` and populate zone/bin usage in `WarehouseMapService`; sum zone usage from its bins.
- [x] Return zone capacity and occupancy, bin capacity and occupancy, and bin detail occupancy from the map endpoints.
- [x] Allow users with `inventory.warehouse.manage` to load zone type options for the map forms.
- [x] Review null capacity, mixed units, and over-capacity behavior against the approved spec.
- [x] Run PHP syntax checks and `git diff --check` for the changed backend files.

### Task 2: Add map CRUD controls and capacity usage in both warehouse interfaces

**Files:**
- Create: `spa/src/pages/warehouse/map-management.tsx`
- Modify: `spa/src/pages/warehouse/map.tsx`
- Modify: `spa/src/api/inventory/warehouse.ts`
- Modify: `spa/src/types/inventory.ts`
- Modify: `spa/src/types/warehouse.ts`
- Modify: `spa/src/pages/inventory/warehouse/index.tsx`

**Interfaces:**
- Zone and location create/update payloads accept `capacity_kg: number | null`.
- Warehouse map zone/location types expose `capacity_kg` and `capacity_used_kg` as nullable/decimal strings.
- Management dialogs expose zone and bin create/edit forms and reuse existing delete API methods.

- [x] Add zone/bin create, edit, and delete controls in the map, gated by existing map permission.
- [x] Use confirmation dialogs and display server business-rule errors for blocked deletes.
- [x] Add optional positive kg capacity fields to map forms and the existing Warehouse Structure forms.
- [x] Show zone and bin occupied kg against configured limits; at 90% or greater, show the existing `Full` capacity status; allow visible over-capacity usage without blocking inventory workflows.
- [x] Label initial capacity values as editable planning estimates and direct administrators to confirm the rated load from posted equipment/floor ratings.
- [x] Invalidate the warehouse map query after mutations and clear or refresh stale selected-bin details.
- [x] Run the SPA TypeScript typecheck and `git diff --check` for changed frontend files.

### Task 3: Close warehouse map audit findings

- [x] Return warehouse active state and visibly distinguish inactive warehouses and bins on the map.
- [x] Count stocked lines with unknown gross weight and show the warning beside capacity totals; identify affected bin items and clarify packaging and non-enforcement limits.
- [x] Track capacity basis as unverified, estimate, derived, or entered from a posted rating; leave historical bin values unverified where provenance cannot be recovered.
- [x] Keep derived zone estimates synchronized after bin creation, deletion, restore, activation, move, and capacity edits; leave manual zone values independent.
- [x] Make the existing create/update request validation accept omitted parent IDs on updates while requiring them on creation.
- [x] Apply the capacity-status migration to the `ogami` database and verify migration status and aggregate values.
- [x] Run PHP syntax checks, SPA typecheck, and `git diff --check`.

## Verification constraint

The workspace instruction for this task prohibits adding or running tests unless requested. No tests will be added or run; use the syntax, typecheck, diff, and code-review steps above.

### Task 4: Include known item weights in capacity coverage

- [x] Add optional `items.weight_kg_per_unit` with API validation, resource serialization, item form, and CSV import support.
- [x] Keep the field blank for existing items until a user supplies a gross weight; do not create mock weight data.
- [x] Convert known non-kg stock into kg occupancy and count unknown-weight stock lines as omitted.
- [x] Show which stock lines are omitted because no gross kg weight is configured on both bin cards and selected-bin details.
- [x] Invalidate warehouse map/bin queries after item weight changes so capacity usage refreshes across clients.
- [x] Ensure the live development API sees migration files through its read-only migration mount; production images already copy the `api/` tree.
- [x] Apply the item-weight migration to `ogami` and verify its schema and migration record (batch 15).
- [x] Run PHP lint, SPA typecheck, and `git diff --check`; do not run tests unless requested.
