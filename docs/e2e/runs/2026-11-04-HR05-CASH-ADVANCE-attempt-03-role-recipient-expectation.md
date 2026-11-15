# HR05-CASH-ADVANCE — attempt 03 (recipient expectation mismatch)

- **Date:** 2026-11-04 UTC
- **Classification:** E2E test issue (TST-HR-057); no application finding or fix
- **Journey:** `HR05-CASH-ADVANCE`, third browser invocation
- **Actors:** Employee, Department Head, Finance Officer, Vice President, HR Officer observer; each used an independent authenticated browser context
- **Command:** `E2E_AUTH_ACTORS=employee,department_head,finance_officer,vice_president,hr_officer node ../scripts/e2e/runner.mjs test-split --grep HR05-CASH-ADVANCE`
- **Result:** Fresh E2E baseline and all five selected real-UI logins passed. One headless Chromium worker ran the process journey. The Employee submitted a request through Self-service Loans (HTTP 201). The pending state, three pending approval rows, zero journal rows, and HR observer's live list/detail update passed. The step then failed because the test expected one submission FYI recipient while the configured role audience correctly contained both seeded Finance Officers. Department Head, Finance, and VP approval steps did not run.
- **Run data:** Playwright context `f747c622-0770-4aab-aaa0-f99f8a0f4352`; loan `CA-202611-0001`; request hash ID `j16KyzV0Xl`; principal ₱1,000.00; two payroll periods.

## Step result

| Step | Actor | Result |
|---|---|---|
| 1. Submit cash advance through Profile menu → Loans → Apply for a loan | Employee; HR Officer observer | **UI action and process-state assertions passed.** The UI returned HTTP 201. Read-only checks found status `pending`, the configured three-step Department Head → Finance → VP chain pending in order, no disbursement journal, and no payment. The already-open HR loan list received the row and opened its detail without reload. The step's recipient invariant then failed because its expected recipient set contained only the primary Finance Officer. The app delivered the configured `loans.submitted` notification to both eligible Finance Officer accounts. |
| 2. Follow the approval hand-off and approve | Department Head | **Blocked.** Not run. |
| 3. Follow the approval hand-off and approve | Finance Officer | **Blocked.** Not run. |
| 4. Follow the approval hand-off and approve | Vice President | **Blocked.** Not run. |

## Recipient expectation correction

The observed recipients were `e2e+finance_officer@ogami-e2e.test` and `e2e+finance_officer-2@ogami-e2e.test`. This is consistent with source: migration `api/database/migrations/0376_seed_workflow_notification_roles.php` configures `loans.submitted.notification_roles` as `['finance_officer']`; `NotifyOnLoanSubmitted` selects every active user with one of those roles, `loans.view`, and loan access. `E2EAccountSeeder` deliberately seeds `finance_officer` and `finance_officer__2` as distinct same-role users. The extra notice is therefore expected group delivery, not a product defect. The E2E recipient assertions now include both Finance accounts, while the actionable Department Head and VP sets remain scoped to their applicable approval stages. Attempt 04's fresh-baseline run passed the corrected exact Finance recipient assertion. Its separate initial Department Head hand-off check is under investigation in TST-HR-058.

## Earlier locator corrections

The first browser attempt kept the Employee Loans page in its loading state after the test tried to load all five actors' application pages together; canceled startup requests were logged as HTTP 499, and no transaction was created. The test now opens the Employee and observer pages sequentially and defers other role pages until their turn. The second browser attempt reached the form but stopped on a strict required-field label locator; no transaction was created. In this attempt, the role-based Type locator and anchored Amount/period label patterns succeeded, and the UI submission completed. These are test-authoring issues, not application findings. The earlier attempt artifacts were overwritten by the Playwright result directory; only the current attempt's archived artifacts are retained.

## Artifacts and limits

- [Trace, failure context, screenshots, and journey context](assets/hr05-cash-advance-attempt-03/). The Playwright trace archive was scrubbed of credential-bearing headers and URL parameters before retention; the cleanup did not print removed values.
- Only the first of four process steps ran. No approval, payroll deduction, email, or final journal behavior was exercised. No application code changed.
