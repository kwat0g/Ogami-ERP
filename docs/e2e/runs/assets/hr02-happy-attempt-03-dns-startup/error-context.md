# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: auth.setup.ts >> sign in selected seeded actors through the real UI and save each role session
- Location: e2e-process/auth.setup.ts:15:1

# Error details

```
Error: page.goto: net::ERR_NAME_NOT_RESOLVED at https://nginx/sign-in
Call log:
  - navigating to "https://nginx/sign-in", waiting until "load"

```

# Test source

```ts
  1  | import { mkdir, readFile } from 'node:fs/promises';
  2  | import path from 'node:path';
  3  | import { test, expect } from './fixtures';
  4  | import { monitorPage } from './helpers/page-diagnostics';
  5  | 
  6  | type Actor = {
  7  |   actor: string;
  8  |   realm: 'internal' | 'customer' | 'supplier';
  9  |   email: string;
  10 |   role?: string;
  11 | };
  12 | 
  13 | type ActorManifest = { accounts: Actor[] };
  14 | 
  15 | test('sign in selected seeded actors through the real UI and save each role session', async ({ browser, baseURL }, testInfo) => {
  16 |   test.setTimeout(5 * 60 * 1000);
  17 |   const manifestPath = process.env.E2E_ACCOUNT_MANIFEST;
  18 |   const password = process.env.E2E_ACCOUNT_PASSWORD;
  19 |   if (!manifestPath || !password) throw new Error('E2E actor manifest or password is missing from the guarded container.');
  20 | 
  21 |   const manifest = JSON.parse(await readFile(manifestPath, 'utf8')) as ActorManifest;
  22 |   if (!Array.isArray(manifest.accounts) || manifest.accounts.length === 0) {
  23 |     throw new Error('E2E actor manifest is empty.');
  24 |   }
  25 | 
  26 |   const requestedActors = process.env.E2E_AUTH_ACTORS?.split(',').map((actor) => actor.trim()).filter(Boolean) ?? [];
  27 |   const requestedSet = requestedActors.length > 0 ? new Set(requestedActors) : null;
  28 |   if (requestedSet && requestedSet.size !== requestedActors.length) {
  29 |     throw new Error('E2E_AUTH_ACTORS must contain unique actor names.');
  30 |   }
  31 |   const actors = requestedSet
  32 |     ? manifest.accounts.filter((actor) => requestedSet.has(actor.actor))
  33 |     : manifest.accounts;
  34 |   const unmatchedActors = requestedActors.filter((actor) => !manifest.accounts.some((account) => account.actor === actor));
  35 |   if (unmatchedActors.length > 0 || actors.length === 0) {
  36 |     throw new Error('E2E_AUTH_ACTORS did not match seeded actors.');
  37 |   }
  38 | 
  39 |   const stateDirectory = path.resolve('.auth');
  40 |   await mkdir(stateDirectory, { recursive: true, mode: 0o700 });
  41 | 
  42 |   for (const actor of actors) {
  43 |     await test.step(`UI sign-in as ${actor.actor}${actor.role ? ` (${actor.role})` : ''}`, async () => {
  44 |       const context = await browser.newContext({ baseURL });
  45 |       const page = await context.newPage();
  46 |       const diagnostics = await monitorPage(page);
  47 |       const actorFile = actor.actor.replace(/[^a-zA-Z0-9_-]/g, '_');
  48 |       try {
> 49 |         await page.goto('/sign-in');
     |                    ^ Error: page.goto: net::ERR_NAME_NOT_RESOLVED at https://nginx/sign-in
  50 |         await page.getByLabel('Email').fill(actor.email);
  51 |         await page.getByLabel('Password', { exact: true }).fill(password);
  52 |         const signInResponse = page.waitForResponse(
  53 |           (response) => response.url().includes('/api/v1/auth/sign-in'),
  54 |           { timeout: 20_000 },
  55 |         );
  56 |         await page.getByRole('button', { name: 'Sign in' }).click();
  57 |         const response = await signInResponse;
  58 |         expect(response.status(), `sign-in response for ${actor.actor}`).toBe(200);
  59 | 
  60 |         if (actor.realm === 'internal') {
  61 |           await expect(page).toHaveURL(/\/dashboard(?:\/|$)/, { timeout: 20_000 });
  62 |         } else {
  63 |           await expect(page).toHaveURL(new RegExp(`/portal/${actor.realm}(?:/|$)`), { timeout: 20_000 });
  64 |         }
  65 |         await expect(page).not.toHaveURL(/change-password/);
  66 |         diagnostics.assertClean(`sign-in as ${actor.actor}`);
  67 | 
  68 |         await context.storageState({ path: path.join(stateDirectory, `${actorFile}.json`) });
  69 |       } catch (error) {
  70 |         await page.getByLabel('Password', { exact: true }).fill('').catch(() => undefined);
  71 |         await page.screenshot({ path: testInfo.outputPath(`${actorFile}.png`), fullPage: true }).catch(() => undefined);
  72 |         throw error;
  73 |       } finally {
  74 |         await context.close();
  75 |       }
  76 |     });
  77 |   }
  78 | });
  79 | 
```