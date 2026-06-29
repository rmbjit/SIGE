<?php
if (!defined('ABSPATH')) exit;

/**
 * SIGE SoftGenial v12.10.131 - Dica do Dia: Cultura de Gestão Escolar
 *
 * Objectivo:
 * - Mostrar uma dica curta e útil no primeiro acesso diário ao SIGE.
 * - Apenas para gestão administrativa da escola; nunca para aluno, encarregado, professor, educador ou director pedagógico.
 * - Uma vez por utilizador por dia.
 * - Conteúdo local no plugin, sem depender da internet.
 * - Não aparece durante primeiro acesso obrigatório/senha provisória.
 */

if (!function_exists('sige_dica_do_dia_tips_v131')) {
    function sige_dica_do_dia_tips_v131(): array {
        return [
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. A escola ganha confiança quando os seus documentos são consistentes.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. A rotina diária é a melhor amiga da escola organizada.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. A rotina diária é a melhor amiga da escola organizada.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. A escola ganha confiança quando os seus documentos são consistentes.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. A escola ganha confiança quando os seus documentos são consistentes.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. A rotina diária é a melhor amiga da escola organizada.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. A escola ganha confiança quando os seus documentos são consistentes.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. A rotina diária é a melhor amiga da escola organizada.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. A rotina diária é a melhor amiga da escola organizada.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. A escola ganha confiança quando os seus documentos são consistentes.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. A escola ganha confiança quando os seus documentos são consistentes.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. A rotina diária é a melhor amiga da escola organizada.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. A escola ganha confiança quando os seus documentos são consistentes.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. A rotina diária é a melhor amiga da escola organizada.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. A rotina diária é a melhor amiga da escola organizada.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. A escola ganha confiança quando os seus documentos são consistentes.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. A escola ganha confiança quando os seus documentos são consistentes.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. A rotina diária é a melhor amiga da escola organizada.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. A escola ganha confiança quando os seus documentos são consistentes.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. A rotina diária é a melhor amiga da escola organizada.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. A rotina diária é a melhor amiga da escola organizada.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. A escola ganha confiança quando os seus documentos são consistentes.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. A escola ganha confiança quando os seus documentos são consistentes.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. A rotina diária é a melhor amiga da escola organizada.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. A escola ganha confiança quando os seus documentos são consistentes.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. A rotina diária é a melhor amiga da escola organizada.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Antes de iniciar o trabalho, escolha uma pequena pendência e resolva-a completamente.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Registos incompletos enfraquecem decisões; dados limpos fortalecem a gestão.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. A rotina diária é a melhor amiga da escola organizada.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Evite decisões no escuro: consulte os dados antes de concluir.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. Melhore hoje um pequeno procedimento; a soma desses ajustes cria uma escola mais forte.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Antes de imprimir ou enviar qualquer documento, faça uma última revisão.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Escolha uma tarefa recorrente e veja se ela pode ser feita com mais simplicidade.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. A escola ganha confiança quando os seus documentos são consistentes.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Partilhe com a equipa uma orientação curta que evite dúvidas repetidas.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Confirme se os registos recentes estão completos, porque a organização começa nos detalhes.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Acompanhe o que está pendente e não espere o fim do mês para corrigir.',
        'Cada encarregado que procura a escola espera orientação clara, respeito e resposta consistente. Organize primeiro a informação essencial; depois a gestão torna-se mais leve.',
        'A qualidade da gestão escolar cresce quando a escola mantém os dados actualizados todos os dias. A tecnologia não substitui o cuidado, mas amplia o impacto de uma equipa organizada.',
        'Boa liderança escolar aparece nos detalhes: pontualidade, organização, escuta e acompanhamento. Olhe para os indicadores do dia e transforme um número em uma decisão prática.',
        'No jardim de infância, pequenos registos diários ajudam a compreender melhor cada criança. Use o sistema como fonte de verdade, não como arquivo incompleto.',
        'Dados dos alunos são informação sensível. Acesso deve ser dado apenas a quem realmente precisa. Se um erro apareceu uma vez, transforme-o em aprendizagem para não repetir.',
        'Uma rotina simples, repetida todos os dias, evita acumular problemas para o fim do mês. Use alguns minutos para corrigir inconsistências antes que se tornem problemas maiores.',
        'A melhor comunicação com famílias é clara, respeitosa e enviada no momento certo. Confirme se a informação comunicada às famílias está simples, completa e respeitosa.',
        'Um bom ano lectivo não se controla apenas no fim; acompanha-se semana a semana. Mantenha a escola preparada para responder com segurança quando os encarregados perguntarem.',
        'Quando cada pessoa sabe o seu papel, a escola trabalha com menos confusão e mais confiança. Reveja hoje um dado que costuma ser esquecido e transforme-o numa rotina da escola.',
        'O sistema ajuda mais quando os dados entram completos, correctos e no momento certo. Registe hoje com cuidado, para que amanhã a escola tenha confiança na informação.',
        'Uma escola organizada começa por decisões tomadas com dados reais, não apenas por memória ou pressão do momento. Se algo parece confuso para a equipa, provavelmente também será confuso para os encarregados.',
        'Dados bem registados hoje evitam retrabalho, conflitos e atrasos no fim do trimestre. A escola ganha confiança quando os seus documentos são consistentes.',
        'Cobrança eficiente não é pressão; é clareza, regularidade e comunicação atempada com os encarregados. Procure uma forma simples de tornar o atendimento de hoje mais claro e mais humano.',
        'Antes de fechar notas, confirme turma, disciplina e aluno. Pequenos erros podem gerar grandes problemas depois. Valorize o tempo da equipa: processos claros reduzem chamadas, dúvidas e retrabalho.',
        'Uma pauta bem emitida depende de registos completos, critérios claros e revisão cuidadosa antes da impressão. Uma boa gestão aparece quando todos sabem onde encontrar a informação certa.'
        ];
    }
}

if (!function_exists('sige_dica_do_dia_current_date_v131')) {
    function sige_dica_do_dia_current_date_v131(): string {
        if (function_exists('wp_date')) {
            return (string)wp_date('Y-m-d');
        }
        return date('Y-m-d');
    }
}

if (!function_exists('sige_dica_do_dia_pick_v131')) {
    function sige_dica_do_dia_pick_v131(): string {
        $tips = sige_dica_do_dia_tips_v131();
        if (empty($tips)) return '';

        $day_index = (int)(function_exists('wp_date') ? wp_date('z') : date('z'));
        $year = (int)(function_exists('wp_date') ? wp_date('Y') : date('Y'));

        // A rotação anual evita que a mesma sequência se repita exactamente todos os anos.
        $idx = abs(($day_index + ($year % max(1, count($tips)))) % count($tips));
        return (string)$tips[$idx];
    }
}

if (!function_exists('sige_dica_do_dia_is_forced_password_v131')) {
    function sige_dica_do_dia_is_forced_password_v131(int $user_id): bool {
        if ($user_id <= 0) return false;
        foreach ([
            'sige_portal_force_password_change',
            'sige_password_must_change',
            'sige_primeiro_acesso_pendente',
            'sige_forcar_troca_senha',
        ] as $key) {
            if ((int)get_user_meta($user_id, $key, true) === 1) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('sige_dica_do_dia_is_school_staff_v132')) {
    function sige_dica_do_dia_is_school_staff_v132(int $user_id): bool {
        if ($user_id <= 0) return false;

        $user = get_user_by('id', $user_id);
        $roles = ($user && !empty($user->roles)) ? array_map('sanitize_key', (array)$user->roles) : [];

        // v12.10.133 - regra mais restrita:
        // A Dica do Dia NÃO aparece para aluno, encarregado, professor,
        // educador nem director pedagógico/pedagógico.
        $blocked_roles = [
            'sige_aluno',
            'sige_encarregado',
            'sige_professor',
            'sige_educador',
            'sige_pedagogico',
            'aluno',
            'encarregado',
            'professor',
            'educador',
            'pedagogico',
            'director_pedagogico',
            'diretor_pedagogico',
            'director-pedagogico',
            'diretor-pedagogico',
        ];
        if (array_intersect($blocked_roles, $roles)) {
            return false;
        }

        if (function_exists('sige_permissions_get_active_role')) {
            $active_role = sige_permissions_get_active_role($user_id);
            if ($active_role && !empty($active_role->slug)) {
                $slug = sanitize_key((string)$active_role->slug);
                if (in_array($slug, [
                    'aluno',
                    'encarregado',
                    'professor',
                    'educador',
                    'pedagogico',
                    'director_pedagogico',
                    'diretor_pedagogico',
                    'director-pedagogico',
                    'diretor-pedagogico',
                ], true)) {
                    return false;
                }
            }
        }

        // Perfis autorizados nesta fase:
        // administração, direcção geral, secretaria e tesouraria/financeiro.
        if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return true;

        $allowed_caps = [
            'sige_director',
            'sige_secretario',
            'sige_tesouraria',
            'sige_financeiro',
            'sige_admin',
        ];
        foreach ($allowed_caps as $cap) {
            if (current_user_can($cap)) return true;
        }

        if (function_exists('sige_can')) {
            $allowed_permissions = [
                'config.ver',
                'config.editar',
                'alunos.ver',
                'financeiro.ver',
                'financeiro.pagar',
                'relatorios.ver',
            ];
            foreach ($allowed_permissions as $perm) {
                if (sige_can($perm)) return true;
            }
        }

        $allowed_roles = [
            'administrator',
            'sige_director',
            'sige_secretario',
            'sige_tesouraria',
            'sige_financeiro',
            'sige_admin',
            'director',
            'diretor',
            'secretario',
            'secretaria',
            'tesouraria',
            'financeiro',
            'admin',
        ];
        if (array_intersect($allowed_roles, $roles)) {
            return true;
        }

        if (function_exists('sige_permissions_get_active_role')) {
            $active_role = sige_permissions_get_active_role($user_id);
            if ($active_role && !empty($active_role->slug)) {
                $slug = sanitize_key((string)$active_role->slug);
                if (in_array($slug, [
                    'director',
                    'diretor',
                    'secretario',
                    'secretaria',
                    'tesouraria',
                    'financeiro',
                    'admin',
                ], true)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('sige_dica_do_dia_should_show_v131')) {
    function sige_dica_do_dia_should_show_v131(): bool {
        if (!is_admin() || !is_user_logged_in()) return false;
        if (defined('DOING_AJAX') && DOING_AJAX) return false;
        if (defined('REST_REQUEST') && REST_REQUEST) return false;

        $page = isset($_GET['page']) ? sanitize_key((string)$_GET['page']) : '';
        if ($page !== 'sige-app') return false;

        // Pode ser desligado por opção/filtro no futuro sem mexer no código.
        $enabled = get_option('sige_dica_do_dia_enabled', '1');
        if ((string)$enabled === '0') return false;

        // v12.10.134 - alinhamento com Hub/feature flags.
        // Se o Hub passar a controlar a feature dica_do_dia, esta área obedece.
        // Em Hub antigo, o default local mantém a dica activa para perfis permitidos.
        if (function_exists('sige_feature') && !sige_feature('dica_do_dia')) return false;

        if (!apply_filters('sige_dica_do_dia_enabled', true)) return false;

        $user_id = (int)get_current_user_id();
        if ($user_id <= 0) return false;

        // v12.10.133 - a Dica do Dia é apenas para gestão administrativa/operacional.
        // Não deve aparecer para aluno, encarregado, professor, educador ou director pedagógico.
        if (!sige_dica_do_dia_is_school_staff_v132($user_id)) return false;

        // Não competir com o ecrã obrigatório de primeiro acesso/senha provisória.
        if (sige_dica_do_dia_is_forced_password_v131($user_id)) return false;

        $today = sige_dica_do_dia_current_date_v131();
        $seen = (string)get_user_meta($user_id, 'sige_dica_do_dia_seen_date', true);
        return $seen !== $today;
    }
}

if (!function_exists('sige_dica_do_dia_render_v131')) {
    function sige_dica_do_dia_render_v131(): void {
        if (!sige_dica_do_dia_should_show_v131()) return;

        $tip = sige_dica_do_dia_pick_v131();
        if ($tip === '') return;

        $nonce = wp_create_nonce('sige_dica_do_dia_dismiss');
        $ajax_url = admin_url('admin-ajax.php');
        ?>
        <style id="sige-dica-do-dia-v1210131">
        .sg-dica-dia-overlay{
            position:fixed;
            inset:0;
            z-index:999999;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:22px;
            background:rgba(11,20,42,.34);
            backdrop-filter:blur(7px);
            -webkit-backdrop-filter:blur(7px);
        }
        .sg-dica-dia-card{
            width:min(620px,100%);
            border-radius:28px;
            background:linear-gradient(135deg,#ffffff 0%,#ffffff 58%,#eef6ff 100%);
            border:1px solid rgba(92,64,187,.14);
            box-shadow:0 28px 90px rgba(15,23,42,.24);
            overflow:hidden;
            font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,sans-serif);
            color:#17172f;
            position:relative;
        }
        .sg-dica-dia-card:before{
            content:"";
            position:absolute;
            right:-80px;
            top:-90px;
            width:220px;
            height:220px;
            border-radius:var(--radius-pill);
            background:rgba(90,63,214,.12);
            pointer-events:none;
        }
        .sg-dica-dia-body{
            position:relative;
            padding:28px;
            display:grid;
            grid-template-columns:auto minmax(0,1fr);
            gap:18px;
            align-items:flex-start;
        }
        .sg-dica-dia-icon{
            width:58px;
            height:58px;
            border-radius:20px;
            background:#eef2ff;
            color:var(--sg-theme-primary,#5a3fd6);
            display:flex;
            align-items:center;
            justify-content:center;
            box-shadow:0 14px 34px rgba(11,74,143,.12);
        }
        .sg-dica-dia-icon svg{
            width:26px;
            height:26px;
            display:block;
        }
        .sg-dica-dia-kicker{
            display:flex;
            align-items:center;
            gap:var(--space-2);
            color:var(--sg-theme-primary,#5a3fd6);
            font-size:12px;
            font-weight:950;
            letter-spacing:.13em;
            text-transform:uppercase;
            margin:0 0 9px;
        }
        .sg-dica-dia-title{
            margin:0 0 10px;
            font-size:26px;
            line-height:1.1;
            font-weight:950;
            letter-spacing:-.04em;
            color:#17172f;
        }
        .sg-dica-dia-text{
            margin:0;
            color:#5d6578;
            font-size:15px;
            line-height:1.65;
            font-weight:720;
        }
        .sg-dica-dia-actions{
            display:flex;
            justify-content:flex-end;
            gap:10px;
            padding:0 28px 26px;
            position:relative;
        }
        .sg-dica-dia-btn{
            min-height:44px;
            padding:0 var(--space-5);
            border-radius:15px;
            border:1px solid var(--sg-theme-primary,#5a3fd6);
            background:linear-gradient(135deg,var(--sg-theme-primary,#5a3fd6),var(--sg-theme-primary-800,#3b2f8d));
            color:#fff;
            font-size:var(--fs-sm);
            font-weight:950;
            cursor:pointer;
            box-shadow:0 14px 30px rgba(11,74,143,.20);
        }
        .sg-dica-dia-btn:hover{
            transform:translateY(-1px);
            box-shadow:0 17px 36px rgba(11,74,143,.25);
        }
        @media(max-width:640px){
            .sg-dica-dia-overlay{
                align-items:flex-end;
                padding:14px;
            }
            .sg-dica-dia-card{
                border-radius:24px;
            }
            .sg-dica-dia-body{
                grid-template-columns:1fr;
                gap:14px;
                padding:var(--space-6);
            }
            .sg-dica-dia-icon{
                width:54px;
                height:54px;
            }
            .sg-dica-dia-title{
                font-size:22px;
            }
            .sg-dica-dia-text{
                font-size:var(--fs-base);
            }
            .sg-dica-dia-actions{
                padding:0 var(--space-6) var(--space-6);
            }
            .sg-dica-dia-btn{
                width:100%;
            }
        }
        </style>

        <div class="sg-dica-dia-overlay" id="sgDicaDiaOverlay" role="dialog" aria-modal="true" aria-labelledby="sgDicaDiaTitle">
            <div class="sg-dica-dia-card">
                <div class="sg-dica-dia-body">
                    <div class="sg-dica-dia-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2a7 7 0 0 0-4 12.74V17a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-2.26A7 7 0 0 0 12 2Z"/>
                            <path d="M9 21h6"/>
                            <path d="M10 17h4"/>
                        </svg>
                    </div>
                    <div>
                        <div class="sg-dica-dia-kicker">SoftGenial</div>
                        <h2 class="sg-dica-dia-title" id="sgDicaDiaTitle">Dica do dia</h2>
                        <p class="sg-dica-dia-text"><?php echo esc_html($tip); ?></p>
                    </div>
                </div>
                <div class="sg-dica-dia-actions">
                    <button type="button" class="sg-dica-dia-btn" id="sgDicaDiaClose">Entendi</button>
                </div>
            </div>
        </div>

        <script <?php echo sige_csp_script_attr(); ?>>
        (function(){
            var overlay = document.getElementById('sgDicaDiaOverlay');
            var btn = document.getElementById('sgDicaDiaClose');
            if (!overlay || !btn) return;

            function closeDicaDia(){
                overlay.style.opacity = '0';
                overlay.style.pointerEvents = 'none';
                setTimeout(function(){
                    if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
                }, 180);
            }

            btn.addEventListener('click', function(){
                btn.disabled = true;
                btn.textContent = 'Guardado...';

                var data = new FormData();
                data.append('action', 'sige_dica_do_dia_dismiss');
                data.append('nonce', <?php echo wp_json_encode($nonce); ?>);

                fetch(<?php echo wp_json_encode($ajax_url); ?>, {
                    method:'POST',
                    credentials:'same-origin',
                    body:data
                }).catch(function(){}).finally(function(){
                    closeDicaDia();
                });
            });

            document.addEventListener('keydown', function(e){
                if (e.key === 'Escape') {
                    btn.click();
                }
            });
        })();
        </script>
        <?php
    }
}
add_action('admin_footer', 'sige_dica_do_dia_render_v131', 30);

if (!function_exists('sige_dica_do_dia_dismiss_v131')) {
    function sige_dica_do_dia_dismiss_v131(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'not_logged_in'], 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string)$_POST['nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_dica_do_dia_dismiss')) {
            wp_send_json_error(['message' => 'invalid_nonce'], 403);
        }

        update_user_meta((int)get_current_user_id(), 'sige_dica_do_dia_seen_date', sige_dica_do_dia_current_date_v131());
        update_user_meta((int)get_current_user_id(), 'sige_dica_do_dia_seen_at', current_time('mysql'));
        update_user_meta((int)get_current_user_id(), 'sige_dica_do_dia_seen_version', '12.10.131');

        wp_send_json_success(['ok' => true]);
    }
}
add_action('wp_ajax_sige_dica_do_dia_dismiss', 'sige_dica_do_dia_dismiss_v131');
