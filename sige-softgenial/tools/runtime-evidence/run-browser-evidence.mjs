#!/usr/bin/env node
/* SIGE Runtime Evidence Suite v12.16.1
 * Requires Playwright in the QA environment: npm i -D playwright && npx playwright install chromium
 * No passwords are accepted in this file or config. Use storage state files through environment variables.
 */
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const configPath = process.argv[2] || 'tools/runtime-evidence/config.example.json';
const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
const baseUrl = process.env[config.baseUrlEnv || 'SIGE_EVIDENCE_BASE_URL'];
if (!baseUrl) {
  console.error('Missing base URL env: ' + (config.baseUrlEnv || 'SIGE_EVIDENCE_BASE_URL'));
  process.exit(2);
}
const productionAllowed = process.env[config.allowProductionEnv || 'SIGE_EVIDENCE_ALLOW_PRODUCTION'] === '1';
if (!productionAllowed && !/(staging|stage|teste|test|dev|local|localhost|127\.0\.0\.1)/i.test(baseUrl)) {
  console.error('Refusing to run against a host that does not look like staging/test/dev. Set SIGE_EVIDENCE_ALLOW_PRODUCTION=1 only with explicit release approval.');
  process.exit(2);
}

let chromium;
try {
  ({ chromium } = await import('playwright'));
} catch (err) {
  console.error('Playwright is not installed in this QA environment. Install with: npm i -D playwright && npx playwright install chromium');
  process.exit(2);
}

const outDir = path.resolve(config.outputDir || 'artifacts/runtime-evidence');
fs.mkdirSync(outDir, { recursive: true });
const summary = {
  schema: config.schema || 'sige-runtime-evidence',
  baseUrl,
  startedAt: new Date().toISOString(),
  results: [],
  failures: []
};

function viewUrl(view) {
  const url = new URL(baseUrl);
  url.searchParams.set('page', 'sige-app');
  url.searchParams.set('view', view);
  return url.toString();
}

function safeName(text) {
  return String(text).replace(/[^a-z0-9_-]+/gi, '_').replace(/^_+|_+$/g, '').toLowerCase();
}

for (const role of config.roles || []) {
  const storagePath = process.env[role.storageStateEnv || ''];
  if (!storagePath || !fs.existsSync(storagePath)) {
    summary.failures.push({ role: role.role, error: 'missing_storage_state', env: role.storageStateEnv });
    continue;
  }

  for (const vp of config.viewports || []) {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
      viewport: { width: vp.width, height: vp.height },
      storageState: storagePath,
      ignoreHTTPSErrors: true
    });
    const page = await context.newPage();
    page.setDefaultTimeout(config.timeoutMs || 15000);

    for (const view of role.views || []) {
      const record = { role: role.role, viewport: vp.name, view, ok: false };
      try {
        const response = await page.goto(viewUrl(view), { waitUntil: 'networkidle' });
        record.status = response ? response.status() : null;
        const bodyText = await page.locator('body').innerText({ timeout: 5000 }).catch(() => '');
        const currentUrl = page.url();
        const markers = [...(config.fatalMarkers || []), ...(role.mustNotContain || [])];
        const foundMarker = markers.find((needle) => currentUrl.includes(needle) || bodyText.includes(needle));
        if (!response || !response.ok()) throw new Error('HTTP status not ok: ' + record.status);
        if (foundMarker) throw new Error('Forbidden marker found: ' + foundMarker);
        if ((bodyText || '').trim().length < 20) throw new Error('Possible blank page');
        const overflow = await page.evaluate(() => {
          const topbar = document.querySelector('.sg-app-topbar');
          if (!topbar) return null;
          return { scrollWidth: topbar.scrollWidth, clientWidth: topbar.clientWidth };
        });
        if (overflow && overflow.scrollWidth > overflow.clientWidth + 4) {
          throw new Error('Topbar horizontal overflow: ' + JSON.stringify(overflow));
        }
        const shot = path.join(outDir, `${safeName(role.role)}-${safeName(vp.name)}-${safeName(view)}.png`);
        await page.screenshot({ path: shot, fullPage: true });
        record.screenshot = shot;
        record.ok = true;
      } catch (err) {
        record.error = err && err.message ? err.message : String(err);
        summary.failures.push(record);
      }
      summary.results.push(record);
    }
    await context.close();
    await browser.close();
  }
}
summary.finishedAt = new Date().toISOString();
summary.ok = summary.failures.length === 0;
fs.writeFileSync(path.join(outDir, 'summary.json'), JSON.stringify(summary, null, 2));
if (!summary.ok) {
  console.error('Runtime evidence failed: ' + summary.failures.length + ' failure(s). See ' + path.join(outDir, 'summary.json'));
  process.exit(1);
}
console.log('Runtime evidence OK. See ' + path.join(outDir, 'summary.json'));
