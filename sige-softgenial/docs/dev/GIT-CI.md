# Git + CI - Primeiro arranque (10 minutos)

## 1. Criar o repositório privado
GitHub > New repository > `sige-softgenial` > Private.

## 2. Primeiro push (a partir da pasta do plugin)
```bash
git init -b main
git add -A
git commit -m "v12.11.9.90 - base validada"
git remote add origin git@github.com:TEU-USER/sige-softgenial.git
git push -u origin main
```
O CI corre sozinho: lint em PHP 8.1 e 8.3 + os três gates vivos.

## 3. Fluxo de release a partir de agora
```bash
php tools/build-release.php 12.11.9.91 "Resumo"
git add -A && git commit -m "v12.11.9.91 - Resumo"
git tag v12.11.9.91 && git push && git push --tags
```
O workflow valida que a tag bate certo com o header e publica o ZIP
instalável como artefacto da execução (Actions > run > Artifacts).
O ZIP que vai para as escolas passa a nascer SEMPRE do CI verde.

## 4. Regra de ouro
Nunca editar directamente no servidor. Servidor recebe ZIPs do CI; o código
vive no Git. Hotfix urgente = branch, commit, tag, ZIP do CI, deploy.
