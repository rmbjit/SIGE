#!/usr/bin/env node
/**
 * SIGE SoftGenial - Arnes de Verificacao Visual
 * ------------------------------------------------------------------
 * Transforma a remocao de !important (e qualquer mudanca de CSS) de
 * "aposta" em "verificada": fotografa cada modulo do SIGE num browser
 * real (autenticado no wp-admin), aplica a mudanca, fotografa de novo
 * e compara pixel a pixel. So se a diferenca for nula (ou abaixo do
 * limiar) a mudanca fica; caso contrario, e revertida.
 *
 * Corre no SEU ambiente (WordPress + browser), idealmente num site de
 * STAGING (ex.: demo.softgenial.edu.mz), nunca em producao, porque o
 * comando verify-strip altera os ficheiros do plugin no servidor.
 *
 * Comandos:
 *   node arnes.js capture <etiqueta>           fotografa todos os modulos
 *   node arnes.js capture <etiqueta> <modulo>  fotografa um modulo
 *   node arnes.js diff <a> <b>                 compara duas capturas
 *   node arnes.js verify-strip <modulo> [--scope=SEL]
 *                                              antes -> remove !important ->
 *                                              depois -> compara -> fica/reverte
 *   node arnes.js list                         lista modulos configurados
 *
 * Config: arnes.config.json (copie de config.example.json).
 * Credenciais: por variaveis de ambiente SIGE_ADMIN_USER / SIGE_ADMIN_PASS
 * (nunca gravar no ficheiro de config).
 */

'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

let chromium, PNG, pixelmatch;
function loadDeps() {
  if (chromium) return;
  try {
    ({ chromium } = require('playwright'));
    PNG = require('pngjs').PNG;
    pixelmatch = require('pixelmatch');
  } catch (e) {
    console.error('Dependencias em falta. Corra primeiro: npm install && npx playwright install chromium');
    console.error('(' + e.message + ')');
    process.exit(2);
  }
}

const ROOT = __dirname;
const CFG_PATH = path.join(ROOT, 'arnes.config.json');
const SCREENS = path.join(ROOT, 'screens');

function loadConfig() {
  if (!fs.existsSync(CFG_PATH)) {
    console.error('Falta arnes.config.json. Copie config.example.json e edite.');
    process.exit(2);
  }
  const cfg = JSON.parse(fs.readFileSync(CFG_PATH, 'utf8'));
  cfg.adminUser = process.env.SIGE_ADMIN_USER || cfg.adminUser || '';
  cfg.adminPass = process.env.SIGE_ADMIN_PASS || cfg.adminPass || '';
  if (!cfg.adminUser || !cfg.adminPass || /ENV:/.test(cfg.adminUser)) {
    console.error('Defina credenciais via SIGE_ADMIN_USER e SIGE_ADMIN_PASS.');
    process.exit(2);
  }
  cfg.viewports = cfg.viewports && cfg.viewports.length ? cfg.viewports
    : [{ name: 'desktop', width: 1440, height: 900 }];
  cfg.thresholdRatio = cfg.thresholdRatio != null ? cfg.thresholdRatio : 0.001;
  cfg.settleMs = cfg.settleMs != null ? cfg.settleMs : 1200;
  return cfg;
}

function ensureDir(d) { fs.mkdirSync(d, { recursive: true }); }
function moduleByName(cfg, name) { return cfg.modules.find(m => m.name === name); }

// Congela animacoes/transicoes e mascara regioes volateis, para diffs estaveis.
async function stabilize(page, cfg) {
  await page.addStyleTag({
    content: `*,*::before,*::after{animation:none!important;transition:none!important;
      caret-color:transparent!important;scroll-behavior:auto!important}`
  });
  await page.waitForTimeout(cfg.settleMs);
  // forca render de conteudo lazy: desce e sobe
  await page.evaluate(async () => {
    await new Promise(r => { window.scrollTo(0, document.body.scrollHeight); setTimeout(r, 250); });
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(300);
}

async function login(context, cfg) {
  const page = await context.newPage();
  await page.goto(cfg.baseUrl + (cfg.loginPath || '/wp-login.php'), { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', cfg.adminUser);
  await page.fill('#user_pass', cfg.adminPass);
  await Promise.all([
    page.waitForLoadState('networkidle').catch(() => {}),
    page.click('#wp-submit'),
  ]);
  await page.waitForTimeout(800);
  await page.close();
}

async function captureOne(context, cfg, label, mod, vp) {
  const page = await context.newPage();
  await page.setViewportSize({ width: vp.width, height: vp.height });
  // URL: explicita (mod.url, contexto sem tokens como login/portal) ou admin-app.
  const url = mod.url
    ? (/^https?:/i.test(mod.url) ? mod.url : cfg.baseUrl + mod.url)
    : cfg.baseUrl + cfg.appPage + '&view=' + encodeURIComponent(mod.view);
  await page.goto(url, { waitUntil: 'networkidle' }).catch(() => {});
  const sel = mod.waitSelector || cfg.waitSelector;
  if (sel) {
    await page.waitForSelector(sel, { timeout: 8000 }).catch(() => {});
  }
  await stabilize(page, cfg);
  const dir = path.join(SCREENS, label);
  ensureDir(dir);
  const file = path.join(dir, `${mod.name}__${vp.name}.png`);
  const mask = (cfg.maskSelectors || []).map(s => page.locator(s));
  await page.screenshot({ path: file, fullPage: true, mask, animations: 'disabled' });
  await page.close();
  return file;
}

async function cmdCapture(cfg, label, onlyModule) {
  loadDeps();
  if (!label) { console.error('Falta a etiqueta. Ex.: node arnes.js capture antes'); process.exit(2); }
  const mods = onlyModule ? [moduleByName(cfg, onlyModule)].filter(Boolean) : cfg.modules;
  if (!mods.length) { console.error('Modulo nao encontrado: ' + onlyModule); process.exit(2); }
  const browser = await chromium.launch();
  // Dois contextos: admin (autenticado) e anonimo (limpo, para login/portal).
  const adminCtx = await browser.newContext({ ignoreHTTPSErrors: true, deviceScaleFactor: 1 });
  let anonCtx = null;
  const needAdmin = mods.some(m => (m.auth || 'admin') === 'admin');
  const needAnon = mods.some(m => (m.auth || 'admin') !== 'admin');
  try {
    if (needAdmin) await login(adminCtx, cfg);
    if (needAnon) anonCtx = await browser.newContext({ ignoreHTTPSErrors: true, deviceScaleFactor: 1 });
    let n = 0;
    for (const mod of mods) {
      const ctx = (mod.auth || 'admin') === 'admin' ? adminCtx : anonCtx;
      for (const vp of cfg.viewports) {
        process.stdout.write(`  capturar ${label}/${mod.name} [${vp.name}]${mod.auth && mod.auth !== 'admin' ? ' (anon)' : ''} ... `);
        try { await captureOne(ctx, cfg, label, mod, vp); console.log('ok'); n++; }
        catch (e) { console.log('FALHOU (' + e.message + ')'); }
      }
    }
    console.log(`Capturas: ${n} imagem(ns) em screens/${label}/`);
  } finally {
    await adminCtx.close();
    if (anonCtx) await anonCtx.close();
    await browser.close();
  }
}

function compareImages(fa, fb, diffPath) {
  const a = PNG.sync.read(fs.readFileSync(fa));
  const b = PNG.sync.read(fs.readFileSync(fb));
  const width = Math.max(a.width, b.width), height = Math.max(a.height, b.height);
  const pad = (img) => {
    if (img.width === width && img.height === height) return img;
    const out = new PNG({ width, height });
    PNG.bitblt(img, out, 0, 0, img.width, img.height, 0, 0);
    return out;
  };
  const A = pad(a), B = pad(b), diff = new PNG({ width, height });
  const mismatch = pixelmatch(A.data, B.data, diff.data, width, height, { threshold: 0.1 });
  if (diffPath) { ensureDir(path.dirname(diffPath)); fs.writeFileSync(diffPath, PNG.sync.write(diff)); }
  return { mismatch, total: width * height, ratio: mismatch / (width * height),
    sizeChanged: a.width !== b.width || a.height !== b.height };
}

function cmdDiff(cfg, a, b) {
  loadDeps();
  const dirA = path.join(SCREENS, a), dirB = path.join(SCREENS, b);
  if (!fs.existsSync(dirA) || !fs.existsSync(dirB)) {
    console.error(`Faltam capturas. Esperado screens/${a} e screens/${b}.`); process.exit(2);
  }
  const outDir = path.join(SCREENS, `diff__${a}__${b}`);
  const files = fs.readdirSync(dirA).filter(f => f.endsWith('.png'));
  let worst = 0, fails = 0;
  console.log(`\n  ${'MODULO [viewport]'.padEnd(40)} ${'PIXELS'.padStart(10)}  ${'RACIO'.padStart(9)}  ESTADO`);
  console.log('  ' + '-'.repeat(74));
  for (const f of files.sort()) {
    const fa = path.join(dirA, f), fb = path.join(dirB, f);
    if (!fs.existsSync(fb)) { console.log(`  ${f.replace('.png','').padEnd(40)} ${'-'.padStart(10)}  ${'-'.padStart(9)}  SEM PAR`); continue; }
    const r = compareImages(fa, fb, path.join(outDir, f));
    worst = Math.max(worst, r.ratio);
    const ok = r.ratio <= cfg.thresholdRatio && !r.sizeChanged;
    if (!ok) fails++;
    const estado = ok ? 'OK' : (r.sizeChanged ? 'TAMANHO MUDOU' : 'DIFERENCA');
    console.log(`  ${f.replace('.png','').padEnd(40)} ${String(r.mismatch).padStart(10)}  ${r.ratio.toFixed(6).padStart(9)}  ${estado}`);
  }
  console.log('  ' + '-'.repeat(74));
  console.log(`  Pior racio: ${worst.toFixed(6)} | limiar: ${cfg.thresholdRatio} | imagens com diferenca: ${fails}`);
  if (fails > 0) console.log(`  Imagens de diferenca em: screens/diff__${a}__${b}/`);
  return fails === 0;
}

async function cmdVerifyStrip(cfg, modName, scope) {
  const mod = moduleByName(cfg, modName);
  if (!mod) { console.error('Modulo nao encontrado: ' + modName); process.exit(2); }
  if (!mod.file) { console.error(`O modulo ${modName} nao tem "file" na config.`); process.exit(2); }
  const pluginRoot = cfg.pluginRoot || path.resolve(ROOT, '..', '..');
  const absFile = path.join(pluginRoot, mod.file);
  if (!fs.existsSync(absFile)) { console.error('Ficheiro do modulo inexistente: ' + absFile); process.exit(2); }
  const stripper = path.join(ROOT, 'strip-important.php');

  const antes = `verify-antes-${modName}`, depois = `verify-depois-${modName}`;
  console.log(`\n[1/4] Captura ANTES (${modName})`);
  await cmdCapture(cfg, antes, modName);

  console.log(`\n[2/4] Remover !important de ${mod.file}${scope ? ` (ambito ${scope})` : ''}`);
  const args = [stripper, absFile, '--apply'];
  if (scope) args.push('--scope=' + scope);
  console.log('  ' + execFileSync('php', args, { encoding: 'utf8' }).trim());
  if (cfg.afterStripCmd) {
    console.log('  pos-strip: ' + cfg.afterStripCmd);
    try { execFileSync('sh', ['-c', cfg.afterStripCmd], { encoding: 'utf8' }); } catch (e) { console.log('  (aviso afterStripCmd: ' + e.message + ')'); }
  }

  console.log(`\n[3/4] Captura DEPOIS (${modName})`);
  await cmdCapture(cfg, depois, modName);

  console.log(`\n[4/4] Comparacao`);
  const ok = cmdDiff(cfg, antes, depois);

  if (ok) {
    console.log(`\n  VERDE: render identico. A remocao de !important em ${modName} e SEGURA e fica aplicada.`);
    console.log(`  (Backup do ficheiro guardado em ${mod.file}.important-bak; apague quando confortavel.)`);
    process.exit(0);
  } else {
    console.log(`\n  VERMELHO: houve diferenca de pixels. A reverter ${mod.file} ...`);
    console.log('  ' + execFileSync('php', [stripper, absFile, '--restore'], { encoding: 'utf8' }).trim());
    console.log('  Veja as imagens de diferenca para perceber que regras precisam de manter !important.');
    process.exit(1);
  }
}

(async function main() {
  const [cmd, ...rest] = process.argv.slice(2);
  const scopeArg = rest.find(a => a.startsWith('--scope='));
  const scope = scopeArg ? scopeArg.slice(8) : null;
  const pos = rest.filter(a => !a.startsWith('--'));
  const cfg = (cmd === 'list' || cmd) ? loadConfig() : null;

  switch (cmd) {
    case 'capture': await cmdCapture(cfg, pos[0], pos[1]); break;
    case 'diff': { const ok = cmdDiff(cfg, pos[0], pos[1]); process.exit(ok ? 0 : 1); }
    case 'verify-strip': await cmdVerifyStrip(cfg, pos[0], scope); break;
    case 'list':
      console.log('Modulos configurados:');
      cfg.modules.forEach(m => {
        const alvo = m.url ? `url=${m.url}` : `view=${m.view}`;
        const auth = (m.auth || 'admin');
        console.log(`  ${m.name.padEnd(22)} ${alvo.padEnd(34)} auth=${auth.padEnd(6)} ${m.file || ''}`);
      });
      console.log('Viewports: ' + cfg.viewports.map(v => `${v.name}(${v.width}x${v.height})`).join(', '));
      break;
    default:
      console.log('Comandos: capture <etiqueta> [modulo] | diff <a> <b> | verify-strip <modulo> [--scope=SEL] | list');
  }
})().catch(e => { console.error(e); process.exit(2); });
