#!/usr/bin/env node
/* Smoke DOM v12.11.9.70 - simula o botão Mais da bottom nav de Alunos sem browser/BD. */
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const alunos = fs.readFileSync(path.join(root, 'admin/academic/alunos_lista.php'), 'utf8');
const match = alunos.match(/<script id="sige-alunos-bottom-more-menu-v1211970">([\s\S]*?)<\/script>/);
if (!match) {
  console.error('script v70 não encontrado');
  process.exit(1);
}
const js = match[1];

class ClassList {
  constructor(){ this.set = new Set(); }
  contains(c){ return this.set.has(c); }
  toggle(c, force){
    const on = typeof force === 'boolean' ? force : !this.set.has(c);
    if (on) this.set.add(c); else this.set.delete(c);
    return on;
  }
  add(c){ this.set.add(c); }
  remove(c){ this.set.delete(c); }
}
class El {
  constructor(id, tag='div'){
    this.id = id || '';
    this.tagName = tag.toUpperCase();
    this.attrs = {};
    this.classList = new ClassList();
    this.focused = false;
  }
  setAttribute(k,v){ this.attrs[k] = String(v); }
  getAttribute(k){ return this.attrs[k] ?? null; }
  focus(){ this.focused = true; }
  querySelector(sel){
    if (this.id === 'sige-sidebar' && sel.includes('.sige-menu-item')) return firstMenuItem;
    return null;
  }
  closest(sel){
    if (this === moreBtn && sel === '.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]') return this;
    if (this === overlay && sel === '#sige-overlay') return this;
    if (this === firstMenuItem && sel === '#sige-sidebar .sige-menu-item[href]') return this;
    return null;
  }
}
const body = new El('', 'body');
const sidebar = new El('sige-sidebar', 'aside');
const overlay = new El('sige-overlay', 'div');
const moreBtn = new El('', 'button');
moreBtn.classList.add('sige-mobile-more-trigger');
moreBtn.setAttribute('aria-controls', 'sige-sidebar');
moreBtn.setAttribute('aria-expanded', 'false');
const hamburger = new El('sige-hamburger', 'button');
const firstMenuItem = new El('', 'a');
firstMenuItem.classList.add('sige-menu-item');
firstMenuItem.setAttribute('href', '#first');

const listeners = {};
const document = {
  readyState: 'complete',
  body,
  getElementById(id){ return ({'sige-sidebar': sidebar, 'sige-overlay': overlay, 'sige-hamburger': hamburger})[id] || null; },
  querySelectorAll(sel){
    if (sel.includes('.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]')) return [moreBtn, hamburger];
    return [];
  },
  addEventListener(type, cb){ (listeners[type] ||= []).push(cb); }
};
const window = { document, setTimeout: (cb) => cb() };

new Function('window', 'document', js)(window, document);

function fire(type, event){
  for (const cb of listeners[type] || []) cb(event);
}
fire('click', { target: moreBtn, preventDefault(){} });
const openOk = sidebar.classList.contains('open') && overlay.classList.contains('show') && body.classList.contains('sg-app-menu-open') && moreBtn.getAttribute('aria-expanded') === 'true' && sidebar.getAttribute('aria-hidden') === 'false' && firstMenuItem.focused;
fire('keydown', { key: 'Escape', target: body });
const closeOk = !sidebar.classList.contains('open') && !overlay.classList.contains('show') && !body.classList.contains('sg-app-menu-open') && moreBtn.getAttribute('aria-expanded') === 'false' && sidebar.getAttribute('aria-hidden') === 'true';

if (!openOk || !closeOk) {
  console.error('DOM smoke falhou', { openOk, closeOk, expanded: moreBtn.getAttribute('aria-expanded'), sidebarHidden: sidebar.getAttribute('aria-hidden') });
  process.exit(1);
}
console.log('DOM smoke Alunos Mais v12.11.9.70 OK: abriu, focou e fechou por Escape.');
