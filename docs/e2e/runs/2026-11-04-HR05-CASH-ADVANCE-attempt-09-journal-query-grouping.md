# HR05-CASH-ADVANCE — attempt 09 (journal-reader SQL grouping)

- **Date:** 2026-11-04 UTC
- **Classification:** E2E test issue, TST-HR-063
- **Journey:** `HR05-CASH-ADVANCE`, ninth browser invocation
- **Actors:** Employee; Department Head; Finance Officer; Vice President; HR Officer observer
- **Command:** `E2E_AUTH_ACTORS=employee,department_head,finance_officer,vice_president,hr_officer node ../scripts/e2e/runner.mjs test-split --grep HR05-CASH-ADVANCE`
- **Result:** The fresh migration and baseline seeding completed and all selected actors signed in through the UI. Employee submitted a cash advance through Self-service Loans. The read-only `journalRows()` query then failed during SQL planning because the newly selected `poster.email` field was omitted from `GROUP BY`. The approval queue and all approver actions were blocked; the UI-created pending request was left in the disposable database until the next fresh reset.
- **Correction:** Add `poster.email` to the grouping expression. TypeScript compilation alone did not validate the SQL at runtime. The query will be checked by the next clean-baseline journey.
- **Run context:** `7d0026f8-58ab-449f-ab49-fe49e07a2b15`; loan `CA-202611-0001`; public loan ID `j16KyzV0Xl`; principal ₱1,000.00.
- **Artifacts:** [Sanitized trace, failure context, screenshots, and journey context](assets/hr05-cash-advance-attempt-09/).
- No application code changed. The only transactional action in this attempt was the Employee UI submission.
