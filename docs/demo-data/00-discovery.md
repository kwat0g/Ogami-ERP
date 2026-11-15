# Phase 0 — Current application discovery

Captured 2026-11-14. Read-only audit for planning. No application files, seeders, migrations or tests were changed. PHP tests were not run.

## Scope

Source: current Laravel 11/PHP 8.3 API, React routes, migrations, enum classes, seeders and read-only PostgreSQL queries. Counts are from a repeatable-read snapshot of configured database ogami at approximately 2026-11-14 04:46 UTC; scheduler/outbox counts change while services run.

Environment warning: host .env reports APP_ENV=local while the running API container reports APP_ENV=production with cached config. Container targets ogami, with Redis queue, SMTP and Reverb. SMTP endpoint is omitted. Compose has no local mail catcher. We did not run Artisan in that container. The disposable seed audit used a separate process and test-named DB with array mail, sync queue, log broadcast, array cache/session.

The foreign key appendix lists 794 constraints across 242 child tables: [SCHEMA-RELATIONSHIPS.md](SCHEMA-RELATIONSHIPS.md). Source-level event, listener, observer and job wiring is inventoried in [EVENTS-AND-HOOKS.md](EVENTS-AND-HOOKS.md).

## A. Current application

### Modules and process chains

Current source has 23 module directories: Accounting, Admin, Assets, Attendance, Auth, B2B, CRM, DSS, Dashboard, Forecasting, HR, Inventory, Landing, Leave, Loans, MRP, Maintenance, Payroll, Production, Purchasing, Quality, ReturnManagement and SupplyChain.

- O2C: customers/products/prices → sales order/items → MRP plan → production work order/materials/operations/outputs → quality inspection and receipt movement → allocation → delivery/proof → invoice/collection/credit note → journal/AR.
- P2P: request/approval → RFQ/invitations/quotes/award → PO/dispatch/portal response → shipment/GRN/incoming QC/MRB → stock movement → supplier bill/payment → journal/AP.
- Inventory: item/UOM, warehouse/zone/location, lot/batch, movement/level, reservation, issue, count, adjustment, transfer and GL handoff.
- H2R: employee/org/user → recruiting/interview → shift/attendance/holiday/OT → leave/loans/salary adjustment/training → payroll/statutory/bank/GL → clearance/final pay.
- Quality: plan/measurement → inspection → NCR/CAPA/effectiveness → supplier rating, complaint/8D, PPAP, calibration and delivery certificate.
- Maintenance: asset/machine/mold, PM schedule, maintenance WO/log/downtime, spare use, calibration/depreciation. Production and maintenance WOs are distinct.
- Accounting/DSS: domain actions/handoffs → journals, budgets, aging, KPI snapshots, forecasts, DSS runs/recommendations. Audit, activity, alerts, notifications, scheduler/outbox, backups, imports/exports and portals are additional surfaces.

### Roles and approval paths

Business roles: customer_service_officer, department_head, driver, employee, finance_officer, hr_officer, impex_officer, maintenance_supervisor, maintenance_tech, ppc_head, production_manager, purchasing_officer, qc_inspector, sales_officer, system_admin, vice_president and warehouse_staff. Test role is excluded. Customer/supplier portals use separate users. DB has 290 permissions, 905 role-permission links.

The current DB has 18 departments and 37 positions. Department names include Admin & General Affairs, Customer Service, Executive, Finance & Accounting, Human Resources, Import/Export, Information Technology, Maintenance & Engineering, Mold Department, Production, Production Planning, Purchasing & Procurement, Quality Control, Sales & Marketing and Warehouse & Logistics. It also contains Gnehm Ryien Rabe, JILAY and test; their intended organizational meaning needs review before using them in the demo hierarchy.

17 workflow definitions exist, 10 active. Active steps: asset disposal finance_officer→vice_president; bill payment vice_president; cash advance department_head→finance_officer→vice_president; company loan department_head→hr_officer→finance_officer→vice_president; finance-only return finance_officer; leave department_head→hr_officer; PO and PR finance_officer→vice_president; return request department_head→production_manager; salary adjustment production_manager→vice_president. Selected financial workflows use a ₱50,000 threshold in source; confirm as policy. Inactive definitions: 8D, department transfer, maintenance request assignment, NCR, payroll, separation clearance and work-order approval. Do not fabricate inboxes on inactive definitions. ApprovalService checks current step/role and distinct actors.

### Schema, numbering, payroll, reference data

284 public tables exist, 202 non-empty in snapshot. Complete counts follow. DocumentSequenceService generates monthly PREFIX-YYYYMM-NNNN or yearly PREFIX-YYYY-NNNN from settings; complaint/NCR/WO also inspect max persisted numbers. 49 sequence rows exist. Use domain services for generated numbers.

Examples observed: RM-### raw, PKG-### packaging, WB-### legacy finished, IMM-## machine, OGM-YYYY-#### employee, UOM KG/G/PCS/BAG/PALLET/BOX/L/ROLL, warehouse MW and location A-01. Validate before reuse.

Pay types monthly/semi_monthly; setting is semi-monthly with cutoffs 1–15 and 16–month-end, plus 13th-month period. Period service rejects computation before cutoff ends. On Nov 14, 2026 Nov 1–15 is not ready; Oct 16–31 is the latest complete cutoff.

Payroll calculator: basic is half monthly equivalent per semi-monthly cutoff, prorates hire/separation and effective salary changes; adds OT/night/holiday then deducts late/undertime. Paid leave stays inside flat basic, leave-pay field is zero. Absences and unpaid leave do not reduce basic. This blocks an unpaid-absence demo without approved application fix. Loan path supports company loan and cash advance only; statutory loan enums are unsupported in request flow. Loan installment is capped to outstanding principal, then net is clamped at zero.

Configured settings: ordinary OT 1.25, premium OT 1.30, night differential 0.10; DTR multipliers regular holiday 2.0, holiday/rest 2.6, rest 1.3, special holiday 1.3, special/rest 1.5; 8 hours/day, 22 work days/month. User must confirm intent.

132 government rows: SSS 114, PhilHealth 2, Pag-IBIG 4, BIR 12; effective through 2025, no 2026 contribution table. No minimum-wage rule or salary-grade rate table found. Holiday table has 21 dates for 2026, none for 2025/2027. Do not guess.

### Status enums from current PHP source

This inventory covers current PHP enum classes whose names end in Status; other string-backed statuses need separate source inspection.

- Accounting/Enums/AccountingPeriodStatus.php: open, closed, reopened
- Accounting/Enums/BillPaymentStatus.php: pending_approval, rejected, posted, voided
- Accounting/Enums/BillStatus.php: draft, unpaid, partial, paid, cancelled
- Accounting/Enums/BudgetStatus.php: draft, submitted, approved, active, closed
- Accounting/Enums/BudgetTransferStatus.php: pending, approved, rejected
- Accounting/Enums/CreditNoteStatus.php: draft, finalized, applied, void
- Accounting/Enums/InvoicePaymentSubmissionStatus.php: pending, verified, rejected
- Accounting/Enums/InvoiceStatus.php: draft, finalized, partial, paid, cancelled
- Accounting/Enums/JournalEntryStatus.php: draft, posted, reversed
- Admin/Enums/AdminUserStatus.php: active, inactive, locked
- Admin/Enums/LoginHistoryStatus.php: success, failed_credentials, failed_locked, failed_inactive, failed_password_expired
- Assets/Enums/AssetStatus.php: active, under_maintenance, disposed
- Assets/Enums/TransferStatus.php: pending, approved, rejected, completed
- Attendance/Enums/AttendanceStatus.php: present, absent, late, halfday, on_leave, holiday, rest_day
- Attendance/Enums/OvertimeStatus.php: pending, approved, rejected
- B2B/Enums/DeliveryScheduleStatus.php: submitted, acknowledged, rejected, cancelled
- CRM/Enums/ComplaintNcrHandoffStatus.php: not_started, generated, manual_required
- CRM/Enums/ComplaintStatus.php: open, investigating, resolved, closed, cancelled
- CRM/Enums/PriceRequestStatus.php: requested, pending_finance, offered, accepted, rejected, sales_rejected, customer_cancelled, withdrawn, expired
- CRM/Enums/SalesOrderResponseStatus.php: pending, accepted, rejected, superseded
- CRM/Enums/SalesOrderStatus.php: draft, awaiting_confirmation, delivery_negotiation, confirmed, in_production, partially_delivered, delivered, invoiced, paid, closed, cancelled
- DSS/Enums/RecommendationStatus.php: open, in_progress, accepted, dismissed, snoozed, expired, executed, resolved
- Dashboard/Enums/KpiStatus.php: on_target, warning, off_target
- HR/Enums/CivilStatus.php: single, married, widowed, separated, divorced
- HR/Enums/ClearanceStatus.php: pending, in_progress, completed, finalized, cancelled
- HR/Enums/EmployeeStatus.php: active, on_leave, suspended, resigned, terminated, retired
- HR/Enums/EmployeeTrainingStatus.php: scheduled, completed, expired, cancelled
- HR/Enums/JobPostingStatus.php: draft, open, closed, filled
- HR/Enums/ProfileUpdateStatus.php: pending, pending_finance, approved, rejected
- HR/Enums/PropertyStatus.php: issued, returned, lost
- HR/Enums/ReviewStatus.php: pending, in_progress, submitted, acknowledged
- HR/Enums/SalaryAdjustmentStatus.php: pending, approved, rejected
- HR/Enums/TrainingMatrixStatus.php: trained, expired, gap
- Inventory/Enums/GrnStatus.php: draft, pending_qc, accepted, partial_accepted, rejected
- Inventory/Enums/IncomingQcHandoffStatus.php: not_started, generated, manual_required, not_required
- Inventory/Enums/ItemCostReviewStatus.php: pending, approved, rejected
- Inventory/Enums/MaterialIssueStatus.php: draft, issued, cancelled
- Inventory/Enums/MovementGlHandoffStatus.php: not_started, generated, manual_required, not_required
- Inventory/Enums/MrbStatus.php: held, released, scrapped, returned
- Inventory/Enums/ReservationStatus.php: reserved, issued, released
- Inventory/Enums/StockAdjustmentStatus.php: pending, approved, rejected, withdrawn
- Inventory/Enums/StockCountItemStatus.php: pending, counted, verified, adjusted
- Inventory/Enums/StockCountSessionStatus.php: draft, in_progress, completed, cancelled
- Inventory/Enums/StockStatus.php: critical, low, ok
- Inventory/Enums/TransferOrderStatus.php: pending, transferred, completed, cancelled
- Landing/Enums/ContactInquiryStatus.php: new, in_progress, closed
- Landing/Enums/NewsletterStatus.php: subscribed, unsubscribed
- Leave/Enums/LeaveRequestStatus.php: pending_dept, pending_hr, approved, rejected, cancelled
- Loans/Enums/LoanStatus.php: pending, active, paid, cancelled, rejected, write_off_pending, written_off
- MRP/Enums/MachineStatus.php: running, idle, maintenance, breakdown, offline
- MRP/Enums/MoldStatus.php: available, in_use, maintenance, retired
- MRP/Enums/MrpPlanStatus.php: active, superseded, cancelled
- MRP/Enums/MrpRunStatus.php: running, completed, partial, failed
- Maintenance/Enums/MaintenanceWorkOrderStatus.php: open, assigned, in_progress, completed, cancelled
- Payroll/Enums/BankFileGenerationStatus.php: not_started, pending, manual_required, generated
- Payroll/Enums/PayrollAdjustmentStatus.php: pending, approved, rejected, applied
- Payroll/Enums/PayrollGlHandoffStatus.php: not_started, pending, manual_required, posted, not_required
- Payroll/Enums/PayrollPeriodStatus.php: draft, processing, computed, approved, finalized, disbursed, voided
- Production/Enums/LateProductionReportStatus.php: pending, approved, rejected, correction
- Production/Enums/ProductionReceiptHandoffStatus.php: not_started, generated, manual_required, not_required
- Production/Enums/ProductionScheduleStatus.php: pending, confirmed, superseded, executed
- Production/Enums/WoOperationStatus.php: pending, setup, in_progress, paused, completed, skipped
- Production/Enums/WorkOrderStatus.php: planned, confirmed, in_progress, paused, completed, closed, cancelled
- Purchasing/Enums/PurchaseOrderResponseStatus.php: pending, pending_approval, accepted, rejected, superseded
- Purchasing/Enums/PurchaseOrderStatus.php: draft, pending_approval, approved, sent, acknowledged, supplier_proposed, supplier_declined, partially_received, received, closed, cancelled
- Purchasing/Enums/PurchaseRequestConversionStatus.php: not_started, pending, manual_required, partial, sourcing_pending, converted
- Purchasing/Enums/PurchaseRequestStatus.php: draft, pending, approved, rejected, converted, cancelled
- Purchasing/Enums/RfqInvitationStatus.php: invited, viewed, submitted, awarded, not_awarded
- Purchasing/Enums/RfqStatus.php: draft, open, closed, awarded, cancelled
- Purchasing/Enums/SupplierDispatchStatus.php: pending, portal_available, manual_required, confirmed, failed, cancelled
- Purchasing/Enums/SupplierListingStatus.php: pending, approved, rejected, superseded
- Purchasing/Enums/SupplierQuoteResponseStatus.php: quoted, no_quote
- Purchasing/Enums/SupplierQuoteStatus.php: draft, submitted, awarded, not_awarded
- Quality/Enums/CalibrationStatus.php: active, due, overdue, retired
- Quality/Enums/EffectivenessStatus.php: pending_verification, effective, ineffective, not_applicable
- Quality/Enums/InspectionStatus.php: draft, in_progress, awaiting_review, passed, failed, cancelled
- Quality/Enums/NcrStatus.php: open, in_progress, closed, cancelled
- Quality/Enums/PpapElementStatus.php: pending, submitted, accepted, rejected, not_applicable
- Quality/Enums/PpapStatus.php: draft, submitted, under_review, approved, rejected, expired
- ReturnManagement/Enums/ReturnCaseStatus.php: submitted, under_review, information_needed, action_agreed, in_progress, resolved, rejected, withdrawn
- ReturnManagement/Enums/ReturnInspectionHandoffStatus.php: not_started, generated, manual_required, not_required
- ReturnManagement/Enums/ReturnRequestStatus.php: draft, pending_approval, approved, received, inspected, completed, rejected, cancelled
- SupplyChain/Enums/DeliveryCocHandoffStatus.php: not_started, generated, not_required, manual_required
- SupplyChain/Enums/DeliveryCostHandoffStatus.php: generated, manual_required, not_required
- SupplyChain/Enums/DeliveryDiscrepancyStatus.php: pending, rejected
- SupplyChain/Enums/DeliveryInvoiceHandoffStatus.php: not_started, generated, manual_required, not_required
- SupplyChain/Enums/DeliveryStatus.php: scheduled, loading, in_transit, return_pending, delivered, confirmed, returned, cancelled
- SupplyChain/Enums/DeliveryStockReservationStatus.php: reserved, consumed, released
- SupplyChain/Enums/ShipmentStatus.php: ordered, shipped, in_transit, customs, cleared, received, cancelled
- SupplyChain/Enums/VehicleStatus.php: available, in_use, maintenance, retired

### Table row census

Read-only repeatable-read snapshot; volatile counts may move.

| Table | Rows |
|---|---:|
| accounting_periods | 1 |
| accounts | 60 |
| action_center_task_events | 0 |
| action_center_tasks | 0 |
| activity_events | 2781 |
| alerts | 318 |
| application_interviews | 4 |
| application_notes | 0 |
| approval_delegations | 8 |
| approval_records | 264 |
| approved_suppliers | 91 |
| asset_depreciation_runs | 0 |
| asset_depreciations | 60 |
| asset_transfers | 0 |
| assets | 30 |
| attendance_punches | 0 |
| attendances | 60102 |
| audit_logs | 16039 |
| backup_operations | 1 |
| bank_file_records | 7 |
| bill_items | 6 |
| bill_of_materials | 14 |
| bill_payments | 5 |
| bills | 8 |
| bom_items | 44 |
| budget_actuals_sync_runs | 0 |
| budget_line_items | 11 |
| budget_transfers | 1 |
| budgets | 5 |
| cache | 0 |
| cache_locks | 0 |
| calibration_records | 0 |
| chain_events | 0 |
| chain_listener_runs | 548 |
| chain_step_runs | 1471 |
| clearances | 3 |
| collections | 3 |
| commission_earnings | 0 |
| complaint_8d_escalation_deliveries | 0 |
| complaint_8d_reports | 0 |
| contact_inquiries | 2 |
| containers | 0 |
| credit_note_applications | 4 |
| credit_note_lines | 7 |
| credit_notes | 7 |
| customer_agreement_documents | 0 |
| customer_complaints | 2 |
| customer_order_feedback | 0 |
| customer_portal_users | 7 |
| customer_product_access | 30 |
| customer_product_warranty_options | 13 |
| customer_product_warranty_overrides | 0 |
| customers | 12 |
| dashboard_layouts | 317 |
| dashboard_widgets | 76 |
| de_minimis_benefits | 0 |
| defect_types | 11 |
| deliveries | 5 |
| delivery_attempt_outcome_items | 0 |
| delivery_attempt_outcome_movements | 0 |
| delivery_attempt_outcomes | 0 |
| delivery_attempt_revisions | 0 |
| delivery_cost_handoffs | 1 |
| delivery_customer_return_allocations | 0 |
| delivery_items | 12 |
| delivery_proofs | 4 |
| delivery_quantity_discrepancies | 0 |
| delivery_reschedules | 19 |
| delivery_schedules | 0 |
| delivery_stock_reservation_batches | 3 |
| delivery_stock_reservations | 4 |
| demand_forecasts | 120 |
| departments | 18 |
| document_sequences | 49 |
| documents | 12 |
| driver_credentials | 0 |
| dss_forecast_runs | 0 |
| dss_parameters | 0 |
| dss_recommendation_events | 13 |
| dss_recommendations | 37 |
| dss_run_logs | 2104 |
| dss_scenarios | 0 |
| email_deliveries | 1679 |
| employee_documents | 0 |
| employee_leave_balances | 176 |
| employee_loans | 10 |
| employee_onboardings | 11 |
| employee_property | 0 |
| employee_salary_history | 2 |
| employee_shift_assignments | 204 |
| employee_skills | 0 |
| employee_trainings | 0 |
| employees | 202 |
| employment_history | 12 |
| event_outbox | 86341 |
| export_column_preferences | 1 |
| failed_jobs | 37 |
| fiscal_years | 1 |
| goods_receipt_notes | 14 |
| government_contribution_tables | 132 |
| grn_items | 14 |
| holidays | 21 |
| import_batch_records | 0 |
| import_batches | 0 |
| inspection_defect_evidence | 0 |
| inspection_defective_pieces | 1 |
| inspection_measurements | 535 |
| inspection_piece_readings | 0 |
| inspection_result_authors | 11 |
| inspection_spec_items | 55 |
| inspection_spec_revisions | 11 |
| inspection_specs | 9 |
| inspections | 30 |
| invoice_items | 9 |
| invoice_payment_submissions | 0 |
| invoices | 8 |
| item_categories | 13 |
| item_cost_reviews | 6 |
| item_quality_plans | 28 |
| item_uom_conversions | 0 |
| items | 29 |
| job_applications | 5 |
| job_batches | 0 |
| job_postings | 3 |
| jobs | 0 |
| journal_entries | 73 |
| journal_entry_lines | 205 |
| kpi_definitions | 11 |
| kpi_snapshots | 10 |
| late_production_reports | 0 |
| leave_requests | 36 |
| leave_types | 8 |
| loan_payments | 12 |
| login_history | 1199 |
| machine_condition_readings | 0 |
| machine_downtimes | 2 |
| machines | 36 |
| maintenance_logs | 3 |
| maintenance_part_requests | 0 |
| maintenance_schedules | 10 |
| maintenance_windows | 0 |
| maintenance_work_orders | 5 |
| material_handovers | 13 |
| material_issue_slip_items | 1 |
| material_issue_slips | 1 |
| material_reservations | 17 |
| material_review_records | 1 |
| migrations | 851 |
| mold_history | 15 |
| mold_machine_compatibility | 119 |
| molds | 15 |
| mrp_plans | 397 |
| mrp_runs | 81 |
| ncr_actions | 1 |
| ncr_effectiveness_notifications | 0 |
| ncr_escalation_deliveries | 72 |
| ncr_recurrence_scans | 2 |
| ncr_templates | 1 |
| newsletter_subscribers | 1 |
| non_conformance_reports | 50 |
| notification_preferences | 0 |
| notifications | 2628 |
| official_receipts | 1 |
| overtime_requests | 9 |
| password_history | 193 |
| password_reset_requests | 182 |
| password_reset_tokens | 0 |
| payroll_adjustments | 11 |
| payroll_annual_tax_brackets | 12 |
| payroll_anomaly_flags | 18 |
| payroll_cycle_claims | 1618 |
| payroll_deduction_details | 2727 |
| payroll_disbursement_proofs | 2 |
| payroll_periods | 11 |
| payrolls | 1688 |
| permissions | 290 |
| personal_access_tokens | 0 |
| portal_contract_templates | 0 |
| portal_password_history | 6 |
| portal_password_reset_tokens | 0 |
| portal_shipping_documents | 6 |
| positions | 37 |
| ppap_elements | 0 |
| ppap_submissions | 0 |
| price_requests | 2 |
| processed_year_end_leave_types | 0 |
| product_blueprints | 1 |
| product_images | 3 |
| product_price_agreements | 16 |
| product_routings | 16 |
| production_logs | 1 |
| production_schedules | 177 |
| products | 9 |
| profile_update_requests | 3 |
| purchase_order_items | 41 |
| purchase_order_response_items | 0 |
| purchase_order_responses | 5 |
| purchase_orders | 42 |
| purchase_request_items | 155 |
| purchase_request_templates | 1 |
| purchase_requests | 152 |
| recruitment_application_events | 16 |
| request_for_quote_invitations | 10 |
| request_for_quote_items | 7 |
| request_for_quotes | 7 |
| return_case_attachments | 0 |
| return_case_events | 0 |
| return_case_lines | 0 |
| return_case_receipt_allocations | 0 |
| return_cases | 0 |
| return_receipt_items | 0 |
| return_receipts | 0 |
| return_request_items | 5 |
| return_request_source_allocations | 0 |
| return_requests | 5 |
| rfq_awards | 5 |
| rfq_documents | 6 |
| role_permissions | 905 |
| roles | 18 |
| routing_operations | 8 |
| salary_adjustments | 0 |
| sales_order_advance_applications | 0 |
| sales_order_advances | 0 |
| sales_order_agreements | 0 |
| sales_order_items | 32 |
| sales_order_response_items | 0 |
| sales_order_responses | 0 |
| sales_order_stock_allocations | 2 |
| sales_order_transition_rejections | 0 |
| sales_orders | 28 |
| scheduled_exports | 3 |
| scheduler_task_runs | 98064 |
| scheduler_tick_runs | 17121 |
| sessions | 1 |
| settings | 474 |
| shifts | 5 |
| shipment_documents | 0 |
| shipment_landed_costs | 0 |
| shipment_lots | 3 |
| shipments | 2 |
| skills | 0 |
| sod_conflict_rules | 6 |
| spare_part_usage | 0 |
| stock_adjustments | 22 |
| stock_count_items | 65 |
| stock_count_sessions | 5 |
| stock_level_snapshots | 313 |
| stock_levels | 30 |
| stock_movements | 68 |
| supplier_agreement_documents | 0 |
| supplier_invoices | 4 |
| supplier_item_listings | 8 |
| supplier_order_dispatches | 9 |
| supplier_performance_snapshots | 10 |
| supplier_portal_users | 2 |
| supplier_quote_items | 8 |
| supplier_quotes | 8 |
| supplier_shipment_items | 6 |
| supplier_shipment_updates | 6 |
| supplier_shipments | 6 |
| thirteenth_month_accruals | 200 |
| thirteenth_month_tax_rules | 1 |
| training_expiry_alert_deliveries | 0 |
| trainings | 0 |
| transfer_orders | 15 |
| uoms | 8 |
| user_invites | 0 |
| user_permission_overrides | 1 |
| users | 210 |
| vehicles | 3 |
| vendors | 16 |
| warehouse_locations | 78 |
| warehouse_scan_events | 0 |
| warehouse_zones | 8 |
| warehouses | 4 |
| wo_operations | 8 |
| work_order_crew_members | 9 |
| work_order_crews | 6 |
| work_order_defects | 0 |
| work_order_materials | 84 |
| work_order_outputs | 5 |
| work_orders | 26 |
| workflow_definitions | 17 |
| year_end_leave_dispositions | 0 |

## B. Existing seeder/factory audit

60 seeders inventoried. Verdict does not delete anything in this phase.

| Seeder | Verdict | Reason |
|---|---|---|
| AccountingPeriodSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| AdminUserSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| AnalyticsDemoSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| AuditBudgetTransferStartingStateSeeder | Keep — test-only | Isolate from supported demo path and preserve test/audit use. |
| AuditE2EProcurementMasterDataSeeder | Keep — test-only | Isolate from supported demo path and preserve test/audit use. |
| AuditFinanceActorSeeder | Keep — test-only | Isolate from supported demo path and preserve test/audit use. |
| AuditLeaveTypePaginationSeeder | Keep — test-only | Isolate from supported demo path and preserve test/audit use. |
| AuditPayrollReviewerSeeder | Keep — test-only | Isolate from supported demo path and preserve test/audit use. |
| AuditPortalActorSeeder | Keep — test-only | Isolate from supported demo path and preserve test/audit use. |
| AuditWarehouseCheckerSeeder | Keep — test-only | Isolate from supported demo path and preserve test/audit use. |
| BomSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| ChartOfAccountsSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| ComprehensiveDemoSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| CustomerSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| DashboardRoleLayoutSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| DashboardWidgetSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| DatabaseSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| DefectTypeSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| DefenseHeroSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| DemoAccountSeeder | Rewrite — demo identity | Use reserved domains and explicit safe synthetic phone/ID fields; separate credentials. |
| DemoBomCostBasisSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| DemoDataSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| DemoInspectionEvidenceSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| DemoInspectionSpecSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| DemoItemQualityPlanSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| DemoRfqBudgetSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| DepartmentSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| DssDemoScenarioSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| E2EAccountSeeder | Keep — test-only | Isolate from supported demo path and preserve test/audit use. |
| E2EReferenceDataSeeder | Keep — test-only | Isolate from supported demo path and preserve test/audit use. |
| GoldenPathDemoSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| GovernmentTable2025Seeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| GovernmentTableSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| HolidaySeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| InventoryItemSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| KpiDefinitionSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| LeaveTypeSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| LoadTestUserSeeder | Keep — test-only | Isolate from supported demo path and preserve test/audit use. |
| MachineSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| ManufacturedProductInventorySeeder | Rewrite — transactional demo | Opening/finished stock direct writes must move to supported inventory actions. |
| MoldCompatibilitySeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| MoldSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| PayrollChartAccountsSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| PositionSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| PriceAgreementSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| ProductRoutingSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| ProductSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| RealisticDataSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| RolePermissionSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| SeriesEDemoSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| SettingsSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| ShiftSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| SodConflictRuleSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| Sprint8DemoSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| ThesisDemoSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| ThesisItemQualityPlanSeeder | Rewrite — demo fixture | Legacy/targeted fixture is incomplete or bypasses current workflow; migrate callers/tests before archive. |
| UomSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| VehicleSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| WarehouseSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |
| WorkflowSeeder | Keep — system/reference | Retain as a baseline/master-data source; it is not sufficient for the defense dataset. |

DatabaseSeeder calls role/permission, admin, workflow, settings, SoD, government tables, accounts, accounting period, dashboard widgets/layout and KPI definitions. Reference flags add org, shift/holiday/leave/UOM/item/warehouse/customer/product/price/BOM/machine/mold/routing/defect/vehicle. Demo flag adds manufactured stock, demo accounts, DemoData, Sprint8, SeriesE, ComprehensiveDemo, RealisticData, GoldenPath, RFQ/budget, inspection evidence/quality plan and thesis fixtures. AnalyticsDemoSeeder, DssDemoScenarioSeeder, DefenseHeroSeeder and DemoInspectionSpecSeeder are not in the default path. DatabaseSeeder has no central DB-name allowlist.

Factories (test support, keep separate):
- BillFactory.php
- BudgetFactory.php
- CustomerFactory.php
- DemandForecastFactory.php
- DepartmentFactory.php
- EmployeeFactory.php
- FiscalYearFactory.php
- GoodsReceiptNoteFactory.php
- InvoiceFactory.php
- ItemCategoryFactory.php
- ItemFactory.php
- MachineFactory.php
- Modules/Attendance/Models/OvertimeRequestFactory.php
- Modules/Auth/Models/UserFactory.php
- Modules/HR/Models/ClearanceFactory.php
- Modules/Landing/Models/ContactInquiryFactory.php
- Modules/Leave/Models/LeaveRequestFactory.php
- Modules/Loans/Models/EmployeeLoanFactory.php
- NonConformanceReportFactory.php
- PayrollFactory.php
- PayrollPeriodFactory.php
- PortalShippingDocumentFactory.php
- PositionFactory.php
- PpapSubmissionFactory.php
- ProductFactory.php
- PurchaseOrderFactory.php
- PurchaseRequestFactory.php
- RequestForQuoteFactory.php
- RequestForQuoteInvitationFactory.php
- RequestForQuoteItemFactory.php
- RfqAwardFactory.php
- RfqDocumentFactory.php
- SalesOrderFactory.php
- SalesOrderItemFactory.php
- SalesOrderStockAllocationFactory.php
- StockLevelFactory.php
- SupplierPortalUserFactory.php
- SupplierQuoteFactory.php
- SupplierQuoteItemFactory.php
- SupplierShipmentFactory.php
- VendorFactory.php
- WarehouseFactory.php
- WarehouseLocationFactory.php
- WarehouseZoneFactory.php
- WorkOrderFactory.php

### Fresh disposable seed audit

Ran migrate:fresh --seed only against ogami_test_demo_seed_audit_20261114, with APP_ENV=testing, array mail, sync queue, log broadcast, array cache/session.

- Default PHP memory_limit 128M failed in RealisticDataSeeder at 1m27.72s, process RSS ~156 MiB.
- Fresh rerun with 768M succeeded in 3m17.69s, peak RSS ~304 MiB.
- Approximate data: 200 employees, 60,106 attendance rows, 1,070 payroll rows/seven periods, 12 sales orders, 10 vendors, 5 customers, 22 items, 8 products, 12 machines, 15 molds, 22 stock movements, 4 maintenance WOs; zero calibration/training/skills/spare-part usage.
- Business dates were historical but created/approval/audit times were seed-run time; no controlled Carbon clock. Attendance skipped Sunday but not holidays; 2,619 rows were on configured holiday dates.
- demo:verify --analytics failed six areas: delivery-invoice linkage; complete production-to-cash; linked passed incoming-QC supplier history; eight empty widget payloads; KPI trend points; all eight DSS detectors. All SOs were confirmed, no production WOs/schedules existed, one pending approval record.
- RealisticData bulk-inserts ~60K attendance. GoldenPathDemo, ThesisDemo, Sprint8Demo and AnalyticsDemo also directly write or update transactional state.

### Current DB check and test dependencies

Read-only demo:verify on configured ogami failed four: 4 invoices missing delivery/SO provenance, 4 paid/partial invoices without collection rows, 37 failed_jobs, 3 passed outgoing inspections missing sample measurements (QC-202611-0021, 0022, 0025). DB was not modified.

Tests reference role permissions, settings, demo setup/accounts/readiness, DefenseHero, AnalyticsDemo, DSS scenarios, product routing, dashboard widgets and procurement audit fixtures. PHP suite not run. Factories remain test-only.

## C. Environment and clocks

Compose has Postgres, Redis, API, Horizon/queue, Reverb and scheduler. Host/container APP_ENV/config disagree. Host uses SMTP/Redis/Reverb; no catcher. Current guard is selective: test mode checks ogami_test or ogami_test_*, local has no DB-name allowlist, DatabaseSeeder is not centrally guarded. No demo:seed/reset/snapshot/restore/credentials commands; read-only demo:verify exists. Generic backups are not demo snapshots.

No seeder sets Carbon::setTestNow. Overdue, aging, dashboards, schedules and payroll use real runtime time. New history must control and restore clock in each event.

## D. User-facing data surface

### SPA routes

369 literal paths in 24 React route files (tests/wildcards excluded), grouped from current source:

- accountingRoutes.tsx: /accounting | /accounting/coa | /accounting/coa/create | /accounting/coa/:id/edit | /accounting/journal-entries | /accounting/journal-entries/create | /accounting/journal-entries/:id/edit | /accounting/journal-entries/:id | /accounting/vendors | /accounting/vendors/create | /accounting/vendors/:id | /accounting/vendors/:id/edit | /accounting/portal-access | /accounting/bills | /accounting/bills/create | /accounting/bills/:id | /accounting/customers | /accounting/customers/create | /accounting/customers/:id | /accounting/customers/:id/edit | /accounting/invoices | /accounting/customer-advances | /accounting/invoices/create | /accounting/invoices/:id/edit | /accounting/invoices/:id | /accounting/trial-balance | /accounting/income-statement | /accounting/balance-sheet | /accounting/ar-aging | /accounting/ap-aging | /accounting/periods | /accounting/credit-notes | /accounting/credit-notes/:id
- adminRoutes.tsx: /admin/roles | /admin/roles/create | /admin/roles/compare | /admin/roles/permissions-search | /admin/roles/:id/permissions | /admin/component-gallery | /admin/settings | /admin/imports | /admin/sessions | /admin/backups | /admin/audit-logs | /admin/sod | /admin/audit-logs/entity | /admin/scheduled-exports | /admin/users | /admin/users/create | /admin/users/:id | /alerts | /decision-center | /decision-center/:id | /admin/dss | /admin/gov-tables | /admin/audit-logs/:id
- advancedRoutes.tsx: /forecasting/demand | /forecasting/stock-out | /forecasting/accuracy | /budgeting | /budgeting/create | /budgeting/transfers | /budgeting/transfers/create | /budgeting/transfers/:id | /budgeting/:id/edit | /budgeting/:id | /budgeting/departments/:id | /budgeting/budget-vs-actual | /return-management | /return-management/cases | /return-management/cases/new | /return-management/cases/:id | /return-management/new | /return-management/:id/edit | /return-management/:id
- assetsRoutes.tsx: /assets | /assets/create | /assets/:id | /assets/:id/edit
- authRoutes.tsx: /sign-in | /login | /recovery | /forgot-password | /reset-password | /change-password
- careersRoutes.tsx: /careers | /careers/track | /careers/:id
- crmRoutes.tsx: /crm | /crm/inquiries | /crm/inquiries/:id | /crm/products | /crm/products/create | /crm/products/:id | /crm/products/:id/edit | /crm/customers | /crm/customers/create | /crm/customers/:id | /crm/customers/:id/edit | /crm/price-agreements/requests | /crm/price-agreements/requests/:id/offer | /crm/price-agreements | /crm/price-agreements/create | /crm/price-agreements/:id/edit | /crm/sales-orders | /crm/sales-orders/create | /crm/sales-orders/:id | /crm/sales-orders/:id/edit | /crm/complaints | /crm/complaints/new | /crm/complaints/:id
- dashboardRoutes.tsx: /dashboard | /dashboard/default | /dashboard/company | /dashboard/logistics | /dashboard/maintenance | /dashboard/department | /dashboard/sales | /dashboard/customer-service | /dashboard/plant-manager | /dashboard/hr | /dashboard/ppc | /dashboard/finance | /dashboard/accounting | /dashboard/purchasing | /dashboard/warehouse | /dashboard/quality | /dashboard/admin | /dashboard/scorecard | /calendar | /approvals | /approvals/delegations | /chains/recovery | /chains | /admin/activity | /admin/users-roles | /notifications | /action-center | /admin/operations-health
- driverRoutes.tsx: /driver | /driver/:id | /driver/:id/photo
- factoryRoutes.tsx: /factory | /factory/:woId/output | /factory/qc
- hrRoutes.tsx: /hr/departments | /hr/positions | /hr/employees | /hr/employees/create | /hr/employees/:id | /hr/employees/:id/edit | /hr/employee-trainings | /hr/employee-trainings/:id | /hr/profile-update-requests | /hr/attendance | /hr/attendance/import | /hr/attendance/shifts | /hr/attendance/shifts/assign | /hr/attendance/holidays | /hr/attendance/overtime | /hr/attendance/overtime/create | /hr/attendance/overtime/:id | /hr/leaves | /hr/leaves/calendar | /hr/leaves/create | /hr/leaves/:id | /hr/loans | /hr/loans/create | /hr/loans/:id | /hr/separations | /hr/separations/:id | /hr/training/matrix | /hr/trainings | /hr/trainings/create | /hr/trainings/:id/edit | /hr/skills | /hr/recruitment | /hr/recruitment/postings | /hr/recruitment/postings/create | /hr/recruitment/postings/:id | /hr/recruitment/postings/:id/edit | /hr/recruitment/applications | /hr/recruitment/applications/:id
- inventoryRoutes.tsx: /crm/price-agreements/cost-reviews | /inventory | /inventory/dashboard | /inventory/items | /inventory/uoms | /inventory/items/create | /inventory/items/:id | /inventory/items/:id/edit | /inventory/items/:id/stock-card | /inventory/items/:id/quality-plans | /inventory/warehouse | /inventory/stock-levels | /inventory/stock-adjustments | /inventory/stock-adjustments/create | /inventory/warehouse-map | /inventory/stock-count | /inventory/picking | /inventory/transfer-orders | /inventory/scanner | /inventory/grn | /inventory/grn/create | /inventory/grn/:id | /inventory/grns/:id | /inventory/material-issues | /inventory/material-issues/create | /inventory/material-issues/:id | /inventory/mrb | /inventory/mrb/:id
- landingRoutes.tsx: /
- legalRoutes.tsx: /privacy | /terms | /cookies
- maintenanceMobileRoutes.tsx: /maintenance/mobile | /maintenance/mobile/:mwoId
- maintenanceRoutes.tsx: /maintenance | /maintenance/downtime | /maintenance/work-orders | /maintenance/work-orders/create | /maintenance/work-orders/:id | /maintenance/schedules | /maintenance/schedules/create | /maintenance/schedules/:id | /maintenance/schedules/:id/edit
- mrpRoutes.tsx: /mrp | /mrp/boms | /mrp/boms/create | /mrp/boms/:id | /mrp/boms/:id/edit | /mrp/machines | /mrp/machines/create | /mrp/machines/:id | /mrp/machines/:id/edit | /mrp/molds | /mrp/molds/create | /mrp/molds/:id | /mrp/molds/:id/edit | /mrp/plans | /mrp/plans/:id
- payrollRoutes.tsx: /payroll/periods | /payroll/periods/create | /payroll/periods/:id | /payroll/periods/:id/employee/:eid | /payroll/adjustments | /payroll/adjustments/:id | /payroll/adjustments/create | /payroll/statutory
- portalRoutes.tsx: /portal/supplier/login | /portal/supplier/forgot-password | /portal/customer/login | /portal/customer/forgot-password | /portal/password-reset | /portal/customer/change-password | /portal/supplier/change-password | /portal/supplier | /portal/supplier/notifications | /portal/supplier/agreements | /portal/supplier/purchase-orders | /portal/supplier/purchase-orders/:id | /portal/supplier/invoices | /portal/supplier/invoices/:id | /portal/supplier/deliveries | /portal/supplier/statement-of-account | /portal/supplier/delivery-schedules | /portal/supplier/item-listings | /portal/supplier/rfqs | /portal/supplier/rfqs/:id | /portal/supplier/rfqs/:id/quote | /portal/supplier/problems | /portal/supplier/problems/:id | /portal/customer | /portal/customer/notifications | /portal/customer/agreements | /portal/customer/pricing | /portal/customer/orders | /portal/customer/orders/new | /portal/customer/orders/:id/edit | /portal/customer/orders/:id | /portal/customer/invoices | /portal/customer/invoices/:id | /portal/customer/deliveries | /portal/customer/deliveries/:id | /portal/customer/complaints | /portal/customer/returns | /portal/customer/returns/new | /portal/customer/returns/:id | /portal/customer/problems | /portal/customer/problems/new | /portal/customer/problems/:id | /portal/customer/statement-of-account | /portal/customer/delivery-schedules
- productionRoutes.tsx: /production | /production/dashboard | /production/oee | /production/schedule | /production/work-orders | /production/work-orders/create | /production/work-orders/:id | /production/work-orders/:id/record-output | /production/routings | /production/routings/create | /production/routings/:id
- purchasingRoutes.tsx: /purchasing | /purchasing/purchase-requests | /purchasing/purchase-requests/create | /purchasing/purchase-requests/:id | /purchasing/rfqs | /purchasing/rfqs/create | /purchasing/rfqs/:id/edit | /purchasing/rfqs/:id/compare | /purchasing/rfqs/:id/manual-quote | /purchasing/rfqs/:id | /purchasing/purchase-orders | /purchasing/purchase-orders/:id | /purchasing/approved-suppliers | /purchasing/supplier-listings | /purchasing/chain | /purchasing/suppliers/performance | /purchasing/suppliers/:id/performance
- qualityRoutes.tsx: /quality | /quality/dashboard | /quality/inspection-specs | /quality/inspection-specs/spec/:specId | /quality/inspection-specs/:productId | /quality/calibration | /quality/calibration/new | /quality/calibration/:id/edit | /quality/inspections | /quality/inspections/:id | /quality/work-orders/:id/final-inspection | /quality/ncrs | /quality/ncrs/effectiveness | /quality/ncrs/new | /quality/ncrs/:id | /quality/ncr-templates | /quality/ncr-templates/new | /quality/ncr-templates/:id/edit | /quality/traceability | /quality/capability
- selfServiceRoutes.tsx: /self-service | /self-service/profile | /self-service/trainings/:id | /self-service/me | /self-service/documents | /self-service/notification-preferences | /self-service/work-orders | /self-service/work-orders/:id | /self-service/maintenance-work-orders | /self-service/maintenance-work-orders/:id | /self-service/dtr | /self-service/overtime/:id | /self-service/overtime | /self-service/leave | /self-service/leaves | /self-service/leaves/:id | /self-service/loans/:id | /self-service/loans | /self-service/payslips/:id | /self-service/payslips
- supplyChainRoutes.tsx: /supply-chain | /supply-chain/shipments | /supply-chain/shipments/create | /supply-chain/shipments/:id | /supply-chain/deliveries | /supply-chain/deliveries/create | /supply-chain/deliveries/:id | /supply-chain/fleet

### Widgets and badges

DashboardWidgetSeeder has 76 widget definitions and KpiDefinitionSeeder 11 definitions. Widget keys are alerts, approvals.pending, assets.under_maintenance, budget.utilization, chain.recovery_backlog, chain.stage_breakdown, crm.open_complaints, executive.risks_opportunities, executive.weekly_briefing, finance.ap_aging, finance.ar_aging, finance.cash_position, finance.invoice_handoff_backlog, finance.revenue_mtd, finance.unpaid_invoices, finance.upcoming_payables, forecast.attendance, forecast.capacity, forecast.cash_flow, forecast.defect_rate, forecast.headcount, forecast.revenue, hr.headcount, hr.on_leave_today, hr.probation_alerts, hr.team_dtr_today, hr.team_on_leave_today, inventory.low_stock, inventory.pending_grns, inventory.pending_issues, inventory.qc_handoff_issues, kpi.ar_aging_60d, kpi.attendance_rate, kpi.budget_utilization, kpi.dppm, kpi.first_pass_yield, kpi.inventory_turnover, kpi.ncr_closure_days, kpi.oee, kpi.on_time_delivery, kpi.supplier_quality, kpi.wo_completion_rate, loans.outstanding, logistics.shipments_in_transit, machine.status, machine.utilization, maintenance.due_schedules, maintenance.open_wos, material.reservations, mrp.shortages, oee.gauges, payroll.upcoming, production.active_wo, production.gantt_mini, production.kpi, production.wo_breakdown, purchasing.open_pos, purchasing.open_prs, purchasing.supplier_perf, qc.open_ncrs, qc.pareto, qc.pass_rate, qc.pending_inspections, quality.eightd_sla, quality.ncr_aging, rma.open_returns, rma.pending_approval, sales.open_orders, sales.order_intake, sales.price_requests, self.dtr_today, self.leave_balance, self.payslip_summary, self.pending_requests, supply.delivery_schedule, supply.overdue_deliveries. Fresh seed had eight empty payloads: assets.under_maintenance, kpi.ar_aging_60d, kpi.attendance_rate, kpi.budget_utilization, kpi.dppm, kpi.first_pass_yield, kpi.inventory_turnover, kpi.ncr_closure_days.

Sidebar badge keys (43): action_center, decision_center, approvals, unread, inquiries, pending_so, open_complaints, pending_returns, mrp_plans, purchase_requests, supplier_listings_pending, pending_grn, deliveries, vehicles_maintenance, pending_budgets, draft_invoices, overdue_bills, profile_requests, missing_time_out, overtime, leaves, payroll, maintenance_wo, active_sessions, work_orders_awaiting_confirmation, work_orders, work_orders_due_soon, work_orders_paused, purchase_order_actions, overdue_purchase_orders, out_of_stock, low_stock, inspection_reviews, pending_inspections, overdue_ncrs, effectiveness_due, ncrs, overdue_invoices, unpaid_invoices, payment_review_invoices, unpaid_bills, due_soon_bills, maintenance_wo_unassigned.

## Readiness summary

Current seeders do not meet coverage, timestamp, safe fake identity, process-linkage, approval, analytics or performance requirements. Findings and plan are documented separately.
