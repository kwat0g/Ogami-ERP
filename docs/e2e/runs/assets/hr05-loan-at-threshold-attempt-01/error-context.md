# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-loans.process.spec.ts >> HR05-LOAN-AT-THRESHOLD — Company loan at ₱50,000 requires VP approval
- Location: e2e-process/hr-loans.process.spec.ts:916:1

# Error details

```
Error: Unexpected browser/network errors during current process step:
- console: Failed to load resource: the server responded with a status of 403 (Forbidden) at https://nginx/api/v1/loans/j16KyzV0Xl
- console: Failed to load resource: the server responded with a status of 403 (Forbidden) at https://nginx/api/v1/loans/j16KyzV0Xl
- console: Failed to load resource: the server responded with a status of 403 (Forbidden) at https://nginx/api/v1/loans/j16KyzV0Xl
- console: Failed to load resource: the server responded with a status of 403 (Forbidden) at https://nginx/api/v1/loans/j16KyzV0Xl
- console: Failed to load resource: the server responded with a status of 403 (Forbidden) at https://nginx/api/v1/loans/j16KyzV0Xl
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
     |           ^ Error: Unexpected browser/network errors during current process step:
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
  56 |       const url = new URL(message.location().url);
  57 |       location = ` at ${url.origin}${url.pathname}`;
  58 |     } catch {
  59 |       // Some browser-generated messages have no source URL.
  60 |     }
  61 |     diagnostics.record('console', `${message.text().slice(0, 500)}${location}`);
  62 |   });
  63 |   page.on('pageerror', (error) => diagnostics.record('pageerror', error.message.slice(0, 500)));
  64 |   page.on('requestfailed', (request) => {
  65 |     let location = request.url();
  66 |     try {
  67 |       const url = new URL(request.url());
  68 |       location = `${url.origin}${url.pathname}`;
  69 |     } catch {
  70 |       // Keep a non-URL request string only when the browser cannot parse it.
  71 |     }
  72 |     diagnostics.record('requestfailed', `${request.method()} ${location}: ${request.failure()?.errorText ?? 'request failed'}`);
  73 |   });
  74 |   page.on('response', (response) => {
  75 |     if (response.status() < 500) return;
  76 |     const url = new URL(response.url());
  77 |     diagnostics.record('http5xx', `${response.status()} ${url.origin}${url.pathname}`);
  78 |   });
  79 |   return diagnostics;
  80 | }
  81 | 
```