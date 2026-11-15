# INV01-HIGH-REJECT-RESUBMIT — full auth setup interruption

**Date:** 2026-11-04 UTC  
**Classification:** E2E environment issue (ENV-E2E-002)  
**Result:** Auth setup interrupted; business journey did not start

The dedicated E2E baseline reset completed successfully and reported 35 isolated actors with no transactional demo records. The auth-setup project then began signing in all actors through the real UI. The browser invocation exited **143** before the auth test reported a result; 14 actor storage-state files had been refreshed. Because the journey command was sequenced after successful auth setup, the business journey did not execute and no process record was created.

Read-only Docker state showed the Compose services stopped in the same interval. The API and database exited cleanly; the SPA exited 143; queue containers exited 137 with `OOMKilled=false`. No OOM was reported for the inspected services. The root cause of the runner termination remains unknown.

To continue this journey from the same clean baseline, the auth setup gained an optional `E2E_AUTH_ACTORS` selector. It defaults to all manifest actors. A subsequent run selected `finance_officer` and `warehouse_staff`; both signed in through the real UI, then [the business journey passed](2026-11-04-INV01-HIGH-REJECT-RESUBMIT-full-run.md).

No application behavior was changed. This interruption is not counted as a passed or failed business journey and did not trigger a retry of the process test.
