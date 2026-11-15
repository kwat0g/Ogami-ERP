# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: production-stock.floor.spec.ts >> PROD01-HAPPY — stock work order, Warehouse handover, startup QC, production, and outgoing release
- Location: e2e-process/production-stock.floor.spec.ts:284:1

# Error details

```
Error: browserType.launch: Target page, context or browser has been closed
Browser logs:

<launching> /ms-playwright/webkit-2359/pw_run.sh --inspector-pipe --headless --no-startup-window --disable-dev-shm-usage --no-sandbox --ignore-certificate-errors
<launched> pid=349
[pid=349][err] Cannot parse arguments: Unknown option --disable-dev-shm-usage
Call log:
  - <launching> /ms-playwright/webkit-2359/pw_run.sh --inspector-pipe --headless --no-startup-window --disable-dev-shm-usage --no-sandbox --ignore-certificate-errors
  - <launched> pid=349
  - [pid=349][err] Cannot parse arguments: Unknown option --disable-dev-shm-usage
  - [pid=349] <gracefully close start>
  - [pid=349] <kill>
  - [pid=349] <will force kill>
  - [pid=349] <process did exit: exitCode=1, signal=null>
  - [pid=349] starting temporary directories cleanup
  - [pid=349] finished temporary directories cleanup
  - [pid=349] <gracefully close end>

```