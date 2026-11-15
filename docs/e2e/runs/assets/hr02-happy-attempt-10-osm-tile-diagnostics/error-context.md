# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-recruitment.process.spec.ts >> HR02-HAPPY — candidate applies through the posting lifecycle and HR hires through interview and offer
- Location: e2e-process/hr-recruitment.process.spec.ts:260:1

# Error details

```
Error: Unexpected browser/network errors during candidate application submission:
- requestfailed: GET https://b.tile.openstreetmap.org/16/54783/30139.png: net::ERR_NAME_NOT_RESOLVED
- console: Failed to load resource: net::ERR_NAME_NOT_RESOLVED at /16/54783/30139.png
- requestfailed: GET https://c.tile.openstreetmap.org/16/54783/30140.png: net::ERR_NAME_NOT_RESOLVED
- console: Failed to load resource: net::ERR_NAME_NOT_RESOLVED at /16/54783/30140.png
- requestfailed: GET https://c.tile.openstreetmap.org/16/54784/30139.png: net::ERR_NAME_NOT_RESOLVED
- console: Failed to load resource: net::ERR_NAME_NOT_RESOLVED at /16/54784/30139.png
- requestfailed: GET https://c.tile.openstreetmap.org/16/54782/30138.png: net::ERR_NAME_NOT_RESOLVED
- console: Failed to load resource: net::ERR_NAME_NOT_RESOLVED at /16/54782/30138.png
- requestfailed: GET https://a.tile.openstreetmap.org/16/54782/30139.png: net::ERR_NAME_NOT_RESOLVED
- console: Failed to load resource: net::ERR_NAME_NOT_RESOLVED at /16/54782/30139.png
- requestfailed: GET https://a.tile.openstreetmap.org/16/54783/30138.png: net::ERR_NAME_NOT_RESOLVED
- console: Failed to load resource: net::ERR_NAME_NOT_RESOLVED at /16/54783/30138.png
- requestfailed: GET https://b.tile.openstreetmap.org/16/54784/30138.png: net::ERR_NAME_NOT_RESOLVED
- console: Failed to load resource: net::ERR_NAME_NOT_RESOLVED at /16/54784/30138.png
- requestfailed: GET https://b.tile.openstreetmap.org/16/54782/30140.png: net::ERR_NAME_NOT_RESOLVED
- console: Failed to load resource: net::ERR_NAME_NOT_RESOLVED at /16/54782/30140.png
- requestfailed: GET https://a.tile.openstreetmap.org/16/54784/30140.png: net::ERR_NAME_NOT_RESOLVED
- console: Failed to load resource: net::ERR_NAME_NOT_RESOLVED at /16/54784/30140.png
```

# Test source

```ts
  1  | import { readFile } from 'node:fs/promises';
  2  | import path from 'node:path';
  3  | import type { Page } from '@playwright/test';
  4  | 
  5  | type IssueKind = 'console' | 'pageerror' | 'requestfailed' | 'http5xx';
  6  | type NoiseAllowlist = Record<IssueKind, string[]>;
  7  | type Issue = { kind: IssueKind; evidence: string };
  8  | 
  9  | export class PageDiagnostics {
  10 |   private readonly issues: Issue[] = [];
  11 |   private readonly expectedConsoleErrors: string[] = [];
  12 | 
  13 |   constructor(private readonly allowlist: NoiseAllowlist) {}
  14 | 
  15 |   record(kind: IssueKind, evidence: string): void {
  16 |     const cleanEvidence = evidence.replace(/[?&](?:token|password|secret|authorization)=[^&\s]*/gi, '[redacted]');
  17 |     if (kind === 'console') {
  18 |       const expectedIndex = this.expectedConsoleErrors.findIndex((fragment) => cleanEvidence.includes(fragment));
  19 |       if (expectedIndex !== -1) {
  20 |         this.expectedConsoleErrors.splice(expectedIndex, 1);
  21 |         return;
  22 |       }
  23 |     }
  24 |     if (this.allowlist[kind].some((known) => cleanEvidence.includes(known))) return;
  25 |     this.issues.push({ kind, evidence: cleanEvidence });
  26 |   }
  27 | 
  28 |   expectConsoleErrorOnceContaining(fragment: string): void {
  29 |     this.expectedConsoleErrors.push(fragment);
  30 |   }
  31 | 
  32 |   assertClean(stepName = 'current process step'): void {
  33 |     if (this.issues.length === 0) return;
  34 |     const details = this.issues.map(({ kind, evidence }) => `- ${kind}: ${evidence}`).join('\n');
> 35 |     throw new Error(`Unexpected browser/network errors during ${stepName}:\n${details}`);
     |           ^ Error: Unexpected browser/network errors during candidate application submission:
  36 |   }
  37 | }
  38 | 
  39 | async function loadAllowlist(): Promise<NoiseAllowlist> {
  40 |   const file = path.resolve('e2e-process/known-network-noise.json');
  41 |   const parsed = JSON.parse(await readFile(file, 'utf8')) as Partial<NoiseAllowlist>;
  42 |   return {
  43 |     console: parsed.console ?? [],
  44 |     pageerror: parsed.pageerror ?? [],
  45 |     requestfailed: parsed.requestfailed ?? [],
  46 |     http5xx: parsed.http5xx ?? [],
  47 |   };
  48 | }
  49 | 
  50 | export async function monitorPage(page: Page): Promise<PageDiagnostics> {
  51 |   const diagnostics = new PageDiagnostics(await loadAllowlist());
  52 |   page.on('console', (message) => {
  53 |     if (message.type() !== 'error') return;
  54 |     let location = '';
  55 |     try {
  56 |       location = ` at ${new URL(message.location().url).pathname}`;
  57 |     } catch {
  58 |       // Some browser-generated messages have no source URL.
  59 |     }
  60 |     diagnostics.record('console', `${message.text().slice(0, 500)}${location}`);
  61 |   });
  62 |   page.on('pageerror', (error) => diagnostics.record('pageerror', error.message.slice(0, 500)));
  63 |   page.on('requestfailed', (request) => {
  64 |     let location = request.url();
  65 |     try {
  66 |       const url = new URL(request.url());
  67 |       location = `${url.origin}${url.pathname}`;
  68 |     } catch {
  69 |       // Keep a non-URL request string only when the browser cannot parse it.
  70 |     }
  71 |     diagnostics.record('requestfailed', `${request.method()} ${location}: ${request.failure()?.errorText ?? 'request failed'}`);
  72 |   });
  73 |   page.on('response', (response) => {
  74 |     if (response.status() < 500) return;
  75 |     const url = new URL(response.url());
  76 |     diagnostics.record('http5xx', `${response.status()} ${url.origin}${url.pathname}`);
  77 |   });
  78 |   return diagnostics;
  79 | }
  80 | 
```