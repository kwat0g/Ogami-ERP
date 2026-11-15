# HR08-TERMINATION — attempt 03: host-test guard interrupted the journey

**Overall result:** Environment interruption during the journey. The attempt is not counted as complete and does not establish a product defect.

- **Run ID:** `885f8714-3915-4ee0-ac02-11b863843342`; clearance `CLR-202611-0001`; E2E employee `E2E-2FDC0177057D3A5`.
- **Environment:** The supervisor observed a fresh 300-second stack-free/PHP-test-free interval, started the isolated E2E stack, waited for healthy services, and invoked the guarded `test` command. Fresh migrations and the E2E baseline seed completed. The E2E TypeScript preflight and real-UI authentication setup passed for the eight selected role sessions.
- **Journey:** Five ordered steps passed with the owning sessions: HR initiated termination, Finance followed the clearance notification, System Admin opened the observer, and the Production Department Head signed both Production items. At step 6, Warehouse Staff's expected `Separations & clearances` heading did not become visible within the assertion timeout. The remaining ten steps were marked blocked in the saved journey context.
- **Guard interruption:** At 18:07:46 UTC the monitor found a concurrent host process running `docker compose exec -T api php artisan test --filter=test_a_stale_processing_outbox_message_marks_delivery_degraded tests/Unit/Common/Services/RealtimeQueueHealthTest.php`. It terminated only the guarded E2E runner with exit 91 and brought down the E2E stack. The browser's clock-reset cleanup then reported `getaddrinfo EAI_AGAIN nginx` during teardown. Because the business journey overlapped a PHP test, the step-6 UI failure is retained as an unconfirmed observation and is not classified as a product finding.
- **Isolation:** All transactional actions shown in the saved context ran through the selected role's UI session against the dedicated E2E baseline. No development database action was used by this journey. At teardown no E2E containers remained. A fresh baseline is required for any rerun.

## Evidence

- [Journey context](assets/hr08-termination-attempt-03-host-test-interruption/journey-context.json) records five passed steps, step 6 failed, and steps 7–16 blocked.
- [Sanitized Playwright trace](assets/hr08-termination-attempt-03-host-test-interruption/trace.zip); the artifact sanitizer reported clean after redaction.
- [Annotated walkthrough screenshots](assets/hr08-termination-attempt-03-host-test-interruption/walkthrough/)
- The raw Playwright error dump was excluded because it contained the E2E control header. No final clean Playwright summary was produced.

**Classification:** Environment / run-coordination interruption under `ENV-E2E-008`. Do not count the five recorded actions as a completed journey. Rerun only after another fresh 300-second stack-free and PHP-test-free interval.
