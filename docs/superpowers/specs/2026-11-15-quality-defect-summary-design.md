# Quality Inspection grouped defect summary

**Status:** Approved for implementation. No product code has been changed.

## Intent

For QC inspectors handling received material and finished production batches, replace the one-description-per-defective-piece workflow in Incoming and Outgoing Quality Inspection with one shared defect summary per inspection. The goal is to keep inspection manageable when many pieces share a defect and give inspectors a place to attach multiple photos. This is an Operate surface; preserve the existing application visual system.

## Selected direction

Use one inspection-level defect group, integrated with inspection submission. The defective quantity remains the source of truth for the inspection result. When it is above zero, the inspector records one category, one severity, one shared description, and at least one photo; multiple photos may be attached to that summary. If defects differ in severity, select the highest observed severity. If they span categories, use “Other” and describe the mix in the shared description.

Use one affected-quantity input in the defect-summary section as the inspection's defective count. Do not ask the inspector to enter the count twice or allow the two values to drift. Use whole pieces for PCS and retain source-unit precision for non-piece lots.

## Approaches considered

1. **Integrated summary, selected:** collect the shared description and photos as part of the inspection workflow, and block submission when defective quantity is positive until the description and at least one valid photo are present. This replaces repeated descriptions and keeps the inspection result and its evidence together.
2. **Supplemental evidence after inspection:** keep evidence separate and optional after the result is submitted. This is less connected to the inspection verdict and can leave a failed inspection without the explanation the user wants to require.
3. **Several defect groups per inspection:** split quantity, category, severity, description, and photos by defect type. This represents mixed causes better, but adds entry work and conflicts with the requested single shared group.

## Interaction and states

- Show one affected-quantity input in the defect-summary section. For positive quantity, show category, severity, a required shared description, and a multi-photo picker alongside it.
- Display selected photos with filenames or previews and a way to remove a photo before submission.
- Require at least one valid photo for a positive defective quantity. Keep the existing private-photo access pattern and JPEG/PNG, 10 MB per-photo limit unless product policy changes it.
- Keep the affected-quantity control visible at zero; hide the summary fields and photo picker, and allow the normal PASS path without a photo.
- Keep entered count, description, and category/severity on validation or upload failure so the inspector can correct or retry.
- Reject a defective quantity greater than the inspected/source quantity. The summary quantity is derived from the inspection count.
- After submission, display the saved summary and all attached photos in the inspection record.

## Scope and constraints

- Update Incoming and Outgoing inspection entry flows, including the separate Outgoing final work-order inspection screen.
- Preserve existing pass/fail rules, accepted quantities, independent review, NCR creation, and private photo delivery. Defect evidence documents the result; it must not independently recalculate the verdict.
- Current defect evidence is Incoming-only, supplemental, stores one photo per record, and has a separate affected-quantity field. Outgoing final inspection currently saves per-piece defect rows. The implementation must unify the new summary behavior without losing existing inspection history or duplicating the count.
- Model one summary separately from its photo attachments so multiple photos do not repeat or inflate its affected quantity. Preserve existing evidence records as historical data.
- Retain compatibility with source units: piece counts remain whole numbers; weight/volume lots may use the existing supported decimal precision.

## Resolved implementation details

- Allow up to 5 photos per summary, with the existing 10 MB per-image limit and JPG/PNG allowlist.
- Save draft summaries and uploaded photos to the server before result submission so they survive reloads; retain form entries after failed uploads for retry.
- Keep old per-piece descriptions and existing evidence readable for historical inspections; new inspections use the shared summary.
- Lock the summary after inspection submission. Additional later findings use the existing NCR/follow-up process.
