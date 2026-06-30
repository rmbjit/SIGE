# LIVE-TEST Alunos e Matrículas (v12.22.0)

Mudança só de apresentação. Foco: modais por cima da barra lateral, herói
compacto e nada de regressão funcional na lista/modais.

## Pré-condições

- Versão 12.22.0. Ctrl+F5. Desktop (a barra lateral está sempre visível).
- Testar com um perfil que pode criar/editar e, em separado, um perfil só de
  consulta (modo consulta).

## Apresentação (herói)

| Verificação | Esperado |
|---|---|
| Herói | Faixa única clara compacta, sem a ilustração de escola |
| Largura | Texto ocupa a faixa toda (sem coluna vazia à direita) |
| Acções | Registar Aluno / Importar Lista / Imprimir Cartões conforme permissões |

## Funcional - empilhamento dos modais (o ponto central)

| Fluxo | Esperado |
|---|---|
| Registar Aluno (`#modal-aluno`) | Abre centrado e **completo, por cima da barra lateral** (sidebar esbatida atrás) |
| Ficha 360º (`#modal-aluno-360`) | Idem, por cima da sidebar |
| Importar Lista (`#modal-import-alunos`) | Idem, por cima da sidebar |
| Alertas/confirmações (popup) | Aparecem por cima da sidebar |
| Scroll do modal | O corpo rola até ao último campo; cabeçalho/abas/rodapé fixos |
| Fechar | X, Cancelar, ESC e clique fora fecham; ao fechar, a barra lateral volta ao normal |

## Funcional - pesquisa e filtros (handlers convertidos)

| Acção | Esperado |
|---|---|
| Escrever na pesquisa | Filtra a lista em tempo real (data-sige-on-keyup) |
| Mudar filtro de turma | Recarrega a lista (submete o formulário) |
| Mudar filtro de estado | Recarrega a lista |

## Anti-regressão

- Lista de alunos carrega e mostra os alunos como antes (contrato de desempenho
  intacto).
- Gravar/editar aluno funciona; documentos e abas do modal intactos.
- Cartões/folhas imprimíveis com a mesma aparência.
- Sem erros de consola; sem violações CSP.
- Modo consulta: continua a bloquear criação/edição com aviso.

## Mobile/tablet

- Confirmar o cabeçalho mobile (hotfix) intacto e os modais a ocupar o ecrã.

## Clientes

- Validar em pelo menos um cliente real, cobrindo registar e editar um aluno.
