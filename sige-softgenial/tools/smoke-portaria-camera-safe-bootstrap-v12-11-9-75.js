#!/usr/bin/env node
/** Smoke JS - SIGE v12.11.9.75 Portaria Camera Safe Bootstrap PRO. */
const fs = require('fs');
const vm = require('vm');
const path = require('path');

const root = path.resolve(__dirname, '..');
let php = fs.readFileSync(path.join(root, 'admin/system/portaria-view.php'), 'utf8');
let match = php.match(/<script>([\s\S]*?)<\/script>/);
if (!match) throw new Error('Script inline da Portaria não encontrado');
let js = match[1]
  .replace(/<\?php echo wp_json_encode\(wp_create_nonce\("sige_portaria_acesso"\)\); \?>/g, '"nonce"')
  .replace(/<\?php echo wp_json_encode\(admin_url\('admin-ajax\.php'\)\); \?>/g, '"/wp-admin/admin-ajax.php"')
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
    srcObject: null,
    muted: false,
    playsInline: false,
    readyState: 4,
    style: {},
    className: '',
    children: [],
    setAttribute(name, value) { this[name] = String(value); },
    getAttribute(name) { return this[name]; },
    appendChild(child) { this.children.push(child); return child; },
    addEventListener(type, fn) { this['on' + type] = fn; },
    focus() { this.focused = true; },
    remove() { this.removed = true; },
    pause() { this.paused = true; },
    play: async () => true,
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
  let getUserMediaCalls = [];
  let startPlan = Array.isArray(opts.startPlan) ? opts.startPlan.slice() : ['resolve'];
  class Html5QrcodeMock {
    constructor(id) { this.id = id; }
    async start(config) {
      qrStartCalls.push(config);
      const action = startPlan.length ? startPlan.shift() : 'resolve';
      if (action === 'rejectNotAllowed') { const e = new Error('Denied'); e.name = 'NotAllowedError'; throw e; }
      if (action === 'rejectNotReadable') { const e = new Error('Busy'); e.name = 'NotReadableError'; throw e; }
      if (action === 'rejectGeneric') { const e = new Error('QR fail'); e.name = 'QrStartError'; throw e; }
      return true;
    }
    async stop() { return true; }
    clear() { return true; }
    static async getCameras() { return opts.cameras || [{ id: 'cam-back', label: 'Back Camera' }, { id: 'cam-front', label: 'Front Camera' }]; }
  }
  class BarcodeDetectorMock {
    constructor() {}
    async detect() { return []; }
    static async getSupportedFormats() { return opts.barcodeFormats || ['qr_code']; }
  }
  const stream = { getTracks: () => [{ stop() { this.stopped = true; } }] };
  const context = {
    console,
    setTimeout: (fn) => { fn(); return 1; },
    clearTimeout: () => {},
    requestAnimationFrame: () => 1,
    cancelAnimationFrame: () => {},
    window: { isSecureContext: true, location: { protocol: 'https:', hostname: 'teste.softgenial.edu.mz' }, addEventListener: () => {} },
    document: {
      readyState: 'loading',
      hidden: false,
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
        getUserMedia: async (constraints) => {
          getUserMediaCalls.push(constraints);
          if (opts.nativeReject) { const e = new Error('Native failed'); e.name = opts.nativeReject; throw e; }
          return stream;
        }
      }
    },
    Html5Qrcode: opts.noHtml5Qrcode ? undefined : Html5QrcodeMock,
    BarcodeDetector: opts.noBarcodeDetector ? undefined : BarcodeDetectorMock,
    jQuery: { post: () => ({ fail: () => ({ always: () => {} }) }) },
    ajaxurl: '/wp-admin/admin-ajax.php',
    __state: { elements, qrStartCalls, getUserMediaCalls }
  };
  context.window.window = context.window;
  if (!opts.noBarcodeDetector) context.window.BarcodeDetector = BarcodeDetectorMock;
  vm.createContext(context);
  vm.runInContext(js, context, { filename: 'portaria-inline-v12.11.9.75.js' });
  return context;
}

async function run() {
  let ok = 0; let fail = 0;
  function check(cond, msg) { if (cond) { ok++; console.log('[OK]', msg); } else { fail++; console.error('[FAIL]', msg); } }

  const c1 = createContext({ startPlan: ['resolve'], permissionState: 'granted', policyAllowed: true });
  await c1.sgPortariaStartCamera();
  check(c1.__state.getUserMediaCalls.length >= 1, 'Fluxo principal executa bootstrap getUserMedia antes do QR');
  check(c1.__state.qrStartCalls.length === 1, 'Fluxo principal chama Html5Qrcode.start uma vez após bootstrap');
  check(c1.sgPortariaStarted === true && c1.sgPortariaStartedMode === 'html5qrcode', 'Estado fica activo em modo Html5Qrcode');
  check(c1.__state.elements.get('sg-camera-status-title').textContent === 'Câmara activa', 'UI mostra Câmara activa após sucesso');

  const c2 = createContext({ startPlan: ['resolve'], permissionState: 'granted', policyAllowed: false });
  await c2.sgPortariaStartCamera();
  check(c2.__state.getUserMediaCalls.length >= 1, 'Policy API bloqueada não impede teste real de getUserMedia');
  check(c2.__state.qrStartCalls.length === 1, 'Policy API bloqueada não impede QR quando câmara abre');
  check(c2.sgPortariaStarted === true, 'Mobile com sinal policy API falso consegue iniciar');

  const c3 = createContext({ startPlan: ['rejectNotReadable'], permissionState: 'granted', policyAllowed: true });
  await c3.sgPortariaStartCamera();
  check(c3.sgPortariaStarted === true && c3.sgPortariaStartedMode === 'native-barcode-detector', 'NotReadable do Html5Qrcode activa fallback nativo BarcodeDetector');
  check(c3.__state.elements.get('reader').children.length >= 1, 'Fallback nativo injecta vídeo no reader');
  check(/modo compatível/i.test(c3.__state.elements.get('sg-camera-status-title').textContent), 'UI indica modo compatível');

  const c4 = createContext({ startPlan: ['resolve'], permissionState: 'granted', policyAllowed: true, nativeReject: 'NotReadableError' });
  await c4.sgPortariaStartCamera();
  const t4 = c4.__state.elements.get('sg-camera-status-title').textContent + ' ' + c4.__state.elements.get('sg-camera-status-text').textContent;
  check(c4.__state.qrStartCalls.length === 0, 'Se o bootstrap nativo falha, QR não é iniciado');
  check(/não conseguiu iniciar/i.test(t4), 'NotReadable nativo mostra mensagem correcta');
  check(/Não encontrei uma aplicação aberta/i.test(t4), 'Mensagem NotReadable não culpa apenas Teams/Zoom/WhatsApp');

  const c5 = createContext({ noHtml5Qrcode: true, permissionState: 'granted', policyAllowed: true });
  await c5.sgPortariaStartCamera();
  check(c5.sgPortariaStarted === true && c5.sgPortariaStartedMode === 'native-barcode-detector', 'Sem Html5Qrcode, fallback nativo pode iniciar se BarcodeDetector existir');

  if (fail) { console.error(`\nSMOKE JS v12.11.9.75 FALHOU: ${fail} falhas`); process.exit(1); }
  console.log(`\nSMOKE JS Portaria Camera Safe Bootstrap v12.11.9.75 OK - ${ok}/${ok} checks`);
}
run().catch(err => { console.error(err); process.exit(1); });
