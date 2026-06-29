# TESTES LIVE - v12.11.9.147 (CORRECÇÃO CRÍTICA: entidades HTML)

Fazer em teste.softgenial.edu.mz. Tempo: ~4 minutos.
Correcção de bug VISÍVEL. Console: zero vermelhos.

## 1. financeiro-config - texto correcto (1 min)
1. Os títulos "Multa por Atraso (Escalada)" e "Descontos Automáticos"
   aparecem CORRECTOS (sem "&var(--color-...)");
2. Os emojis aparecem (🚀, família 👨‍👩‍👧, 📍, etc.);
3. Os acentos (Localização, Configuração) correctos.

## 2. financeiro-pagamentos - texto correcto (1 min)
1. Os textos com acento (Serviço, Método, Múltiplos, Família) correctos;
2. Os emojis (💡, 📚, ⚠) aparecem;
3. As caixas de selecção (☑/☐) aparecem.

## 3. Funcionalidade CRÍTICA (2 min)
1. config: alterar e gravar um parâmetro de teste - funciona;
2. pagamentos: processar um pagamento de teste - funciona, valor certo;
3. Imprimir um recibo - sai correcto.

## Critério de aprovação
Sem texto "&var(...)" em lado nenhum, emojis e acentos correctos,
gravação e pagamento funcionam, console limpo = v147 validada.
