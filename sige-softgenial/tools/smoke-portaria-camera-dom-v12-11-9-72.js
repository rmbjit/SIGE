#!/usr/bin/env node
/*
 * Functional DOM smoke - SIGE v12.11.9.72 Portaria Camera Permission UX PRO
 * Dependency-free DOM simulation for camera, Html5Qrcode and AJAX flows.
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const build = JSON.parse(fs.readFileSync(path.join(root, 'BUILD.json'), 'utf8'));
if ((build.version || '') !== '12.11.9.72') {
  console.log('SKIP Smoke JS smoke-portaria-camera-dom-v12-11-9-72.js - superseded by current build ' + (build.version || 'unknown'));
  process.exit(0);
}
const viewPath = path.join(root, 'admin/system/portaria-view.php');
const view = fs.readFileSync(viewPath, 'utf8');
const scriptMatch = view.match(/<script>\s*([\s\S]*?)\s*<\/script>\s*$/);
if (!scriptMatch) throw new Error('Não foi possível extrair JS inline da Portaria.');
let js = scriptMatch[1]
  .replace(/<\?php echo wp_json_encode\(wp_create_nonce\("sige_portaria_acesso"\)\); \?>/g, '"nonce-v72"')
  .replace(/<\?php echo wp_json_encode\(admin_url\('admin-ajax\.php'\)\); \?>/g, '"/admin-ajax.php"')
  .replace(/<\?php echo wp_json_encode\(SIGE_URL \. 'assets\/img\/avatar-default\.svg'\); \?>/g, '"avatar.svg"');

class FakeClassList {
  constructor(el){ this.el = el; }
  _set(){ return new Set(String(this.el.className || '').split(/\s+/).filter(Boolean)); }
  _write(set){ this.el.className = Array.from(set).join(' '); }
  add(...names){ const s=this._set(); names.forEach(n=>s.add(n)); this._write(s); }
  remove(...names){ const s=this._set(); names.forEach(n=>s.delete(n)); this._write(s); }
  contains(name){ return this._set().has(name); }
}
class FakeElement {
  constructor(tag='div', id=''){
    this.tagName = tag.toUpperCase();
    this.id = id;
    this.children = [];
    this.parentNode = null;
    this.className = '';
    this.classList = new FakeClassList(this);
    this.attributes = {};
    this.style = {};
    this.hidden = false;
    this.disabled = false;
    this.value = '';
    this._textContent = '';
    this._innerHTML = '';
    this.listeners = {};
    this.src = '';
  }
  set textContent(v){ this._textContent = String(v ?? ''); }
  get textContent(){ return this._textContent + this.children.map(c=>c.textContent).join(''); }
  set innerHTML(v){
    this._innerHTML = String(v ?? '');
    this.children = [];
    if (this.tagName === 'SELECT' && /<option/i.test(this._innerHTML)) {
      const opt = new FakeElement('option');
      opt.value = '';
      opt.textContent = this._innerHTML.replace(/<[^>]+>/g, '').trim() || 'Opção';
      this.appendChild(opt);
    }
  }
  get innerHTML(){ return this._innerHTML; }
  get options(){ return this.children.filter(c => c.tagName === 'OPTION'); }
  appendChild(child){ child.parentNode = this; this.children.push(child); return child; }
  setAttribute(name, value){ this.attributes[name] = String(value); if (name === 'class') this.className = String(value); if (name === 'aria-hidden') this.ariaHidden = String(value); }
  getAttribute(name){ return this.attributes[name]; }
  addEventListener(type, fn){ (this.listeners[type] ||= []).push(fn); }
  dispatchEvent(type, event={}){ (this.listeners[type] || []).forEach(fn => fn(Object.assign({ preventDefault(){}, key: undefined }, event))); }
  click(){ this.dispatchEvent('click', { preventDefault(){} }); }
  focus(){ this.focused = true; }
  play(){ return Promise.resolve(); }
}
class FakeDocument {
  constructor(width){
    this.readyState = 'complete';
    this.hidden = false;
    this.listeners = {};
    this.map = {};
    this.documentElement = new FakeElement('html', 'html');
    this.documentElement.scrollWidth = width;
    this.body = new FakeElement('body', 'body');
  }
  add(el){ this.map[el.id] = el; this.body.appendChild(el); return el; }
  getElementById(id){ return this.map[id] || null; }
  createElement(tag){ return new FakeElement(tag); }
  addEventListener(type, fn){ (this.listeners[type] ||= []).push(fn); }
  querySelectorAll(selector){
    const wanted = selector.split('.').filter(Boolean);
    const out = [];
    function walk(el){
      if (wanted.length && wanted.every(c => el.classList && el.classList.contains(c))) out.push(el);
      el.children.forEach(walk);
    }
    walk(this.body);
    return out;
  }
}
function buildContext(width){
  const document = new FakeDocument(width);
  const ids = [
    ['sg-camera-frame','div','sg-camera-frame is-waiting'], ['reader','div',''], ['sg-camera-permission','div',''],
    ['sg-camera-permission-title','h4',''], ['sg-camera-permission-text','p',''], ['sg-camera-start','button',''],
    ['sg-camera-status','div',''], ['sg-camera-status-title','strong',''], ['sg-camera-status-text','span',''],
    ['sg-camera-select','select',''], ['sg-camera-retry','button',''], ['sg-camera-stop','button',''],
    ['sg-portaria-manual-box','div','sg-portaria-manual'], ['manual-proc','input',''], ['sg-portaria-manual-btn','button',''],
    ['result-panel','div','result-box waiting'], ['r-chip','span',''], ['r-time','small',''], ['r-status','div',''],
    ['r-nome','div',''], ['r-turma','div',''], ['r-obs','div',''], ['r-id-help','strong',''], ['r-action-help','strong',''],
    ['r-foto','img',''], ['sg-portaria-history-list','div',''], ['audio-success','audio',''], ['audio-error','audio','']
  ];
  ids.forEach(([id, tag, cls]) => { const el = document.add(new FakeElement(tag, id)); el.className = cls; });
  document.getElementById('sg-camera-stop').hidden = true;
  document.getElementById('sg-camera-select').hidden = true;
  const window = { innerWidth: width, isSecureContext: true, listeners: {}, addEventListener(type, fn){ (this.listeners[type] ||= []).push(fn); } };
  const navigator = { mediaDevices: { getUserMedia(){ return Promise.resolve({ getTracks(){ return [{ stop(){ window.__trackStopped = true; } }]; } }); } } };
  class Html5Qrcode {
    constructor(id){ this.id = id; window.__readerId = id; }
    start(cameraConfig, scannerConfig, onSuccess){
      window.__cameraStarted = true;
      window.__cameraConfig = cameraConfig;
      window.__qrbox = scannerConfig.qrbox(320, 240);
      window.__scanSuccess = onSuccess;
      return Promise.resolve();
    }
    stop(){ window.__cameraStopped = true; return Promise.resolve(); }
    clear(){ window.__cameraCleared = true; }
  }
  Html5Qrcode.getCameras = () => Promise.resolve([{ id:'front-1', label:'Câmara frontal' }, { id:'back-1', label:'Câmara traseira' }]);
  const jQuery = { post(url, data, cb){
    window.__lastAjax = { url, data };
    setTimeout(() => cb({ success:true, data:{ status:'activo', nome:'Ana Câmara Teste', turma:'2º Ano - A', foto:'', cor:'#4caf50', mensagem:'ENTRADA AUTORIZADA', som:'success', processo:data.qr_code } }), 5);
    return { fail(){ return this; }, always(fn){ setTimeout(fn, 10); return this; } };
  }};
  const context = { window, document, navigator, Html5Qrcode, jQuery, ajaxurl:'/admin-ajax.php', console, setTimeout, clearTimeout, Date, String, Array, RegExp, Promise, Math };
  context.globalThis = context;
  return context;
}
function wait(ms){ return new Promise(resolve => setTimeout(resolve, ms)); }
function check(checks, name, ok){ checks.push({ name, ok: !!ok }); }
async function runWidth(width){
  const ctx = buildContext(width);
  vm.createContext(ctx);
  vm.runInContext(js, ctx, { filename: 'portaria-inline-v72.js' });
  const d = ctx.document, w = ctx.window;
  const checks = [];
  check(checks, 'start_button_bound', !!d.getElementById('sg-camera-start').listeners.click?.length);
  d.getElementById('sg-camera-start').click();
  await wait(30);
  check(checks, 'get_user_media_called', w.__trackStopped === true);
  check(checks, 'html5qrcode_started', w.__cameraStarted === true);
  check(checks, 'frame_running', d.getElementById('sg-camera-frame').classList.contains('is-running'));
  check(checks, 'status_active', /Câmara activa/.test(d.getElementById('sg-camera-status-title').textContent));
  check(checks, 'stop_visible', d.getElementById('sg-camera-stop').hidden === false);
  check(checks, 'select_populated', d.getElementById('sg-camera-select').options.length >= 3);
  check(checks, 'qrbox_responsive', w.__qrbox && w.__qrbox.width >= 170 && w.__qrbox.width <= 280);
  w.__scanSuccess('12345');
  await wait(30);
  check(checks, 'ajax_action_sent', w.__lastAjax?.data?.action === 'sige_validar_acesso');
  check(checks, 'ajax_nonce_sent', w.__lastAjax?.data?._sige_nonce === 'nonce-v72');
  check(checks, 'result_success', d.getElementById('result-panel').classList.contains('success'));
  check(checks, 'result_name', /Ana Câmara Teste/.test(d.getElementById('r-nome').textContent));
  check(checks, 'history_item', d.querySelectorAll('.sg-history-item.success').length >= 1);
  d.getElementById('manual-proc').value = '98765';
  d.getElementById('sg-portaria-manual-btn').click();
  await wait(30);
  check(checks, 'manual_ajax_sent', w.__lastAjax?.data?.qr_code === '98765' && w.__lastAjax?.data?.origem === 'manual');
  d.getElementById('sg-camera-stop').click();
  await wait(20);
  check(checks, 'stop_camera_called', w.__cameraStopped === true);
  check(checks, 'no_horizontal_overflow_model', d.documentElement.scrollWidth <= width + 1);
  const failed = checks.filter(c => !c.ok);
  return { width, pass: failed.length === 0, total: checks.length, failed, checks };
}
(async () => {
  const widths = [360, 390, 430, 760, 900];
  const results = [];
  for (const width of widths) results.push(await runWidth(width));
  const failed = results.filter(r => !r.pass);
  if (failed.length) {
    console.error(JSON.stringify(failed, null, 2));
    process.exit(1);
  }
  console.log(`Smoke DOM Portaria Camera v12.11.9.72 OK: ${widths.length}/${widths.length} viewports; ${results[0].total} functional checks por viewport.`);
})();
