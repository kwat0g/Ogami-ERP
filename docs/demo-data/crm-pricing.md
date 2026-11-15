# CRM pricing layer

The `crm-pricing` layer follows the finished-product catalog, costed BOMs, customer catalog access, and workforce identities. It writes product prices through `ProductService::updateSellingPrices`, creates customer portal users through `PortalAccessService::inviteCustomer` and `PortalPasswordResetService`, and runs pricing negotiations through `PriceNegotiationService`. Accepted requests create agreements through the application's acceptance flow and `PriceAgreementService`.

## Demo data

- Every active finished product receives a synthetic minimum selling price and fallback base price after `PriceNegotiationService::reviewCost` reports a complete cost basis.
- Every active demo customer receives one authorized-signatory portal account using an `@portal.ogami-demo.test` address. The account is activated through the application's invitation and password-reset paths. It uses the configured demo password; the random invitation token is consumed and is not retained as a credential.
- Every active customer receives one accepted, cost-reviewed agreement for its first accessible finished product. Even-indexed customers receive volume tiers; odd-indexed customers receive flat pricing.
- CRM request status coverage includes requested, pending Finance, offered, accepted, rejected, Sales rejected, customer cancelled, withdrawn, and expired.
- The Sales-rejected example requests a price below the current synthetic minimum floor, so its rejection reason agrees with the seeded terms. Requests use the same portal accounts this layer provisioned for the customers.
- Three live-start records remain open: one customer request waiting for Sales, one offer waiting for customer response, and one direct Sales proposal waiting for Finance review.
- Historical actions suppress in-app notifications while retaining the domain events, audit records, and generated agreements. Open actions call the normal notification services; the internal PriceRequest notification gap is documented in F-027.

## Price assumptions

The current code contains no approved customer selling price list or released margin policy. For illustrative navigation only, the layer sets the minimum price to `reviewed unit cost × 1.20` and base price to `reviewed unit cost × 1.50`. Accepted offers use the base price, with volume tiers at `1.45×` and `1.40×` reviewed unit cost. The pricing review uses the application's existing cost rules: the greatest of product standard cost, active BOM total cost, and matching finished-good item standard cost. These multipliers do not represent Ogami quotes, approved margin, customer commitments, or actual profitability. See F-028 and the manufacturing-cost limitations in F-018.

The expired example is created 35 days before `--as-of` and expires before that date through the service's expiry operation. Other closed negotiation examples are dated on the as-of day; open scenarios stay in their current workflow state.

## Idempotency and review

Requests use stable `DEMO-CRM-PRICING:` note markers. The layer creates a missing record, verifies an existing record is still in the seeded state, and stops on drift instead of overwriting a user-edited negotiation. Existing product prices must match the documented factors exactly. Password hashes and one-time token hashes are security material and are intentionally excluded from stable dataset fingerprints; the portal account identity, active/signatory state, and password-change date are checked.

Do not change the pricing factors or customer assignment definitions on a populated demo database. Reset from a verified snapshot before changing the synthetic catalog.
