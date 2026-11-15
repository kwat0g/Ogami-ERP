# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: production-stock.floor.spec.ts >> PROD01-HAPPY — stock work order, Warehouse handover, startup QC, production, and outgoing release
- Location: e2e-process/production-stock.floor.spec.ts:284:1

# Error details

```
Error: apiRequestContext.post: getaddrinfo EAI_AGAIN nginx
Call log:
  - → POST https://nginx/api/v1/_e2e/clock
    - user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.8010.12 Safari/537.36
    - accept: */*
    - accept-encoding: gzip,deflate,br
    - X-E2E-Control: abfd4b2149647d9a84e1f3d957ab505be22f96ddc581eb2ef0b4c5f49b33f37e
    - content-type: application/json
    - content-length: 38

```

# Test source

```ts
  1  | import { expect, type APIRequestContext } from '@playwright/test';
  2  | 
  3  | const endpoint = '/api/v1/_e2e/clock';
  4  | 
  5  | function controlHeaders(): Record<string, string> {
  6  |   const token = process.env.E2E_CONTROL_TOKEN;
  7  |   if (!token || token.length < 32) throw new Error('E2E clock control token is missing.');
  8  |   return { 'X-E2E-Control': token };
  9  | }
  10 | 
  11 | export async function freezeClock(request: APIRequestContext, instant: string): Promise<void> {
> 12 |   const response = await request.post(endpoint, { headers: controlHeaders(), data: { instant } });
     |                                  ^ Error: apiRequestContext.post: getaddrinfo EAI_AGAIN nginx
  13 |   expect(response.status(), 'E2E clock freeze should be accepted by the gated control endpoint').toBe(200);
  14 | }
  15 | 
  16 | export async function clearClock(request: APIRequestContext): Promise<void> {
  17 |   const response = await request.delete(endpoint, { headers: controlHeaders() });
  18 |   expect(response.status(), 'E2E clock reset should be accepted by the gated control endpoint').toBe(200);
  19 | }
  20 | 
  21 | export async function readClock(request: APIRequestContext): Promise<string> {
  22 |   const response = await request.get(endpoint, { headers: controlHeaders() });
  23 |   expect(response.status(), 'E2E clock read should be accepted by the gated control endpoint').toBe(200);
  24 |   const result = await response.json() as { now: string };
  25 |   return result.now;
  26 | }
  27 | 
```