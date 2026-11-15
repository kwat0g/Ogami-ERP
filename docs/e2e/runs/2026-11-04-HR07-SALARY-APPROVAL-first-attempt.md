# HR07-SALARY-APPROVAL — Initial UI discovery attempt

**Date:** 2026-11-04 UTC  
**Classification:** Test issue; process-gap step not reached  
**Environment:** Guarded E2E stack; fresh `ogami_e2e` baseline  
**Result:** 1 auth setup passed; 1 business test failed before UI navigation

HR signed in through the real login UI. The journey then failed its first UI locator because `actorPage` created a blank page and the discovery test expected the authenticated Modules navigation without first opening the dashboard. No business step was reached; no request, approval, or transactional record was created. The known salary-request UI gap was not counted as runtime-confirmed in this attempt.

The screenshots, trace, error context, and Playwright results are archived in [the attempt assets](assets/hr07-salary-approval-first-attempt/). This is test issue TST-HR-014. The selector is unchanged; the corrected journey will enter the role's dashboard and continue through the sidebar from a fresh baseline.
