# TESTES LIVE - v12.11.9.103 (Sprint UX-8: vassoura dos diálogos)

Fazer em teste.softgenial.edu.mz. Tempo total: ~9 minutos.
F12 > Console aberto: zero linhas vermelhas. Em NENHUM destes passos
deve aparecer a caixa cinzenta do browser.

## 1. Confirmações destrutivas com modal do SIGE (4 min)
1. Disciplinas > apagar uma disciplina de teste: modal "Remover
   disciplina" com o NOME e a lista de impactos; Cancelar não apaga;
   confirmar apaga como antes;
2. Transporte > remover uma rota de teste: modal VERMELHO "Remover
   rota"; Cancelar não remove; confirmar remove (a rota desaparece);
3. Permissões > num utilizador com perfil SIGE, "Remover": modal do
   SIGE; cancelar mantém o perfil;
4. Aprovar Notas > "Rejeitar" numa selecção: modal de confirmação.

## 2. Acções de ano e fecho de caixa (3 min)
1. Abertura > "Executar Abertura Oficial": modal do SIGE com as
   consequências por extenso; Esc/Cancelar NÃO abre o ano;
   (só confirmar se for ano de teste descartável);
2. Tesouraria/Extractos > fechar caixa com divergência ou sem
   checklist: TOAST de aviso (não caixa do browser).

## 3. Exportações e impressões (2 min)
1. Pauta Final / DEC / Boletim: com popups bloqueados, imprimir ->
   toast com instrução; exportar sem dados -> toast de aviso;
2. Jardim > boletins: imprimir sem selecção -> toast.

## Critério de aprovação
Nenhuma caixa cinzenta do browser em todo o teste + console limpo =
v103 validada. A categoria "diálogo nativo" está oficialmente fechada.
