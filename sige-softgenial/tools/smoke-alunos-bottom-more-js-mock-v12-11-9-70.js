#!/usr/bin/env node
/**
 * Smoke JS mock v12.11.9.70 - bottom nav “Mais” no módulo Alunos.
 * Executa o handler num DOM mínimo, sem browser e sem BD.
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
const alunos = fs.readFileSync(path.join(root, 'admin/academic/alunos_lista.php'), 'utf8');
const m = alunos.match(/<script[^>]*id="sige-alunos-bottom-more-menu-v1211970"[^>]*>([\s\S]*?)<\/script>/);
if (!m) throw new Error('Script sige-alunos-bottom-more-menu-v1211970 não encontrado.');
const script = m[1];

function makeClassList(){
  const set = new Set();
  return {
    contains: c => set.has(c),
    add: c => set.add(c),
    remove: c => set.delete(c),
    toggle: (c, force) => { const on = (typeof force === 'boolean') ? force : !set.has(c); on ? set.add(c) : set.delete(c); return on; }
  };
}
function makeEl(name){
  const attrs = {};
  return {
    name,
    classList: makeClassList(),
    attrs,
    focused: false,
    setAttribute(k,v){ attrs[k]=String(v); },
    getAttribute(k){ return Object.prototype.hasOwnProperty.call(attrs,k) ? attrs[k] : null; },
    focus(){ this.focused = true; },
    querySelector(){ return null; },
    closest(){ return null; }
  };
}
const sidebar = makeEl('sidebar'); sidebar.setAttribute('aria-hidden','true');
const overlay = makeEl('overlay');
const body = makeEl('body');
const link = makeEl('link'); link.classList.add('sige-menu-item'); link.setAttribute('href','#');
sidebar.querySelector = sel => (sel.includes('sige-menu-item') || sel.includes('a[href]')) ? link : null;
const button = makeEl('button');
button.setAttribute('aria-controls','sige-sidebar');
button.setAttribute('aria-expanded','false');
button.closest = sel => sel.includes('.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]') ? button : null;
overlay.closest = sel => sel.includes('#sige-overlay') ? overlay : null;
link.closest = sel => sel.includes('#sige-sidebar .sige-menu-item[href]') ? link : null;
const listeners = {click:[], keydown:[], DOMContentLoaded:[]};
const document = {
  readyState: 'complete',
  body,
  getElementById(id){ return id === 'sige-sidebar' ? sidebar : id === 'sige-overlay' ? overlay : null; },
  querySelectorAll(sel){ return sel.includes('aria-controls="sige-sidebar"') || sel.includes('#sige-hamburger') ? [button] : []; },
  querySelector(sel){ return sel.includes('.sige-mobile-bottom-nav button') ? button : null; },
  addEventListener(type, cb){ (listeners[type] || (listeners[type]=[])).push(cb); }
};
const window = { document, setTimeout: cb => cb() };
const ctx = { window, document, Array, console };
vm.createContext(ctx);
vm.runInContext(script, ctx);
function fire(type, target, extra={}){
  let prevented=false;
  const ev = Object.assign({target, preventDefault(){prevented=true;}, stopPropagation(){}}, extra);
  for (const cb of listeners[type] || []) cb(ev);
  return {prevented};
}
function assert(name, cond){ if(!cond) throw new Error('FAIL ' + name); console.log('OK   ' + name); }
let e = fire('click', button);
assert('click_prevented', e.prevented);
assert('open_sidebar', sidebar.classList.contains('open'));
assert('open_overlay', overlay.classList.contains('show'));
assert('open_body', body.classList.contains('sg-app-menu-open'));
assert('open_aria', button.getAttribute('aria-expanded') === 'true' && sidebar.getAttribute('aria-hidden') === 'false');
assert('focus_menu_item', link.focused === true);
fire('click', button);
assert('close_second_click', !sidebar.classList.contains('open') && button.getAttribute('aria-expanded') === 'false' && sidebar.getAttribute('aria-hidden') === 'true');
fire('click', button);
fire('keydown', document, {key:'Escape'});
assert('escape_closes', !sidebar.classList.contains('open') && button.getAttribute('aria-expanded') === 'false');
console.log('Smoke JS Bottom More v12.11.9.70 OK: 8/8 checks.');
