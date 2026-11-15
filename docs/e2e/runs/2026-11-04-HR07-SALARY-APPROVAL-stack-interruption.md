# HR07-SALARY-APPROVAL — E2E stack readiness interruption

**Date:** 2026-11-04 UTC  
**Classification:** Environment issue (ENV-E2E-002)  
**Environment:** Dedicated `ogami-erp-e2e` Compose project only

Before the salary journey's fresh baseline, the stack was not running. The guarded `runner.mjs seed` command refused to execute because the 12-service E2E stack was not ready; the baseline database was not reset by that invocation and no browser journey ran. The guarded `up` and `wait` commands restored a healthy isolated stack, after which `runner.mjs seed` completed and reported 37 isolated E2E actors with no transactional demo records. The salary journey then ran from that fresh baseline.

This is a recurrence of ENV-E2E-002. The readiness guard prevented the seed from running against a stopped stack; it does not explain why the stack had stopped. The separate development Compose project was not used. The stack later remained healthy through seed, actor login, and the HR UI discovery run.
