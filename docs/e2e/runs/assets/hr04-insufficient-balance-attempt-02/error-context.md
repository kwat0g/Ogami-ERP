# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-leave.process.spec.ts >> HR04-INSUFFICIENT-BALANCE — Employee cannot submit more leave than the annual balance
- Location: e2e-process/hr-leave.process.spec.ts:1391:1

# Error details

```
Error: Unexpected browser/network errors during Employee receives a controlled insufficient-balance refusal:
- console: Failed to load resource: the server responded with a status of 422 (Unprocessable Content) at /api/v1/leaves/requests
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
  11 | 
  12 |   constructor(private readonly allowlist: NoiseAllowlist) {}
  13 | 
  14 |   record(kind: IssueKind, evidence: string): void {
  15 |     const cleanEvidence = evidence.replace(/[?&](?:token|password|secret|authorization)=[^&\s]*/gi, '[redacted]');
  16 |     if (this.allowlist[kind].some((known) => cleanEvidence.includes(known))) return;
  17 |     this.issues.push({ kind, evidence: cleanEvidence });
  18 |   }
  19 | 
  20 |   assertClean(stepName = 'current process step'): void {
  21 |     if (this.issues.length === 0) return;
  22 |     const details = this.issues.map(({ kind, evidence }) => `- ${kind}: ${evidence}`).join('\n');
> 23 |     throw new Error(`Unexpected browser/network errors during ${stepName}:\n${details}`);
     |           ^ Error: Unexpected browser/network errors during Employee receives a controlled insufficient-balance refusal:
  24 |   }
  25 | }
  26 | 
  27 | async function loadAllowlist(): Promise<NoiseAllowlist> {
  28 |   const file = path.resolve('e2e-process/known-network-noise.json');
  29 |   const parsed = JSON.parse(await readFile(file, 'utf8')) as Partial<NoiseAllowlist>;
  30 |   return {
  31 |     console: parsed.console ?? [],
  32 |     pageerror: parsed.pageerror ?? [],
  33 |     requestfailed: parsed.requestfailed ?? [],
  34 |     http5xx: parsed.http5xx ?? [],
  35 |   };
  36 | }
  37 | 
  38 | export async function monitorPage(page: Page): Promise<PageDiagnostics> {
  39 |   const diagnostics = new PageDiagnostics(await loadAllowlist());
  40 |   page.on('console', (message) => {
  41 |     if (message.type() !== 'error') return;
  42 |     let location = '';
  43 |     try {
  44 |       location = ` at ${new URL(message.location().url).pathname}`;
  45 |     } catch {
  46 |       // Some browser-generated messages have no source URL.
  47 |     }
  48 |     diagnostics.record('console', `${message.text().slice(0, 500)}${location}`);
  49 |   });
  50 |   page.on('pageerror', (error) => diagnostics.record('pageerror', error.message.slice(0, 500)));
  51 |   page.on('requestfailed', (request) => {
  52 |     let location = request.url();
  53 |     try {
  54 |       const url = new URL(request.url());
  55 |       location = `${url.origin}${url.pathname}`;
  56 |     } catch {
  57 |       // Keep a non-URL request string only when the browser cannot parse it.
  58 |     }
  59 |     diagnostics.record('requestfailed', `${request.method()} ${location}: ${request.failure()?.errorText ?? 'request failed'}`);
  60 |   });
  61 |   page.on('response', (response) => {
  62 |     if (response.status() < 500) return;
  63 |     const url = new URL(response.url());
  64 |     diagnostics.record('http5xx', `${response.status()} ${url.origin}${url.pathname}`);
  65 |   });
  66 |   return diagnostics;
  67 | }
  68 | 
```