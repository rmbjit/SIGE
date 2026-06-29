# DEPLOY - SIGE SoftGenial v12.10.0.1

**Hotfix:** design alinhado ao plugin + honestidade do Registry (4 chaves marcadas como informativas).

---

## Resumo de impacto

- **Tipo:** Hotfix UI + Registry. Lógica de gravação/leitura/auditoria **idêntica à v12.10.0**.
- **Ficheiros alterados:** 6 (sige-softgenial.php, BUILD.json, Registry, View Renderer, config-center-view, config-view).
- **Sem migração de BD.** Sem alterações a outros módulos.
- **Tempo por escola:** 2 a 3 minutos.
- **Rollback:** < 2 minutos.

---

## Ordem de propagação

```
1. Demo → 2. Teste → 3. Liceu Muhalaze → 4. Casa Colorida → 5. Malisa
```

---

## Instalação

1. **Backup do actual.** Hostinger File Manager → `/public_html/wp-content/plugins/` → comprimir `sige-softgenial/` como `sige-softgenial-12.10.0-backup.zip`.

2. **Confirmar v12.10.0 instalada.** WordPress admin → Plugins → versão deve dizer **12.10.0**. Se diferente, **parar** e investigar.

3. **Substituir.** Renomear `sige-softgenial/` → `sige-softgenial-OLD-12.10.0/`. Upload do ZIP novo. Extrair.

4. **Reactivar plugin** (se foi desactivado).

---

## Smoke test (3 minutos)

### Test 1 - Aspecto geral

WordPress admin → SIGE → Sistema → **Centro de Configuração**.

**Esperado:**
- Cabeçalho simples: **"Centro de Configuração"** + tag `v12.10.0.1` ao lado
- **Sem** hero gradient grande no topo
- **Sem** badges promocionais ("Schema-driven", "Auditoria activa", etc.)
- **Sem** descrições paragrafais em cada secção
- Tabs estilo simples (parecidas com as do WhatsApp Central)
- Cor primária `#0d1259` (escuro azul-violeta, igual ao resto do plugin)

### Test 2 - Pills "informativo" nas 4 chaves preservadas

Abrir aba **Identidade**.

**Esperado:** os campos seguintes mostram pill amarelo "informativo" ao lado do label:
- Tipo de instituição `[informativo]`
- Níveis de ensino `[informativo]`
- Entidade proprietária `[informativo]`
- Ano de fundação `[informativo]`

Passar o rato sobre o pill mostra o tooltip:
> "Campo preservado para uso futuro. Ainda sem efeito operacional no plugin."

### Test 3 - Separador "Diagnóstico"

Se modo técnico activo: o último separador chama-se **"Diagnóstico"** (não "Técnico SoftGenial").

Os campos lá (versão, build, licença, financeiro.*) mostram pill cinza "leitura".

### Test 4 - Escrita continua a funcionar

- Alterar o **rodapé dos documentos** para `Teste 12.10.0.1 - DD/MM/YYYY`.
- Click "Guardar".
- Esperar mensagem verde "Configurações guardadas com segurança." (curta, sem ruído).
- F5 → valor preservado.

### Test 5 - WhatsApp/SMTP funcionam (crítico)

- Enviar uma mensagem WhatsApp de teste → confirma entrega.
- Enviar um email de teste → confirma chegada.

Se algum dos 5 testes falhar → **rollback** (ver abaixo).

---

## Rollback

1. WordPress admin → Plugins → desactivar `sige-softgenial`.
2. File Manager → renomear `sige-softgenial/` → `sige-softgenial-FAILED-12.10.0.1/`.
3. Renomear `sige-softgenial-OLD-12.10.0/` → `sige-softgenial/`.
4. Reactivar plugin.
5. Voltou à v12.10.0 funcional.

Não propagar para os próximos clientes até saber a causa.

---

## Comparação visual rápida

**Antes (v12.10.0):**
- Hero azul-ciano com gradient, 5 badges, kicker "SoftGenial · Configuração v12.10.0"
- "Identidade institucional" + parágrafo "Dados usados em documentos, recibos..."
- `<small>` debaixo de quase todos os campos
- Cards com bordas grandes 22px, sombras dramáticas

**Depois (v12.10.0.1):**
- "Centro de Configuração" + tag `v12.10.0.1`
- Tabs simples: Identidade · Localização · Direcção · Marca · Académico · Operação · Comunicação · Módulos · Diagnóstico
- Apenas 1 help text na UI inteira (no token WhatsApp: "Vazio preserva")
- Cards limpos, bordas 10px, sombra subtil

---

**Documento gerado:** 16 Maio 2026
**Versão:** 12.10.0.1
**Baseline:** v12.10.0 validada em produção
