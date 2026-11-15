# HR03-PUNCH — attempt 01

**Result:** Test issue; the business action succeeded, but the journey stopped during its first time-in UI assertion. Auth setup and the first two process steps passed. The remaining time-out and DTR steps were blocked.

- **Process step / actor:** Employee time-in / `employee`; HR remained open as observer.
- **Observed:** The UI POST returned HTTP 201 and displayed the successful 6:00 AM punch. `getByText(/Timed in at 6:00 AM/)` resolved to three elements: the screen-reader announcement, the visible result, and the toast. Playwright strict mode stopped the test before its read-only state assertions.
- **Side effect:** One open attendance row and one UI-created time-in punch existed in the disposable E2E database. They were discarded by the next guarded fresh reset; no API or database mutation advanced the process.
- **Classification:** TST-HR-030, ambiguous visible-result locator. No product defect inferred.
- **Artifact note:** The Playwright output directory is reused for repeated titles, so this run's failure artifact was replaced by a later run. This is covered by [TST-INV-012](../FINDINGS.md#tst-inv-012--repeated-playwright-titles-reused-output-paths-and-lost-earlier-failure-artifacts).

