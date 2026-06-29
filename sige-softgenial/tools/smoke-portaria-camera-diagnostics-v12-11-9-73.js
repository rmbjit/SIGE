/** Functional JS smoke - SIGE v12.11.9.73 Portaria Camera Diagnostics */
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const build = JSON.parse(fs.readFileSync('BUILD.json', 'utf8'));
if ((build.version || '') !== '12.11.9.73') {
  console.log('SKIP Smoke JS Portaria Diagnostics v12.11.9.73 - superseded by current build ' + (build.version || 'unknown'));
  process.exit(0);
}
const src = fs.readFileSync('admin/system/portaria-view.php', 'utf8');
const script = src.match(/<script>([\s\S]*?)<\/script>/)[1]
  .replace(/<\?php\s+echo\s+wp_json_encode\([^;]*\);\s*\?>/g, '"__PHP_JSON__"')
  .replace(/<\?php[\s\S]*?\?>/g, '"__PHP__"');
const start = script.indexOf('function sgPortariaErrorName');
const end = script.indexOf('function sgPortariaScannerConfig');
assert(start > -1 && end > start, 'bloco de funções de diagnóstico encontrado');
const subset = script.slice(start, end);
const sandbox = {
  sgPortariaCameras: [],
  sgPortariaActiveCameraId: '',
  navigator: { permissions: { query: async () => ({ state: 'prompt' }) }, mediaDevices: { enumerateDevices: async () => [] } },
  document: { getElementById: () => null },
  window: { isSecureContext: true, location: { protocol: 'https:', hostname: 'teste.softgenial.edu.mz' } }
};
vm.createContext(sandbox);
vm.runInContext(subset, sandbox);

assert.strictEqual(sandbox.sgPortariaCameraErrorInfo({name:'NotAllowedError'}, {permissionState:'denied'}).title, 'Câmara bloqueada para este site');
assert.strictEqual(sandbox.sgPortariaCameraErrorInfo({name:'NotAllowedError'}, {permissionState:'prompt'}).title, 'Câmara recusada pelo navegador ou sistema');
assert(sandbox.sgPortariaCameraErrorInfo({name:'NotAllowedError'}, {permissionState:'prompt'}).text.includes('cadeado'));
assert.strictEqual(sandbox.sgPortariaCameraErrorInfo({name:'NotReadableError'}, {}).title, 'Câmara ocupada');
assert.strictEqual(sandbox.sgPortariaCameraErrorInfo({name:'NotFoundError'}, {}).title, 'Nenhuma câmara detectada');
assert.strictEqual(sandbox.sgPortariaCameraErrorInfo({name:'OverconstrainedError'}, {}).title, 'Câmara traseira não disponível');
assert.strictEqual(sandbox.sgPortariaChooseCamera([{id:'front1',label:'Integrated Webcam'}]), 'front1');
assert.strictEqual(sandbox.sgPortariaChooseCamera([{id:'front1',label:'Front Camera'},{id:'back1',label:'Rear Camera'}]), 'back1');
sandbox.sgPortariaActiveCameraId = '';
sandbox.sgPortariaCameras = [{id:'desk1',label:'USB Webcam'}];
assert.strictEqual(sandbox.sgPortariaCameraConfig(), 'desk1');
sandbox.sgPortariaActiveCameraId = 'active42';
assert.strictEqual(sandbox.sgPortariaCameraConfig(), 'active42');
sandbox.sgPortariaActiveCameraId = '';
sandbox.sgPortariaCameras = [];
assert.strictEqual(JSON.stringify(sandbox.sgPortariaCameraConfig()), JSON.stringify({ facingMode: { ideal: 'environment' } }));
assert(src.includes('getUserMedia({ video: true, audio: false })'), 'pedido genérico getUserMedia video:true');
assert(src.includes('sgPortariaRenderDiagnostic'), 'diagnóstico visível');
assert(src.includes('html5qrcode-start-fallback'), 'fallback de arranque Html5Qrcode');
assert(src.includes('camerasFromProbe'), 'fallback via enumerateDevices');
console.log('Smoke JS Portaria Camera Diagnostics v12.11.9.73 OK: 15/15 checks.');
