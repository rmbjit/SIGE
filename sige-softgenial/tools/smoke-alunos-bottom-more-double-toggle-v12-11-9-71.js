/* Smoke JS v12.11.9.71 - confirma que o handler global em capture impede double-toggle com o fallback local. */
class ClassList {
  constructor(){ this.s = new Set(); }
  contains(c){ return this.s.has(c); }
  add(c){ this.s.add(c); }
  remove(c){ this.s.delete(c); }
  toggle(c, force){ const on = typeof force === 'boolean' ? force : !this.s.has(c); on ? this.s.add(c) : this.s.delete(c); return on; }
}
class El {
  constructor(id){ this.id = id; this.classList = new ClassList(); this.attrs = {}; }
  setAttribute(k,v){ this.attrs[k] = String(v); }
  getAttribute(k){ return this.attrs[k]; }
  matches(sel){ return this === button && sel === '.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]'; }
  closest(sel){
    if (this === button && sel.includes('.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]')) return button;
    return null;
  }
  querySelector(){ return null; }
}
const sidebar = new El('sige-sidebar');
const overlay = new El('sige-overlay');
const button = new El('mais');
button.setAttribute('aria-controls','sige-sidebar');
button.setAttribute('aria-expanded','false');
const body = { classList: new ClassList() };
let prevented = 0;
let stopped = 0;
let localBubbleRan = 0;
function sidebarState(open){
  sidebar.classList.toggle('open', open);
  overlay.classList.toggle('show', open);
  body.classList.toggle('sg-app-menu-open', open);
  button.setAttribute('aria-expanded', open ? 'true' : 'false');
}
function globalCapture(ev){
  const sidebarTrigger = ev.target.closest('#sige-hamburger,.sg-mobile-global-bottom-nav button[aria-controls="sige-sidebar"],.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]');
  if (sidebarTrigger && sidebarTrigger.matches('.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]')) {
    ev.preventDefault();
    ev.stopPropagation();
    sidebarState(!(sidebar && sidebar.classList.contains('open')));
  }
}
function localBubble(ev){
  const target = ev.target.closest('.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]');
  if (!target) return;
  localBubbleRan++;
  ev.preventDefault();
  sidebarState(!sidebar.classList.contains('open'));
}
function click(){
  let stop = false;
  const ev = { target: button, preventDefault(){ prevented++; }, stopPropagation(){ stopped++; stop = true; } };
  globalCapture(ev);
  if (!stop) localBubble(ev);
}
click();
const afterFirst = sidebar.classList.contains('open') && overlay.classList.contains('show') && body.classList.contains('sg-app-menu-open') && button.getAttribute('aria-expanded') === 'true';
click();
const afterSecond = !sidebar.classList.contains('open') && !overlay.classList.contains('show') && !body.classList.contains('sg-app-menu-open') && button.getAttribute('aria-expanded') === 'false';
const checks = {
  prevented_twice: prevented === 2,
  stopped_twice: stopped === 2,
  local_bubble_not_run: localBubbleRan === 0,
  first_click_opens_once: afterFirst,
  second_click_closes_once: afterSecond,
};
let failed = [];
for (const [k,v] of Object.entries(checks)) { console.log((v ? 'OK   ' : 'FAIL ') + k); if (!v) failed.push(k); }
if (failed.length) { console.error('Smoke double-toggle v12.11.9.71 falhou: ' + failed.join(', ')); process.exit(1); }
console.log('Smoke double-toggle v12.11.9.71 OK: ' + Object.keys(checks).length + '/' + Object.keys(checks).length + ' checks.');
