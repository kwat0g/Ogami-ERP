# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: hr-attendance.process.spec.ts >> HR03-OVERTIME-APPROVE: employee request reaches its department head and updates the open employee view
- Location: e2e-process/hr-attendance.process.spec.ts:1025:1

# Error details

```
Error: Read-only API GET /api/v1/dashboards/badges returned 401.
```

# Test source

```ts
  1  | import type { APIRequestContext, BrowserContext } from '@playwright/test';
  2  | 
  3  | export async function apiReader(context: BrowserContext): Promise<APIRequestContext> {
  4  |   return context.request;
  5  | }
  6  | 
  7  | export async function readApiJson<T>(context: BrowserContext, apiPath: string): Promise<T> {
  8  |   if (!apiPath.startsWith('/api/v1/') || apiPath.startsWith('//')) {
  9  |     throw new Error('Read-only API checks must use a same-origin /api/v1/ path.');
  10 |   }
  11 |   const response = await context.request.get(apiPath, { failOnStatusCode: false });
  12 |   if (!response.ok()) {
> 13 |     throw new Error(`Read-only API GET ${apiPath} returned ${response.status()}.`);
     |           ^ Error: Read-only API GET /api/v1/dashboards/badges returned 401.
  14 |   }
  15 |   return response.json() as Promise<T>;
  16 | }
  17 | 
  18 | export async function attemptDeniedMutation(
  19 |   context: BrowserContext,
  20 |   method: 'POST' | 'PUT' | 'PATCH' | 'DELETE',
  21 |   apiPath: string,
  22 |   data?: unknown,
  23 |   expectedStatuses: number[] = [403, 422],
  24 | ): Promise<number> {
  25 |   if (!apiPath.startsWith('/api/v1/') || apiPath.startsWith('//')) {
  26 |     throw new Error('Negative authorization checks must use a same-origin /api/v1/ path.');
  27 |   }
  28 |   const response = await context.request.fetch(apiPath, {
  29 |     method,
  30 |     data,
  31 |     failOnStatusCode: false,
  32 |   });
  33 |   if (!expectedStatuses.includes(response.status())) {
  34 |     throw new Error(`Negative ${method} ${apiPath} check returned unexpected status ${response.status()}.`);
  35 |   }
  36 |   return response.status();
  37 | }
  38 | 
```