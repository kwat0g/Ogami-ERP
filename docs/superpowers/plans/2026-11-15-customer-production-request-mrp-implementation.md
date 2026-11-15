# Customer Production Requests and Stock MRP Plans Implementation Plan

> **For agentic workers:** Execute this plan inline in the approved isolated worktree. The user approved the linked design and requested implementation.

**Goal:** Make internal stock production traceable to MRP and let customers request exact production quantities without placing zero-stock Sales Orders.

**Architecture:** Generalize `MrpPlan` to reference a Sales Order, a customer production request, or an internal root stock work order. Reuse `StockProductionPlanningService` for BOM, subassembly, and material-shortage planning; add a customer request lifecycle and request-owned finished-goods holds that transfer to Sales Order allocations atomically.

**Tech Stack:** Laravel, PostgreSQL, React, TypeScript, TanStack Query, existing inventory reservation and portal session infrastructure.

**Spec:** `docs/superpowers/specs/2026-11-15-customer-production-request-design.md`

## Global Constraints

- Ordinary Customer Portal Sales Orders remain limited to server-verified available stock.
- A production request is not a Sales Order and creates no invoice, payment, or delivery.
- Internal **Produce for stock** preserves the exact PPC-entered make quantity.
- Customer production plans preserve the exact production quantity on the request.
- Work orders remain Planned until PPC uses the existing confirmation gate.
- Only outgoing-QC-released, received stock may satisfy an ordinary order or customer hold.
- A request becomes ready only after its full requested quantity is QC-released and received.
- The approved request hold duration is 48 hours from the ready notification.
- Customer-owned records remain scoped to the authenticated portal customer's account.

## Review Focus

- Existing Sales Order MRP plans and reruns retain their current source/version behavior.
- Internal stock create idempotency does not create duplicate plans, work orders, or purchase requests.
- Customer requests cannot bypass the ordinary stock availability gate through direct API calls.
- Request holds cannot double-reserve a lot/bin or be transferred to a different customer's order.
- Decline, cancellation, expiry, and concurrent order/hold conversion release or transfer reservations exactly once.

---

### Task 1: Make MRP plans source-aware

**Files:**
- Create a migration under `api/database/migrations/` to make `mrp_plans.sales_order_id` nullable, add customer-request and internal-stock source foreign keys, add source/version indexes, and enforce exactly one source.
- Modify `api/app/Modules/MRP/Models/MrpPlan.php` and `api/app/Modules/MRP/Resources/MrpPlanResource.php` for source relations and source summaries.
- Modify `api/app/Modules/MRP/Services/MrpEngineService.php`, `MrpPlanRealtimeService.php`, `MrpPlanController.php`, and `api/app/Modules/MRP/routes.php` so listing, detail, realtime, and rerun paths handle all sources without treating `null` as a Sales Order id.
- Modify `api/app/Modules/Production/Models/WorkOrder.php` only as needed for the stock-source relation.

**Deliverable:** Existing Sales Order plans work as before; new source types can be represented and loaded safely, with unique active/version constraints scoped by source.

### Task 2: Link internal Produce for Stock to MRP

**Files:**
- Modify `api/app/Modules/Production/Services/StockProductionPlanningService.php` and `api/app/Modules/Production/Services/WorkOrderService.php`.
- Modify MRP plan source serialization/detail as needed.

**Deliverable:** A new root stock work order and its MRP plan are created atomically and idempotently. The plan records exact make quantity and reuses the existing BOM, material availability, subassembly, shortage, draft purchase request, and cancellation reconciliation. Root and child work orders remain Planned until existing PPC confirmation.

### Task 3: Add customer production request domain and staff workflow

**Files:**
- Create customer production request model, status enum, transition history migration/model, staff and portal resources, validation requests, and `api/app/Modules/CRM/Services/CustomerProductionRequestService.php`.
- Add customer portal endpoints in `api/app/Modules/B2B/Controllers/CustomerPortalController.php` and `api/app/Modules/B2B/routes.php`.
- Add Sales/PPC endpoints in the existing CRM/Production route structure with request ownership and permission checks.
- Reuse the stock planning service to create request-sourced MRP plans and planned stock work orders; add requested/offered dates, customer accept/decline, staff decline, and safe cancellation transitions.
- Add portal notification events for request status changes and ready-to-order.

**Deliverable:** Customers can create and track requests; Sales can review and offer a ready-to-order date; customer acceptance precedes PPC confirmation. The customer cannot create or control an MRP plan directly.

### Task 4: Add customer request stock holds and conversion

**Files:**
- Create a request-owned finished-goods hold migration/model/service with output, inspection, item, location, lot, expiry, quantity, status, and timestamps.
- Modify `api/app/Modules/CRM/Services/SalesOrderSupplyService.php`, `api/app/Modules/Inventory/Services/StockMovementService.php`, and Customer Portal order placement to include request holds in availability and transfer them to Sales Order allocations atomically.
- Add the scheduled expiry command under `api/app/Console/Commands/` and register it in the existing scheduler.

**Deliverable:** Holds can be created only from eligible QC-released, received stock. Conversion transfers the existing reservation without reserving twice; expiry/cancellation releases it once. The ready state requires the full requested quantity.

### Task 5: Add Customer Portal and internal UI

**Files:**
- Modify `spa/src/api/b2b/customer.ts`, `spa/src/types/b2b.ts`, portal route registration, and `spa/src/pages/portal/customer/orders/new.tsx`.
- Create customer production request list/detail/create UI under `spa/src/pages/portal/customer/`.
- Create internal Sales request queue/detail UI and API client under the appropriate CRM/production pages.
- Modify MRP plan list/detail pages under `spa/src/pages/mrp/plans/` to display source type and customer-request/internal-stock context.

**Deliverable:** Out-of-stock products offer **Request production** while **Add to order** remains unavailable. Customers can submit quantity/date, follow status, accept/decline offers, cancel when allowed, and place a regular stock-backed order when ready. Sales/PPC can review, plan, offer, and confirm through existing gates.

### Task 6: Security and consistency review

**Files:** Review all new route, request, service, migration, and UI changes from Tasks 1–5.

**Deliverable:** Confirm source ownership, exactly-one-source constraints, customer tenancy, idempotency, locking order, lifecycle transitions, inventory holds, and existing Sales Order availability behavior match the approved spec. Do not modify unrelated files in the original checkout.
