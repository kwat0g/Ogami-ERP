# HR03-PUNCH — attempt 02

**Result:** Test issue; the UI time-in succeeded, then the read-only verifier failed while converting the timestamp. Time-out and DTR review were blocked.

- **Process step / actor:** Employee time-in / `employee`; HR remained open as observer.
- **Observed:** The UI displayed 6:00 AM and created the expected `present` attendance row. The verifier expected `2026-11-05 06:00:00` but returned `2026-11-04 22:00:00`.
- **Evidence:** The attendance migration declares `time_in` and `time_out` as PostgreSQL `timestamp without time zone`. A read-only query showed the row stored as `2026-11-05 06:00:00`; applying `AT TIME ZONE 'Asia/Manila'` incorrectly shifted the local wall time by eight hours. The punch column has the same timestamp type and storage convention.
- **Correction:** The read-only snapshot now formats these local wall-clock fields directly. This changed only the E2E verifier; application behavior and data were not changed.
- **Classification:** TST-HR-031, verifier timezone conversion error. Resolved after the clean-baseline full run passed.
- **Artifact note:** As with attempt 01, repeated titles reused the output directory; the final run's archived context and screenshots are retained at [full-run assets](assets/hr03-punch-full-run/). See [TST-INV-012](../FINDINGS.md#tst-inv-012--repeated-playwright-titles-reused-output-paths-and-lost-earlier-failure-artifacts).

