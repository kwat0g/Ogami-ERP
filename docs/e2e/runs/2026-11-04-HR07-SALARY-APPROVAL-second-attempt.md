# HR07-SALARY-APPROVAL — Compensation-message locator attempt

**Date:** 2026-11-04 UTC  
**Classification:** Test issue; salary-request gap step not reached  
**Environment:** Guarded E2E project; fresh baseline  
**Result:** 1 HR UI auth setup passed; 1 business test failed during UI discovery

The HR actor navigated from Dashboard through the Human Resources sidebar to Employees, opened the seeded E2E Employee, and opened Edit. The screenshot shows the edit page and the visible compensation workflow guidance. The exact-text locator did not match because the sentence is split around the inline “Open salary adjustments” link. The salary queue was not inspected, and the journey did not reach the GAP-UI-02 step. No transactional record was created.

Screenshots, trace, error context, and Playwright JSON are archived in [the attempt assets](assets/hr07-salary-approval-second-attempt/). This is test issue TST-HR-015. The assertion now matches the visible sentence while keeping the request-action absence checks intact.
