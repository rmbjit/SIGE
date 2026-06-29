const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
const build = JSON.parse(fs.readFileSync(path.join(root, 'BUILD.json'), 'utf8'));
if ((build.version || '') !== '12.11.9.74') {
  console.log('SKIP Smoke JS smoke-portaria-camera-policy-dom-v12-11-9-74.js - superseded by current build ' + (build.version || 'unknown'));
  process.exit(0);
}
const php = fs.readFileSync(path.join(root, 'admin/system/portaria-view.php'), 'utf8');
const match = php.match(/<script>([\s\S]*?)<\/script>/);
if (!match) throw new Error('Script inline da Portaria não encontrado.');
let js = match[1]
  .replace('var portariaNonce = <?php echo wp_json_encode(wp_create_nonce("sige_portaria_acesso")); ?>;', 'var portariaNonce = "nonce";')
  .replace("var portariaAjaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;", "var portariaAjaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';")
  .replace("var portariaAvatarDefault = <?php echo wp_json_encode(SIGE_URL . 'assets/img/avatar-default.svg'); ?>;", "var portariaAvatarDefault = '/assets/img/avatar-default.svg';");

const elements = {};
function el(id) {
  if (!elements[id]) {
    elements[id] = {
      id,
      textContent: '',
      innerHTML: '',
      hidden: false,
      disabled: false,
      value: '',
      className: '',
      style: {},
      setAttribute(k, v){ this[k] = v; },
      getAttribute(k){ return this[k]; },
      appendChild(){},
      addEventListener(){},
      classList: { remove(){}, add(){} }
    };
  }
  return elements[id];
}
const context = {
  console,
  setTimeout,
  clearTimeout,
  Promise,
  ajaxurl: '/wp-admin/admin-ajax.php',
  window: {
    isSecureContext: true,
    location: { protocol: 'https:', hostname: 'teste.softgenial.edu.mz' },
    addEventListener(){}
  },
  document: {
    readyState: 'loading',
    hidden: false,
    permissionsPolicy: { allowsFeature: (name) => name === 'camera' ? false : true },
    featurePolicy: null,
    getElementById: el,
    addEventListener(){}
  },
  navigator: {
    permissions: { query: async () => ({ state: 'granted' }) },
    mediaDevices: {
      enumerateDevices: async () => [{kind:'videoinput', deviceId:'cam1', label:'Integrated Camera'}],
      getUserMedia: async () => ({ getTracks: () => [{ stop(){} }] })
    }
  },
  Html5Qrcode: function(){ this.start = async () => true; this.stop = async () => true; this.clear = () => true; },
  jQuery: { post(){ return { fail(){ return this; }, always(){ return this; } }; } }
};
context.Html5Qrcode.getCameras = async () => [{id:'cam1', label:'Integrated Camera'}];
vm.createContext(context);
vm.runInContext(js, context, { filename: 'portaria-inline-v12.11.9.74.js' });

let ok = 0, fail = 0;
function check(cond, msg) { if (cond) { ok++; console.log('[OK]', msg); } else { fail++; console.log('[FAIL]', msg); } }

check(context.sgPortariaCameraPolicyStatus() === 'bloqueada', 'document policy bloqueada é detectada');
let infoPolicy = context.sgPortariaCameraErrorInfo({name:'PermissionsPolicyError'}, {policyStatus:'bloqueada', permissionState:'granted', phase:'permissions-policy'});
check(infoPolicy.title.includes('Política'), 'PermissionsPolicyError gera mensagem de política, não permissão do utilizador');
check(infoPolicy.text.includes('camera=()'), 'Mensagem de política aponta para camera=()');
context.sgPortariaRenderDiagnostic({permissionState:'granted', policyStatus:'bloqueada', videoCount:1, phase:'permissions-policy', errorName:'PermissionsPolicyError'});
check(el('sg-camera-diagnostic').innerHTML.includes('Política do documento'), 'Diagnóstico inclui política do documento');
check(el('sg-camera-diagnostic').innerHTML.includes('bloqueada'), 'Diagnóstico mostra policy bloqueada');

context.document.permissionsPolicy = { allowsFeature: (name) => name === 'camera' ? true : false };
check(context.sgPortariaCameraPolicyStatus() === 'permitida', 'document policy permitida é detectada');
let infoGrantedButDenied = context.sgPortariaCameraErrorInfo({name:'NotAllowedError'}, {policyStatus:'permitida', permissionState:'granted', phase:'html5qrcode-direct-1'});
check(infoGrantedButDenied.title.includes('permitida'), 'NotAllowed com permissão granted já não diz que o site está bloqueado');
let infoQr = context.sgPortariaCameraErrorInfo({name:'NotReadableError'}, {policyStatus:'permitida', permissionState:'granted', phase:'native-getUserMedia'});
check(infoQr.title.includes('ocupada') || infoQr.title.includes('indisponível'), 'NotReadable continua classificado como câmara ocupada/indisponível');

if (fail) {
  console.error(`SMOKE DOM v12.11.9.74 FALHOU: ${fail} falhas`);
  process.exit(1);
}
console.log(`SMOKE DOM v12.11.9.74 OK - ${ok} checks`);
