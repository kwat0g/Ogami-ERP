# Grouped Quality Defect Evidence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace per-piece defect descriptions in Incoming and Outgoing Quality Inspection with one required summary per defective inspection and multiple private photo attachments.

**Architecture:** Persist one `InspectionDefectSummary` per inspection and store its photos as child attachments. Save the summary while the inspection is draft, then require its affected quantity to match the submitted inspection defect count. Reuse the same summary editor in standard inspection forms and the separate Outgoing final work-order flow; keep existing pass/fail, acceptance, review, and NCR decisions authoritative.

**Tech Stack:** Laravel 12, PostgreSQL, React 18, TypeScript, TanStack Query, Vitest, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-15-quality-defect-summary-design.md`

## Global Constraints

- Use one inspection-level defect group.
- For a positive defective quantity, require one category, one severity, one shared description, and at least one valid photo.
- If defects differ in severity, select the highest observed severity.
- The summary quantity must equal the inspection's defective count; never ask for the same count twice.
- Use whole pieces for PCS and source-unit precision for non-piece lots.
- Preserve existing pass/fail rules, accepted quantities, independent review, NCR creation, and private photo delivery.
- Accept JPEG/PNG photos up to 10 MB each; allow multiple photos on one summary.
- Keep legacy evidence readable and do not invalidate inspections already awaiting review.

## Review Focus

- **Zero defects:** PASS does not need a summary or photo. Test in the result service and the shared editor.
- **PCS vs non-piece quantities:** PCS remains whole-number and bounded by inspected quantity; non-piece lots retain supported decimal precision. Test in summary API validation and quantity form.
- **Count mismatch or concurrent edit:** a saved summary whose quantity differs from the submitted count must block result submission. Test in both standard and final work-order submission services.
- **Missing/invalid attachments:** positive defects cannot submit without description and at least one valid photo; image type/size and failed upload must leave recoverable entries. Test in API and editor.
- **Existing awaiting-review inspections:** pre-change per-piece evidence must remain reviewable and historical evidence must remain readable. Test the legacy branch in full-piece review and detail resources.

---

### Task 1: Add inspection-level summary and private photo API

**Files:**
- Create: `api/database/migrations/<timestamp>_create_inspection_defect_summaries.php`
- Create: `api/app/Modules/Quality/Models/InspectionDefectSummary.php`
- Create: `api/app/Modules/Quality/Models/InspectionDefectSummaryPhoto.php`
- Create: `api/app/Modules/Quality/Requests/SaveInspectionDefectSummaryRequest.php`
- Create: `api/app/Modules/Quality/Controllers/InspectionDefectSummaryController.php`
- Create: `api/app/Modules/Quality/Resources/InspectionDefectSummaryResource.php`
- Create: `api/app/Modules/Quality/Services/InspectionDefectSummaryService.php`
- Modify: `api/app/Modules/Quality/Models/Inspection.php`
- Modify: `api/app/Modules/Quality/routes.php`
- Test: `api/tests/Feature/Quality/InspectionDefectSummaryTest.php`

**Interfaces:**
- `GET /quality/inspections/{inspection}/defect-summary` returns `{ data: InspectionDefectSummary | null }` with photo metadata and private photo URLs.
- `PUT /quality/inspections/{inspection}/defect-summary` accepts multipart fields `category`, optional `other_category`, `severity`, `affected_quantity`, `description`, `photos[]`, and optional `remove_photo_ids[]`; it creates or updates the one draft summary.
- `DELETE /quality/inspections/{inspection}/defect-summary` clears a draft summary when the inspector changes the affected count to zero.
- `GET /quality/defect-summary-photos/{photo}/file` streams one attachment through the existing authenticated/private storage pattern.
- `InspectionDefectSummaryService::save(Inspection $inspection, array $data, array $photos, array $removePhotoIds, User $by): InspectionDefectSummary` validates editable state/source bounds, persists summary metadata and child files, and cleans stored files if persistence fails.
- `InspectionDefectSummaryService::assertMatches(Inspection $inspection, string $quantity): ?InspectionDefectSummary` requires a matching saved summary and at least one photo when the quantity is positive.
- Store one summary row per inspection; store each image in a child photo row so the quantity and description are not repeated per photo. Preserve the existing `inspection_defect_evidence` table as legacy history.
- Cap each new summary at 5 photos; keep the existing 10 MB per-photo limit and JPG/PNG allowlist.

- [x] **Step 1: Add failing feature tests** for incoming and outgoing summaries, 1–5 private photos, missing description/photo, invalid file type/size, quantity above source, non-manager access, and clearing a draft summary.
- [x] **Step 2: Run** `cd api && php artisan test --filter=InspectionDefectSummaryTest` and confirm the new behavior fails for missing routes/models.
- [x] **Step 3: Add the parent and photo migrations/models/relations.** The summary has a unique `inspection_id`; photos reference the summary and store private file metadata.
- [x] **Step 4: Implement request, resource, controller, routes, and service** with the interfaces above. Permit Incoming and Outgoing draft inspections; bind any GRN relation from the inspection rather than trusting request data.
- [x] **Step 5: Run** `cd api && php artisan test --filter=InspectionDefectSummaryTest` and `cd api && php artisan test --filter=InspectionDefectPhotoSeverityTest`.

### Task 2: Enforce summary/count consistency in inspection result services

**Files:**
- Modify: `api/app/Modules/Quality/Services/InspectionService.php`
- Modify: `api/app/Modules/Quality/Services/WorkOrderFinalInspectionService.php`
- Modify: `api/app/Modules/Quality/Services/CoCService.php`
- Modify: `api/app/Modules/Quality/Resources/InspectionResource.php`
- Modify: `api/app/Modules/Quality/Resources/InspectionDefectSummaryResource.php`
- Test: `api/tests/Feature/Quality/InspectionDefectSummaryTest.php`
- Test: `api/tests/Feature/Quality/FullPieceWorkOrderInspectionTest.php`
- Test: `api/tests/Feature/Quality/InspectionLifecycleConcurrencyTest.php`

**Interfaces:**
- Call `InspectionDefectSummaryService::assertMatches()` before any result transition with a positive physical defect count, including quantity-result, counted-batch/checklist, and full-piece work-order submissions.
- For zero defects, allow PASS without a summary and clear an unsent draft summary before finalizing.
- Full-piece final inspection evidence may use a saved summary instead of one `InspectionDefectivePiece` description per item. Continue validating every required size reading and that counted defects cover unique out-of-tolerance pieces.
- Keep an explicit legacy path for already-submitted/awaiting-review inspections that only have `InspectionDefectivePiece` rows.
- Set the inspection's persisted problem description from the shared summary so existing NCR creation continues to receive the reason. Summary severity is descriptive metadata and does not alter the calculated result or `critical_defect`.

- [x] **Step 1: Add failing service tests** for positive count without summary, matching summary, mismatched summary count, zero defects without summary, zero-count cleanup, aggregate outgoing evidence with no per-piece text rows, and legacy awaiting-review evidence.
- [x] **Step 2: Run** `cd api && php artisan test --filter=InspectionDefectSummaryTest` and `cd api && php artisan test --filter=FullPieceWorkOrderInspectionTest` to confirm failures.
- [x] **Step 3: Add the summary check to standard result paths** and set `Inspection.problem_description` from the saved shared description while preserving current verdict calculations.
- [x] **Step 4: Update full-piece assertion, outcome, and final-work-order submit paths** to accept an aggregate summary for new submissions; preserve old per-piece validation for existing awaiting-review records and preserve size-reading checks.
- [x] **Step 5: Confirm CoC still accepts only zero-defect passing evidence** and does not treat summary severity as a verdict input.
- [x] **Step 6: Run** `cd api && php artisan test --filter=InspectionDefectSummaryTest`, `cd api && php artisan test --filter=FullPieceWorkOrderInspectionTest`, `cd api && php artisan test --filter=ConfirmedCountOutgoingInspectionTest`, `cd api && php artisan test --filter=InspectionNcrTest`, and `cd api && php artisan test --filter=CoCEvidenceIntegrityTest`.

### Task 3: Build a reusable grouped-summary editor and API client

**Files:**
- Create: `spa/src/api/quality/defectSummary.ts`
- Create: `spa/src/components/quality/InspectionDefectSummaryEditor.tsx`
- Create: `spa/src/components/quality/InspectionDefectSummaryEditor.test.tsx`
- Modify: `spa/src/types/quality.ts`

**Interfaces:**
- `InspectionDefectSummary` includes `id`, `category`, `other_category`, `severity`, `affected_quantity`, `description`, `photos[]`, and audit fields.
- `defectSummaryApi.show(inspectionId)` loads the current summary; `save(inspectionId, data)` submits metadata, added photos, and removed photo IDs; `clear(inspectionId)` removes a draft summary.
- `InspectionDefectSummaryEditor` receives `inspectionId`, `sourceQuantity`, `unit`, controlled `quantity: string`, `onQuantityChange(quantity: string)`, `canManage`, and `onSaved(summary)`; it renders the sole quantity input and, for a positive value, category/severity/shared description/photo selection plus an explicit “Save defect summary” action.
- Save the summary and new private photo attachments before enabling inspection submission; reload restores the saved draft through `show()`. A failed save retains text and selected files for retry.
- Use one summary for all defects. For mixed categories, choose `Other` and describe the mix. Display/select the highest severity observed.

- [x] **Step 1: Add failing component tests** for one shared description at quantity 500, no per-piece fields, zero-defect PASS without evidence, required description/photo for positive count, multiple photos, file removal, saved summary restored after reload, and retained entries after API error.
- [x] **Step 2: Run** `cd spa && npm run test:run -- src/components/quality/InspectionDefectSummaryEditor.test.tsx` and confirm the editor tests fail.
- [x] **Step 3: Add API types/client and implement the editor** with category/severity controls, shared description, multi-file JPG/PNG selection, preview/remove, per-file 10 MB validation, and explicit summary save/clear state.
- [x] **Step 4: Run** `cd spa && npm run test:run -- src/components/quality/InspectionDefectSummaryEditor.test.tsx`.

### Task 4: Replace repeated fields in standard Incoming and Outgoing forms

**Files:**
- Modify: `spa/src/pages/quality/inspections/components/QuantityInspectionPanel.tsx`
- Modify: `spa/src/pages/quality/inspections/components/QuantityInspectionPanel.test.tsx`
- Modify: `spa/src/pages/quality/inspections/components/CountedBatchInspectionPanel.tsx`
- Modify: `spa/src/pages/quality/inspections/components/CountedBatchInspectionPanel.test.tsx`
- Modify: `spa/src/pages/quality/inspections/components/ChecklistDecisionPanel.tsx`
- Modify: `spa/src/pages/quality/inspections/components/InspectionChecklistPanel.tsx`
- Modify: `spa/src/api/quality/inspections.ts`
- Modify: `spa/src/types/quality.ts`

**Interfaces:**
- Standard forms submit the same defective quantity to the result API after the editor has saved a matching summary.
- Remove `reasons[]` and per-defective-piece textareas from quantity-based inspections.
- Retain descriptions for failed inspection requirements that are not physical defect-count descriptions; they remain distinct from the shared defect summary.
- Disable result submit for a positive count until the saved summary quantity matches and has a description plus at least one photo. The backend remains authoritative.

- [x] **Step 1: Update component tests** to assert that quantity 500 renders one summary description, result submission is disabled before summary save, the matching count is sent, and zero defects still submits without photos.
- [x] **Step 2: Run** `cd spa && npm run test:run -- src/pages/quality/inspections/components/QuantityInspectionPanel.test.tsx src/pages/quality/inspections/components/CountedBatchInspectionPanel.test.tsx` and confirm expected failures.
- [x] **Step 3: Integrate the shared editor into standard quantity/count/checklist flows** and remove only per-piece physical defect descriptions; preserve measurement, checklist, and segregation behavior.
- [x] **Step 4: Run** `cd spa && npm run test:run -- src/pages/quality/inspections/components/QuantityInspectionPanel.test.tsx src/pages/quality/inspections/components/CountedBatchInspectionPanel.test.tsx src/pages/quality/inspections/components/ChecklistDecisionPanel.test.tsx`.

### Task 5: Replace paginated per-piece descriptions in Outgoing final work-order inspection

**Files:**
- Modify: `spa/src/pages/quality/inspections/components/WorkOrderFinalInspectionPanel.tsx`
- Modify: `spa/src/pages/quality/inspections/components/WorkOrderFinalInspectionPanel.test.tsx`
- Modify: `spa/src/pages/quality/inspections/components/SimpleWorkOrderFinalInspectionPanel.tsx`
- Modify: `spa/src/pages/quality/inspections/work-order-final.tsx`
- Modify: `spa/src/types/quality-final.ts`
- Modify: `spa/src/api/quality/inspections.ts`
- Modify: `api/app/Modules/Quality/Requests/SaveWorkOrderFinalInspectionEvidenceRequest.php`

**Interfaces:**
- Keep per-piece paging only for required size readings. Remove defective-piece numbering, problem textareas, shared-problem bulk apply, and defect-description pagination.
- Use one summary editor for the selected output batch. Save its affected quantity as the batch's existing `sample_defect_count`.
- Work-order submit requires a matching summary for each batch with positive defects; zero-defect batches need none.
- Continue sending the existing work-order manifest and coverage confirmation unchanged.

- [x] **Step 1: Update final-inspection tests** for 500 defects with one shared description, multiple photos, no defect-page navigation, size-reading pagination retained, and submit blocked without a saved summary.
- [x] **Step 2: Run** `cd spa && npm run test:run -- src/pages/quality/inspections/components/WorkOrderFinalInspectionPanel.test.tsx src/pages/quality/inspections/components/SimpleWorkOrderFinalInspectionPanel.test.tsx` and confirm expected failures.
- [x] **Step 3: Replace per-piece defect inputs with the shared editor** while preserving batch selection, size readings, offline submission recovery, manifest validation, and independent review.
- [x] **Step 4: Run** `cd spa && npm run test:run -- src/pages/quality/inspections/components/WorkOrderFinalInspectionPanel.test.tsx src/pages/quality/inspections/components/SimpleWorkOrderFinalInspectionPanel.test.tsx src/pages/quality/inspections/work-order-final.test.tsx`.

### Task 6: Display grouped and historical evidence, then verify the complete flow

**Files:**
- Modify: `spa/src/pages/quality/inspections/detail.tsx`
- Modify: `spa/src/pages/quality/inspections/detail.test.tsx`
- Modify: `spa/src/pages/quality/inspections/work-order-final.tsx`
- Modify: `api/app/Modules/Quality/Resources/InspectionResource.php`
- Modify: `api/app/Modules/Quality/Resources/InspectionDefectSummaryResource.php`
- Test: `api/tests/Feature/Quality/InspectionDefectSummaryTest.php`
- Test: `spa/src/pages/quality/inspections/detail.test.tsx`

**Interfaces:**
- Both stages show one saved summary with affected quantity, category, severity, shared description, and all private photo attachments.
- Existing `inspection_defect_evidence` rows remain visible as historical evidence and are not added into the new summary quantity.
- Photos remain inaccessible to users without Quality view permission.

- [x] **Step 1: Add display tests** for Incoming and Outgoing summaries with multiple private photos, and for legacy Incoming evidence remaining visible without double-counting.
- [x] **Step 2: Run** `cd spa && npm run test:run -- src/pages/quality/inspections/detail.test.tsx` and confirm expected failures.
- [x] **Step 3: Add saved summary to inspection/final-work-order responses** and render the summary and all photos on detail pages; label old evidence as historical where both exist.
- [x] **Step 4: Run targeted backend tests:** `cd api && php artisan test --filter=InspectionDefectSummaryTest` and `cd api && php artisan test --filter=InspectionLifecycleConcurrencyTest`.
- [x] **Step 5: Run targeted frontend tests:** `cd spa && npm run test:run -- src/components/quality/InspectionDefectSummaryEditor.test.tsx src/pages/quality/inspections/components/QuantityInspectionPanel.test.tsx src/pages/quality/inspections/components/CountedBatchInspectionPanel.test.tsx src/pages/quality/inspections/components/WorkOrderFinalInspectionPanel.test.tsx src/pages/quality/inspections/detail.test.tsx`.
- [x] **Step 6: Run** `cd spa && npm run typecheck` and `cd api && ./vendor/bin/phpstan analyse app/Modules/Quality` if the project quality gate supports targeted analysis.

---

## Self-review

- The approved spec's positive/zero defect behavior is covered by Tasks 2–5.
- Incoming and Outgoing detail views, all standard quantity/count flows, and the separate final work-order flow are covered by Tasks 4–6.
- Multiple private photos, file limits, retention on errors, and legacy evidence are covered by Tasks 1, 3, and 6.
- Pass/fail, NCR, CoC, review, source-unit precision, and legacy awaiting-review behavior are covered by Tasks 2 and 6.
- The implementation is split by reviewable boundaries: persistence/API, server enforcement, reusable editor, standard forms, final work-order form, display/verification.
