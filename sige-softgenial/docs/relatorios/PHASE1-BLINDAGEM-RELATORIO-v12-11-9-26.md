# SIGE SoftGenial v12.11.9.26 - Relatório Hotfix Fase 1

Build: `sige-12.11.9.26-phase1-hotfix-capability-recursion`  
Canal: `test`  
Data: 2026-05-31

## Motivo do hotfix

Após instalação da v12.11.9.25, foi identificado erro fatal por recursão infinita:

```text
Maximum call stack size ... Infinite recursion?
... WP_User->has_cap()
... is_super_admin()
... sige_is_real_wp_admin_user()
... sige_security_strip_technical_caps_from_sige_users()
```

A causa foi técnica e localizada: o filtro `user_has_cap`, criado para impedir que roles SIGE legadas herdassem capacidades técnicas do WordPress, chamava `sige_is_real_wp_admin_user()`. Essa função chamava `is_super_admin()`. Em WordPress, `is_super_admin()` pode consultar capabilities; isso reentra em `user_has_cap`, criando loop infinito.

## O que foi corrigido

### 1. Remoção da chamada recursiva a `is_super_admin()`

Ficheiro:

```text
includes/security-hardening.php
```

Foi criada a função:

```php
sige_is_real_wp_admin_user_raw()
```

Ela identifica administrador WordPress real sem chamar:

```php
is_super_admin()
current_user_can()
user_can()
```

A verificação passa a ser feita por:

- role nativa `administrator`;
- role `super_admin`, quando existente;
- em multisite, login presente em `get_super_admins()`, sem chamar `is_super_admin()`.

### 2. Guarda anti-recursão no filtro `user_has_cap`

O filtro:

```php
sige_security_strip_technical_caps_from_sige_users()
```

passa a ter protecção estática contra reentrada. Se o filtro for chamado novamente enquanto já está a correr, ele devolve o estado actual das capabilities e evita loop fatal.

### 3. Uso do objecto `WP_User` recebido pelo WordPress

Dentro do filtro `user_has_cap`, a validação agora usa o objecto `$user` já fornecido pelo WordPress, evitando novas consultas desnecessárias.

### 4. Ajuste nos helpers de permissões

Ficheiros:

```text
includes/page-guard.php
includes/permissions-layer.php
```

Os helpers internos passam a reutilizar `sige_is_real_wp_admin_user()` quando disponível, garantindo a mesma verificação segura.

## O que permanece igual

Este hotfix não altera:

- fórmulas financeiras;
- fórmulas académicas;
- notas;
- pautas;
- boletins;
- mensalidades;
- multas;
- descontos;
- geração/cálculo de recibos;
- regras de negócio já aplicadas na v12.11.9.25.

## Estado da Fase 1

A v12.11.9.26 substitui a v12.11.9.25 como candidato final da Fase 1, porque corrige um bug crítico de execução sem remover as blindagens já feitas.

Para considerar a Fase 1 operacionalmente fechada no teu ambiente mono-escola, validar:

```text
1. WordPress admin abre sem fatal error.
2. Plugin activa normalmente.
3. Director entra no SoftGenial, mas não vira administrador técnico do WordPress.
4. Recibos WhatsApp continuam a abrir sem login.
5. Token de recibo alterado falha.
6. Documentos do aluno continuam protegidos.
7. Pagamentos, notas, pautas e boletins continuam normais.
8. Restauro de configuração cria snapshot.
```
