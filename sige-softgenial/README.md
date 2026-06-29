# SIGE SoftGenial - Gestão Escolar Moçambique

Plugin WordPress de gestão integrada para escolas (SaaS multi-tenant). Cobre o ciclo completo: alunos e matrículas, académico (notas, pautas, DEC, actas, boletins), financeiro (lançamentos, pagamentos, recibos, extractos e fecho de caixa), recursos humanos, pré-escolar (Jardim), transporte, portaria, comunicação (WhatsApp e e-mail) e portal do aluno/encarregado.

Autor: RMBJ Consultoria · https://softgenial.edu.mz

## Estrutura do projecto

```
sige-softgenial/
├── sige-softgenial.php      Bootstrap: constantes, ordem de carregamento, hooks de activação
├── BUILD.json               Manifesto da build (fonte de verdade de versão junto do header e SIGE_VERSION)
├── CHANGELOG.md             Registo consolidado de versões (gerado/actualizado pelo build-release)
├── uninstall.php            Desinstalação conservadora: nunca apaga dados da escola
├── admin/                   Views (páginas renderizadas no app shell)
│   ├── academic/            Alunos, turmas, notas, pautas, DEC, actas, boletins, portal
│   ├── finance/             Pagamentos, lançamentos, extractos/caixa, despesas, relatórios
│   ├── hr/                  Equipa e professores
│   ├── jardim/              Pré-escolar (diário, saúde, presenças, boletim)
│   ├── logistics/           Transporte
│   └── system/              Painel, configuração, permissões, portaria, currículos
├── includes/                Lógica de negócio, motores e camadas transversais
│   ├── core/                Fundação (licenciamento, diagnóstico, logs, fila)
│   ├── hub/                 Cliente do SigeHub (heartbeat, billing, comandos, updates)
│   ├── settings/            Centro de Configuração (schema único + controller + renderer)
│   ├── infra/               Logger e fila
│   └── licensing/           Licença local
├── assets/                  CSS/JS partilhados, ícones SVG, áudio
├── tools/                   Utilitários de desenvolvimento (apenas CLI; bloqueados via web)
├── docs/                    Histórico do projecto
│   ├── changelog/           Changelogs integrais por versão
│   ├── deploy/              Notas de deploy por versão
│   ├── qa/                  Relatórios de smoke/QA
│   ├── relatorios/          Relatórios de fase (blindagem, etc.)
│   └── legado/              Documentos das eras V6/V7
└── languages/               Reservado para traduções (Text Domain: sige-softgenial)
```

## Convenções obrigatórias

1. **Sempre progresso, nunca regressão.** Trabalhar sobre o último plugin validado.
2. **Regras financeiras e académicas aprovadas são intocáveis.** Fórmulas canónicas de tesouraria: `sige_fin_saldo_sql` / `sige_fin_saldo_lancamento`. Fórmulas académicas: MFD=(MT1+MT2+MT3)/3 com nulo=0; 3ª classe=AF/MF; 6ª+=Exame/NF.
3. **Entrega = ZIP instalável completo** + notas de DEPLOY + pistas de teste. Nunca blocos incrementais.
4. **Versão sincronizada em 3 fontes** neste pacote: header `Version:` do plugin, constante `SIGE_VERSION` e `BUILD.json`. O `tools/build-release.php` actualiza as três e o CHANGELOG.
5. **Terminações de linha: LF** em todo o código (.php/.js/.css).
6. **Português pré-AO90 em todo o texto visível ao utilizador** (sector, actualizado, activo, seleccionar, acção, reactivação). Sem travessões como pontuação: usar dois pontos, vírgulas, parênteses ou hífenes.
7. **Dupla grafia na camada de dados é deliberada e intocável.** Consultas SQL e listas de valores aceitam `'activo'` e `'ativo'`, `'inactiva'` e `'inativa'`, etc., porque há dados históricos com ambas as grafias. Nomes de colunas (`ativo`), slugs (`atualizar_contacto`, `reativar_lancamento`, `atividade_extra`, `diretor_pedagogico`) e nomes de funções/acções existentes nunca mudam de grafia. A regra pré-AO90 aplica-se apenas a texto de interface.
8. **Texto de interface sem jargão técnico.** O utilizador da escola nunca deve ler nonce, schema, cron, backfill, pending, snapshot, engine, hotfix ou números de versão no meio das páginas. A versão vive no Estado do Sistema e no rodapé técnico, não na copy.

## Processo de release

```bash
# 1) Aplicar versão (actualiza header, SIGE_VERSION, BUILD.json, docs/changelog/ e CHANGELOG.md)
php tools/build-release.php 12.11.9.NN "Resumo curto da versão"

# 2) Lint integral
find . -name "*.php" -exec php -l {} \;

# 3) Smoke da release (conteúdo + invariantes)
php tools/smoke-release-gate.php

# 4) Compactar a pasta sige-softgenial/ como ZIP instalável
```

## Segurança

- Todas as views correm dentro do app shell com allowlist de rotas, matriz de permissões (`sige_can`) e guarda de página (`sige_page_guard`).
- Utilitários em `tools/` recusam execução via web (guarda CLI + `.htaccess`).
- Cada pasta contém `index.php` de silêncio contra listagem de directórios.
- A desinstalação não destrói dados; ver `uninstall.php`.
