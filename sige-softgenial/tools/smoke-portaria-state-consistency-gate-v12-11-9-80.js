#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
const safe = fs.readFileSync(path.join(root, 'includes/portaria-camera-safe-page.php'), 'utf8');
const start = safe.indexOf('function boolTrue');
const end = safe.indexOf('function showResult', start);
if (start < 0 || end < 0) throw new Error('Não foi possível extrair boolTrue/accessDecision');
const ctx = {};
vm.createContext(ctx);
vm.runInContext(safe.slice(start, end), ctx);
function assert(cond, msg) { if (!cond) throw new Error(msg); }
function dec(payload) { return ctx.accessDecision(payload); }

const blockedWithGreen = dec({
  permitido: false,
  acesso_permitido: false,
  bloqueado: true,
  cor: '#4caf50',
  som: 'success',
  mensagem: 'ACESSO BLOQUEADO',
  obs: 'Situação do aluno: Desistente. Não permitir entrada. Encaminhar à Secretaria.',
  nome: 'Antonia Macario'
});
assert(blockedWithGreen.ok === false, 'Bloqueado inconsistente não pode ficar ok');
assert(blockedWithGreen.cls === 'error', 'Bloqueado inconsistente deve ser error/vermelho');
assert(blockedWithGreen.chip === 'BLOQUEADO', 'Bloqueado inconsistente deve ter chip BLOQUEADO');
assert(blockedWithGreen.msg === 'ACESSO BLOQUEADO', 'Bloqueado inconsistente deve ter título ACESSO BLOQUEADO');

const active = dec({ permitido: true, acesso_permitido: true, bloqueado: false, resultado: 'autorizado' });
assert(active.ok === true, 'Activo autorizado deve ficar ok');
assert(active.cls === 'success', 'Activo autorizado deve ficar success/verde');
assert(active.chip === 'AUTORIZADO', 'Activo autorizado deve ter chip AUTORIZADO');
assert(active.msg === 'ENTRADA AUTORIZADA', 'Activo autorizado deve ter título ENTRADA AUTORIZADA');

const conflict = dec({ permitido: true, acesso_permitido: true, bloqueado: true, resultado: 'bloqueado', som: 'success' });
assert(conflict.ok === false, 'Se bloqueado=true vier junto de permitido=true, bloqueio deve vencer');
assert(conflict.cls === 'error', 'Conflito deve ficar vermelho/error');

const legacySoundOnly = dec({ som: 'success', cor: '#4caf50', mensagem: 'ENTRADA AUTORIZADA' });
assert(legacySoundOnly.ok === false, 'Som/cor sem permitido explícito não autorizam entrada');
assert(legacySoundOnly.chip === 'BLOQUEADO', 'Sem permitido explícito fica bloqueado por segurança');

console.log('Smoke JS Portaria State Consistency v12.11.9.80 OK: 12/12 checks.');
