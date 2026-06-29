# SIGE SoftGenial v12.11.0 - Curriculum Engine Foundation PRO (TEST)

## Instalação recomendada
Instalar primeiro no ambiente de testes. Não instalar em produção antes de validação funcional.

## Depois de instalar
1. Confirmar que a versão aparece como 12.11.0.
2. Abrir: `admin.php?page=sige-app&view=curriculos`.
3. Confirmar que o perfil activo é "Moçambique / SNE".
4. Clicar em "Sincronizar matriz actual".
5. Confirmar os contadores de perfis, classes e disciplinas mapeadas.
6. Testar módulos existentes: Matriz Curricular, Notas, Pautas, DEC, Boletins, Pagamentos.

## Importante
Esta versão cria fundação multicurrículo, mas mantém o núcleo académico em modo `legacy_safe`. A selecção de Cambridge/Angola/Brasil ainda não altera cálculos nem documentos.

## Rollback
Por ser uma versão fundacional, o rollback para a v12.10.142 não deve apagar dados existentes. As novas tabelas podem ficar sem uso até a próxima fase.
