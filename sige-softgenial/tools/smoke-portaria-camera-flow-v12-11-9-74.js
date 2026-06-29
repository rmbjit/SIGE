#!/usr/bin/env node
/**
 * Smoke JS - SIGE v12.11.9.74 Portaria Camera Flow
 * Valida: QR directo no clique, fallback getUserMedia, Permissions-Policy e falso alerta removido.
 */
const fs = require('fs');
const vm = require('vm');
const path = require('path');

const root = path.resolve(__dirname, '..');
const build = JSON.parse(fs.readFileSync(path.join(root, 'BUILD.json'), 'utf8'));
if ((build.version || '') !== '12.11.9.74') {
  console.log('SKIP Smoke JS smoke-portaria-camera-flow-v12-11-9-74.js - superseded by current build ' + (build.version || 'unknown'));
  process.exit(0);
}
let php = fs.readFileSync(path.join(root, 'admin/system/portaria-view.php'), 'utf8');
let match = php.match(/<script>([\s\S]*?)<\/script>/);
if (!match) throw new Error('Script inline da Portaria não encontrado');
let js = match[1]
  .replace(/<\?php echo wp_json_encode\(wp_create_nonce\("sige_portaria_acesso"\)\); \?>/g, '"nonce"')
  .replace(/<\?php echo wp_json_encode\(admin_url\('admin-ajax.php'\)\); \?>/g, '"/wp-admin/admin-ajax.php"')
  .replace(/<\?php echo wp_json_encode\(SIGE_URL \. 'assets\/img\/avatar-default\.svg'\); \?>/g, '"/assets/img/avatar-default.svg"')
  .replace(/<\?php[\s\S]*?\?>/g, 'null');

function makeElement(id) {
  return {
    id,
    hidden: false,
    disabled: false,
    textContent: '',
    innerHTML: '',
    value: '',
    src: '',
    style: {},
    className: '',
    children: [],
    setAttribute(name, value) { this[name] = String(value); },
    getAttribute(name) { return this[name]; },
    appendChild(child) { this.children.push(child); return child; },
    addEventListener(type, fn) { this['on' + type] = fn; },
    focus() { this.focused = true; },
    classList: {
      values: new Set(),
      add(...v) { v.forEach(x => this.values.add(x)); },
      remove(...v) { v.forEach(x => this.values.delete(x)); },
      contains(v) { return this.values.has(v); }
    }
  };
}

function createContext(opts = {}) {
  const elements = new Map();
  const ids = [
    'sg-camera-frame','sg-camera-status-title','sg-camera-status-text','sg-camera-permission-title','sg-camera-permission-text',
    'sg-camera-start','sg-camera-retry','sg-portaria-manual-btn','manual-proc','result-panel','sg-camera-diagnostic','sg-camera-select',
    'sg-camera-stop','r-status','r-nome','r-turma','r-id-help','r-action-help','r-chip','r-time','r-obs','r-foto','sg-portaria-history-list','sg-portaria-manual-box','reader'
  ];
  ids.forEach(id => elements.set(id, makeElement(id)));
  let qrStartCalls = [];
  let getUserMediaCalls = 0;
  let startPlan = Array.isArray(opts.startPlan) ? opts.startPlan.slice() : ['resolve'];
  class Html5QrcodeMock {
    constructor(id) { this.id = id; }
    async start(config) {
      qrStartCalls.push(config);
      const action = startPlan.length ? startPlan.shift() : 'resolve';
      if (action === 'rejectNotAllowed') { const e = new Error('Denied'); e.name = 'NotAllowedError'; throw e; }
      if (action === 'rejectNotReadable') { const e = new Error('Busy'); e.name = 'NotReadableError'; throw e; }
      return true;
    }
    async stop() { return true; }
    clear() { return true; }
    static async getCameras() { return opts.cameras || [{ id: 'cam-back', label: 'Back Camera' }, { id: 'cam-front', label: 'Front Camera' }]; }
  }
  const context = {
    console,
    setTimeout: (fn) => { fn(); return 1; },
    clearTimeout: () => {},
    window: { isSecureContext: true, location: { protocol: 'https:', hostname: 'teste.softgenial.edu.mz' }, addEventListener: () => {} },
    document: {
      readyState: 'loading',
      permissionsPolicy: { allowsFeature: () => opts.policyAllowed !== false },
      featurePolicy: null,
      getElementById: (id) => elements.get(id) || elements.set(id, makeElement(id)).get(id),
      createElement: (tag) => makeElement(tag),
      addEventListener: () => {}
    },
    navigator: {
      permissions: { query: async () => ({ state: opts.permissionState || 'granted' }) },
      mediaDevices: {
        enumerateDevices: async () => (opts.devices || [{ kind: 'videoinput', deviceId: 'cam-back', label: 'Back Camera' }]),
        getUserMedia: async () => {
          getUserMediaCalls++;
          if (opts.nativeReject) { const e = new Error('Native denied'); e.name = opts.nativeReject; throw e; }
          return { getTracks: () => [{ stop() {} }] };
        }
      }
    },
    Html5Qrcode: Html5QrcodeMock,
    jQuery: { post: () => ({ fail: () => ({ always: () => {} }) }) },
    ajaxurl: '/wp-admin/admin-ajax.php',
    __state: { elements, qrStartCalls, getUserMediaCalls: () => getUserMediaCalls }
  };
  context.window.window = context.window;
  vm.createContext(context);
  vm.runInContext(js, context, { filename: 'portaria-inline-v74.js' });
  return context;
}

async function run() {
  let ok = 0; let fail = 0;
  function check(cond, msg) { if (cond) { ok++; console.log('[OK]', msg); } else { fail++; console.error('[FAIL]', msg); } }

  let c1 = createContext({ startPlan: ['resolve'], permissionState: 'granted', policyAllowed: true });
  await c1.sgPortariaStartCamera();
  check(c1.__state.qrStartCalls.length >= 1, 'Fluxo principal chama Html5Qrcode.start');
  check(c1.__state.getUserMediaCalls() === 0, 'Fluxo principal não faz probe nativo antes do QR quando QR inicia');
  check(c1.sgPortariaStarted === true, 'Estado started fica activo após QR directo');
  check(c1.__state.elements.get('sg-camera-status-title').textContent === 'Câmara activa', 'UI mostra Câmara activa após sucesso');

  let c2 = createContext({ startPlan: ['rejectNotAllowed', 'resolve'], permissionState: 'granted', policyAllowed: true });
  await c2.sgPortariaStartCamera();
  check(c2.__state.qrStartCalls.length >= 2, 'Fallback tenta QR novamente depois do teste nativo');
  check(c2.__state.getUserMediaCalls() === 1, 'Fallback executa getUserMedia nativo uma vez');
  check(c2.sgPortariaStarted === true, 'Fallback deixa scanner activo após segunda tentativa');

  let c3 = createContext({ startPlan: ['resolve'], permissionState: 'granted', policyAllowed: false });
  await c3.sgPortariaStartCamera();
  check(c3.__state.qrStartCalls.length === 0, 'Permissions-Policy bloqueada impede QR antes de pedir câmara');
  check(/Política/.test(c3.__state.elements.get('sg-camera-status-title').textContent), 'UI identifica política da aplicação, não falso bloqueio do utilizador');
  check(/camera=\(\)/.test(c3.__state.elements.get('sg-camera-status-text').textContent), 'Mensagem orienta verificar header camera=()');

  let c4 = createContext({ startPlan: ['resolve'], permissionState: 'denied', policyAllowed: true });
  c4.sgPortariaInit();
  check(c4.__state.elements.get('sg-camera-status-title').textContent !== 'Câmara bloqueada para este site', 'Init denied não dispara falso alerta antigo');
  check(/vamos tentar novo pedido|pronta para autorização|permitir uso da câmara/i.test(c4.__state.elements.get('sg-camera-status-title').textContent + ' ' + c4.__state.elements.get('sg-camera-status-text').textContent), 'Init denied mantém tentativa pelo botão');

  if (fail) { console.error(`\nFAILURES: ${fail}`); process.exit(1); }
  console.log(`\nSMOKE JS Portaria Camera Flow v12.11.9.74 OK - ${ok}/${ok} checks`);
}
run().catch(err => { console.error(err); process.exit(1); });
