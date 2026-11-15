# Warehouse map management and capacity

## Intent

Let warehouse managers add, edit, and delete zones and bins from the Warehouse Map, and see how much kg capacity each zone and bin uses. This keeps routine layout management beside the floor map.

## Goals

- Provide zone and bin create, edit, and delete actions in the Warehouse Map for users with `inventory.warehouse.manage`.
- Reuse the existing warehouse API and service rules, including delete restrictions for locations or zones with stock, reservations, or pending adjustments.
- Allow a nullable positive `capacity_kg` on zones and bins. Bins already have a database field; expose it through validation, models, and forms. Add the equivalent field for zones.
- Initialize unconfigured bins with a 250 kg planning estimate and initialize each zone's estimate as the sum of its bins. Preserve any capacity already configured.
- Require a capacity when creating a zone or bin in the UI; start a new bin at the editable 250 kg estimate, and let existing limits be cleared during edits.
- Show occupied kg and configured maximum kg for zones and bins. Count kg stock directly and convert other stock using an optional gross kg weight per item base unit; leave capacity unconfigured when there is no limit.
- Treat capacity as informational. Show utilization/status but do not block receipts or transfers above a configured limit.
- Clearly mark inactive warehouses and bins so they are not mistaken for available locations.
- Store capacity basis as unverified, planning estimate, derived from active bins, or entered from a posted rating.
- Keep derived zone estimates synchronized with active-bin changes; keep manually entered zone limits independent.
- Show the count of non-kg stock lines with no configured gross weight that are excluded from kg utilization, and list each affected item on its bin card and in bin details. Gross item weights should include packaging that rests on the rack; the system cannot infer missing packaging, pallet tare, or loose packaging weight.

## Non-goals

- Capacity conversion for items stored in units other than kg.
- Enforcing capacity limits in stock receipt, transfer, issue, or adjustment workflows.
- Changing warehouse structure permissions or deletion policy.
- Treating estimated capacities as equipment-certified load ratings.

## Approaches

1. **Manage directly in the map (selected):** Add management controls and forms to the map while reusing the existing zone/location endpoints. This matches the requested workflow and keeps stock-aware server rules in one place.
2. **Link to Warehouse Structure:** Add a shortcut from the map to the existing structure page. This avoids a second set of forms but takes the user away from the map while editing.

The request names the Warehouse Map as the place for these actions, so direct map management is the chosen approach.

## Implementation notes

- Add independent zone and bin `capacity_kg` fields with server validation and existing decimal formatting.
- Add `capacity_status`; existing bin values stay unverified where their historical source cannot be recovered.
- Mark prior zone totals as derived when they match the active-bin sum and recalculate those totals after bin create, delete, restore, activation, move, or capacity edits.
- Backfill only unset bin limits to 250 kg and unset zone limits to the sum of their active bin limits; leave existing configured values intact. New bins start at the same editable 250 kg estimate.
- Aggregate stock-level quantities for map occupancy; multiply non-kg quantities by an optional `items.weight_kg_per_unit` value.
- Keep item gross weight optional and user-entered. Never seed inferred weights. Count stock lines with unknown/non-positive weights as omitted and identify their items in bin details.
- Include warehouse and bin active state in the map presentation.
- Present zone and bin utilization in the map and selected-bin detail. A configured capacity at 90% or more is shown as full, matching the existing bin status convention.
- Use existing modal, confirmation, toast, and query invalidation patterns. Do not introduce new permissions or bypass existing service constraints.

## Acceptance

- A permitted user can create, edit, and delete zones and bins from the map; delete errors explain when stock or pending inventory work prevents deletion.
- Zone and bin capacity values can be set, cleared, and edited; invalid or negative values are rejected by the API.
- The map shows used kg and max kg for configured capacities and refreshes after changes.
- The map identifies the initial 250 kg/bin values as planning estimates that administrators should replace with posted rack/floor ratings.
- The map and structure screens require capacity for newly created zones/bins, while existing capacities remain editable or clearable.
- The map visibly labels inactive warehouses and bins.
- Derived zone totals follow active-bin changes; manual estimates and posted ratings remain fixed until edited.
- Stock with configured gross weights contributes to kg usage; stock without known weight is flagged in the bin detail. Warehouse capacity remains an estimate where a posted rating has not been recorded.
- Existing warehouse structure management and stock workflows continue to use their current API and safety rules.
