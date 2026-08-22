# Plano — Auth backend (Login, Cadastro, Reset de senha)

Status: PLANEJAMENTO, nada implementado ainda. Laravel 13 + Sanctum (API token, sem cookie/sessão stateful).

## 1. O que existe hoje no front (ERP-front)

Fluxo 100% fake, tudo local em IndexedDB, sem HTTP.

- **Modelo de usuário** (`core/interfaces/user.interface.ts`):
  ```ts
  interface User { name: string; email: string; password: string; phone?: string; farm?: string; }
  ```
  `phone` e `farm` existem no tipo mas **não são preenchidos em lugar nenhum** (nem no form de cadastro, nem na tela `platform/users`). Não existe campo `role`/papel em nenhum lugar do front hoje — apesar do pedido inicial mencionar "papel/role", ele não existe. Ver decisão D1 abaixo.

- **`UsersService`** (`core/services/users.service.ts`): `register()` (rejeita e-mail duplicado, salva senha em texto puro no IDB) e `validate(email, senha)` (busca linear comparando senha em texto puro).

- **`AuthSession`** (`core/services/auth-session.service.ts`): signal `user` populado a partir do `localStorage` (`erp-current-user`), sem token, sem expiração. `setCurrent()` grava o usuário completo (senha inclusa) no localStorage. `clear()` remove.

- **Telas** (`pages/auth/*`):
  - `login`: form e-mail/senha → `UsersService.validate()` → `AuthSession.setCurrent()` → navega `/platform/dashboard`.
  - `register`: nome/e-mail/senha/confirmar senha (mín. 6 chars) → `UsersService.register()` → navega `/login`.
  - `forgot-password`: wizard de 3 passos com **`p-inputotp` de 6 dígitos** — (1) e-mail, (2) digitar código de 6 dígitos, (3) nova senha + confirmação. Os 3 passos hoje são só `TODO`, nada chama backend.

- **Proteção de rota**: **não existe guard nem interceptor HTTP** no front hoje (`grep` não achou `CanActivate`/`HttpInterceptor`). `platform.routes.ts` não tem `canActivate`. Ou seja, hoje qualquer um acessa `/platform/*` direto pela URL — `AuthSession` só é usado pra exibir nome/logout na sidebar. Isso é uma lacuna do front que fica fora do escopo deste plano (é plano de *backend*), mas registro aqui porque a integração real vai exigir um `authGuard` + `HttpInterceptor` de Authorization no front depois que o backend existir — trago como aviso, não como tarefa deste plano.

## 2. O que já existe no ERP-Backend (scaffold)

- Laravel 13.17, Sanctum 4.0 já instalado, `personal_access_tokens` já migrado.
- `bootstrap/app.php` já configurado API-only: `redirectGuestsTo(fn () => null)` (401 JSON em vez de redirect) + `shouldRenderJsonWhen` pra `api/*`.
- `app/Models/User.php` já usa os **atributos PHP do Laravel 13** (`#[Fillable]`, `#[Hidden]`) em vez de propriedades `$fillable`/`$hidden` — seguir esse padrão nos próximos models.
- Migration padrão `users` (name/email/password/remember_token) + `password_reset_tokens` (email PK, token, created_at) — formato nativo do `Illuminate\Auth\Passwords\PasswordBroker`.
- Pastas já criadas (vazias, só `.gitkeep`): `app/Http/Controllers/Api`, `app/Http/Requests`, `app/Http/Resources`, `app/Services` → confirma a camada de serviço esperada.
- `.env`: `DB_CONNECTION=sqlite`, `MAIL_MAILER=log` (já serve pra dev).
- Sem Pest — projeto usa PHPUnit puro (`phpunit/phpunit`, sem `pestphp/*`).

## 3. Decisões que preciso da sua confirmação antes de codar

**D1 — Campo `role`/papel: criar agora ou não?**
Não existe no front hoje. Opções:
- (a) Não criar agora — só `name`/`email`/`password`. Adiciona depois quando o front tiver RBAC de verdade.
- (b) Criar coluna `role` (enum/string, default `'user'`) já agora, sem expor no front ainda, pra não precisar de migration extra depois.
Minha recomendação: (b) — custo baixo agora, evita migration extra e retrabalho no model/resource quando o RBAC chegar (a tela `/platform/users` já existe no front, então parece que RBAC vem em breve).

**D2 — Campos `phone` e `farm`: entram no cadastro agora?**
Existem no tipo do front mas não são usados em nenhum form. Recomendo **não** incluir na migration/registro agora (YAGNI) — adiciono quando o front realmente pedir esses campos no formulário.

**D3 — Reset de senha: fluxo de link (nativo do Laravel) vs. código OTP de 6 dígitos.**
Este é o ponto mais importante. O front já foi construído esperando um **código numérico de 6 dígitos digitado manualmente** (`p-inputotp`, `tokenValido = token.length === 6`), em 3 passos: pedir e-mail → digitar código → nova senha. Isso **não é** o que o `Illuminate\Auth\Passwords\PasswordBroker` nativo faz: ele gera um token de 60 caracteres pensado pra ir dentro de um link de e-mail (`Str::random(60)`, hasheado com bcrypt), e `Password::reset()` já junta "validar token + trocar senha" numa única chamada — não dá pra separar em "validar" e depois "trocar" como o front faz no passo 2/3.

Duas opções:
- **(a) Adaptar o broker nativo**: gerar o token nativo, mas mostrar só os 6 primeiros dígitos numéricos extraídos dele (gambiarra, token real fica maior, e-mail mostraria só parte) — não recomendo, foge do desenho do Laravel e complica sem necessidade.
- **(b) Implementar um `PasswordResetService` próprio, reaproveitando a tabela `password_reset_tokens`** (mesma estrutura: `email`, `token`, `created_at`) mas com token curto: `random_int(100000, 999999)`, guardado com `Hash::make()` (nunca em texto puro), expiração própria (ex.: 15 min), rate limit de reenvio. Endpoints separados pra "pedir código", "validar código" (sem consumir, só pra UX do passo 2→3) e "confirmar reset" (valida de novo + consome + troca senha). **Esta é minha recomendação** — bate com o desenho do front sem forçar o front a mudar.

Preciso que você confirme (b), ou me diga se prefere mudar o front pra usar link de reset em vez de código de 6 dígitos (aí sim usaríamos o broker nativo sem gambiarra).

**D4 — Confirmação de e-mail no cadastro: obrigatória ou não?**
Front cadastra e já navega pra `/login` sem etapa de confirmação. Recomendo: **login liberado imediatamente após cadastro**, sem exigir verificação de e-mail nesta fase (mantém paridade com o comportamento atual do front). `email_verified_at` fica no schema (já vem no scaffold) mas não bloqueia login — dá pra ligar `MustVerifyEmail` depois sem migration nova.

**D5 — Sanctum: token de API simples (Bearer), sem cookie de sessão** — já decidido antes, só confirmando que mantenho: `createToken()` no login, cliente manda `Authorization: Bearer <token>`, sem `EnsureFrontendRequestsAreStateful` / cookie SPA.

## 3.1 IMPLEMENTADO — Access token de 2h + refresh token (rotação)

Decisão confirmada pelo usuário em 22/08/2026. Sanctum não tem refresh nativo, então o desenho ficou:

- **Login/registro/refresh emitem SEMPRE um PAR de tokens**, ligados por um id de sessão (`uuid`) embutido no `name` de cada `PersonalAccessToken`: `access:{uuid}` (ability `access`, expira em **2h**) e `refresh:{uuid}` (ability `refresh`, expira em **30 dias**). 30 dias porque é o padrão de mercado pra "lembrar login" em app interno de granja (baixo risco, poucos usuários) sem forçar login toda semana; ajusta fácil (`TokenService::REFRESH_TTL_DAYS`) se quiser mais curto.
- **`config('sanctum.expiration')` foi mantido `null`** (não setei os 120 minutos ali) — de propósito. O comentário do próprio arquivo `config/sanctum.php` explica: esse valor vira um teto de idade que TODOS os tokens têm que respeitar (é checado em paralelo ao `expires_at` de cada token, não é substituído por ele). Se eu tivesse posto 120 ali, o refresh token de 30 dias teria sido invalidado em 2h também. Os 2h do access e os 30 dias do refresh são garantidos via `expiresAt` explícito por token (`$user->createToken($name, $abilities, $expiresAt)`, suportado nativamente pelo Sanctum), o que cumpre o requisito sem esse efeito colateral.
- **Rotas normais da API exigem ability `access`** (`middleware(['auth:sanctum', 'abilities:access'])`) — um refresh token vazado não serve pra nada além de bater no `/api/refresh`.
- **`POST /api/refresh`** exige ability `refresh`. Revoga o par atual (`TokenService::rotate()`) e emite um par novo — o refresh token antigo nunca pode ser reusado (rotação, testado em `RefreshTokenTest`).
- **Logout** (`POST /api/logout`, ability `access`) revoga o par inteiro (access + refresh) daquela sessão, não só o token usado na chamada.
- **Reset de senha** revoga TODAS as sessões do usuário (todos os pares, todos os dispositivos) — `TokenService::revokeAllSessionsFor()`.
- Aliases de middleware `abilities`/`ability` (`Laravel\Sanctum\Http\Middleware\CheckAbilities`/`CheckForAnyAbility`) precisaram ser registrados manualmente em `bootstrap/app.php` — o Sanctum não os registra sozinho.

Resposta do login/registro/refresh:
```json
{
  "user": { "id": 1, "name": "...", "email": "...", "role": "ADMINISTRADOR", "created_at": "..." },
  "token_type": "Bearer",
  "access_token": "1|xxxxx",
  "access_token_expires_at": "2026-08-22T18:00:00.000000Z",
  "refresh_token": "2|yyyyy",
  "refresh_token_expires_at": "2026-09-21T16:00:00.000000Z"
}
```

Front, quando um request vier 401 com access token expirado: chamar `/api/refresh` com o refresh token (header `Authorization: Bearer <refresh_token>`), guardar o par novo, repetir a chamada original. Se `/api/refresh` também vier 401/403 (refresh expirado/revogado), aí sim manda pro login.

## 4. Migrations

### 4.1 `users` — ajustar a migration padrão (ainda não rodou migrate, então dá pra editar o arquivo existente em vez de criar uma nova)
Campos: `id`, `name`, `email` (unique), `email_verified_at` (nullable, já existe), `password`, `role` (string, default `'user'` — se D1 = b), `remember_token`, timestamps.
Sem `phone`/`farm` (D2).

### 4.2 `password_reset_tokens` — manter estrutura, mas repensar uso
Se D3 = (b): mantém `email` (index, não precisa ser PK — pode ter mais de uma solicitação pendente se quiser invalidar a anterior a cada novo pedido) + `token` (hash do código de 6 dígitos) + `created_at`. Pode adicionar `expires_at` explícito em vez de calcular a partir de `created_at` + config, pra deixar a expiração explícita na query.

### 4.3 `personal_access_tokens`
Já existe (Sanctum). Nenhuma mudança.

## 5. Estrutura de código (Service layer, sem lógica em Controller)

```
app/Http/Controllers/Api/Auth/
  LoginController.php          (store)
  RegisterController.php       (store)
  PasswordResetController.php  (requestCode, verifyCode, reset)
  LogoutController.php         (store) — revoga o token atual

app/Http/Requests/Auth/
  LoginRequest.php
  RegisterRequest.php
  RequestPasswordResetRequest.php   (email)
  VerifyPasswordResetCodeRequest.php (email, code)
  ResetPasswordRequest.php          (email, code, password, password_confirmation)

app/Http/Resources/
  UserResource.php             (id, name, email, role, created_at — nunca password)
  AuthResource.php (opcional) — envelope { user: UserResource, token }

app/Services/Auth/
  AuthService.php               (attempt login, gera token via Sanctum)
  RegistrationService.php       (cria usuário, role default)
  PasswordResetService.php      (gera código, valida, consome, troca senha)

app/Notifications/
  PasswordResetCodeNotification.php (Notification via Mail, usa MAIL_MAILER=log em dev)
```

Controllers ficam finos: validam via Form Request (`->validated()`), chamam o Service, devolvem Resource. Toda regra (hash de senha, geração/validação de código, revogação de tokens antigos) mora no Service.

## 6. Endpoints

Prefixo `routes/api.php` (ou `routes/api/auth.php` incluído em `api.php`, se preferir separar):

| Método | Rota | Middleware | Descrição |
|---|---|---|---|
| POST | `/api/register` | `guest` (via lógica no Service, não middleware `guest` clássico — API não tem sessão) | Cria usuário, retorna user + token (login automático, D4) |
| POST | `/api/login` | throttle (`throttle:6,1` por IP+email) | Valida credenciais, gera token Sanctum |
| POST | `/api/logout` | `auth:sanctum` | Revoga o token atual (`$request->user()->currentAccessToken()->delete()`) |
| GET | `/api/user` | `auth:sanctum` | Já existe no scaffold — devolver via `UserResource` em vez do model cru |
| POST | `/api/password/forgot` | throttle (`throttle:3,1`) | Gera código de 6 dígitos, dispara e-mail (Notification) |
| POST | `/api/password/verify-code` | throttle | Confere código sem consumir (UX do passo 2 do front) |
| POST | `/api/password/reset` | throttle | Confere código de novo + consome + troca senha + revoga tokens Sanctum antigos do usuário |

### Login
- `LoginRequest`: `email` (required, email), `password` (required, string).
- `AuthService::attempt(email, password)`: busca por e-mail, `Hash::check()`. Erro genérico "E-mail ou senha inválidos" pros dois casos (não revelar se e-mail existe). 422 com essa mensagem no campo `email` (padrão Laravel de `ValidationException`), ou 401 puro — recomendo seguir o padrão Laravel de `ValidationException::withMessages(['email' => [...]])` pra já sair no formato de erro que o Angular vai esperar (422 + `errors.email[]`), igual ao resto da validação.
- Sucesso: `201`/`200` com `{ user: UserResource, token: string }`. Token via `$user->createToken('api')->plainTextToken`.
- "Conta inativa": não existe esse conceito no front hoje (não há status de usuário). Não implemento agora — se quiser, é uma coluna `is_active`/`suspended_at` a mais, mas não tem pedido nenhum do front pra isso; fica de fora salvo confirmação sua.

### Cadastro
- `RegisterRequest`: `name` (required, string, max 255), `email` (required, email, unique:users), `password` (required, `Password::min(6)` — o front já exige mín. 6 no cliente, mantenho paridade; posso subir pra regra mais forte do Laravel `Password::min(8)->mixedCase()->numbers()` se você quiser, mas aí o front precisa mudar a validação também — pergunto antes de divergir do front), `password_confirmation` (required, same:password — o front já teria mandado `confirmarSenha`, formalizo como `password_confirmation` no padrão Laravel).
- `RegistrationService::register(data)`: cria com `role = 'user'` (default, D1), hash automático via `casts()` (`'password' => 'hashed'`, já no model). Devolve user + token (login automático).

### Reset de senha (fluxo D3-b)
1. `POST /password/forgot` — `RequestPasswordResetRequest` (`email`, required, exists:users — mas resposta **não deve revelar** se o e-mail existe ou não, pra evitar user enumeration; sempre 200 genérico "Se o e-mail existir, enviamos um código", mesmo que internamente o Service só crie/envie o código se o usuário existir). `PasswordResetService::requestCode(email)`: apaga códigos antigos daquele e-mail, gera `random_int(100000,999999)`, salva hash + `created_at`, dispara `PasswordResetCodeNotification` (Notification, canal `mail`, usa a Model `User` diretamente com `notify()`).
2. `POST /password/verify-code` — `email`, `code`. `PasswordResetService::verifyCode()`: confere hash + expiração (ex. 15 min), **não apaga** o registro (só valida, pro front avançar de step 2→3). Resposta booleana/erro 422 "Código inválido ou expirado".
3. `POST /password/reset` — `email`, `code`, `password`, `password_confirmation`. `PasswordResetService::reset()`: valida de novo (hash + expiração), troca a senha (`Hash::make`), **apaga o registro de `password_reset_tokens`**, **revoga todos os tokens Sanctum existentes daquele usuário** (`$user->tokens()->delete()` — força novo login em todos os dispositivos, boa prática depois de reset de senha). 422 se código inválido/expirado.

Rate limit em todos os 3 (`throttle` middleware ou `RateLimiter::for()` custom) pra não virar oráculo de força-bruta de código de 6 dígitos (1 milhão de combinações é pouco — throttle é obrigatório aqui, não opcional).

## 7. E-mail (dev vs produção)

- **Dev**: `MAIL_MAILER=log` já está configurado — o e-mail cai no `storage/logs/laravel.log`, dá pra copiar o código de lá manualmente pra testar o fluxo sem SMTP real. Não precisa mudar nada agora.
- Alternativa melhor pra "ver" o e-mail formatado durante o dev: Mailtrap ou `MAIL_MAILER=smtp` apontando pro Mailpit (se tiver Docker/Sail rodando localmente com Mailpit, senão fica só `log` mesmo — não vou adicionar infra nova sem você pedir).
- **Produção**: trocar `MAIL_MAILER` pra `ses`/`postmark`/`resend`/smtp real + credenciais via `.env` de produção. Nenhuma mudança de código — a `Notification` já é agnóstica de driver.
- A `PasswordResetCodeNotification` deve ser uma `Notification` (não um `Mailable` solto), assim fica fácil no futuro adicionar canal `sms` ou `broadcast` sem tocar no Service.

## 8. Testes (a incluir no plano de execução, TDD)

- `tests/Feature/Auth/LoginTest.php`: sucesso (200 + token), credenciais inválidas (422/401), rate limit.
- `tests/Feature/Auth/RegisterTest.php`: sucesso, e-mail duplicado, senha fraca/não confere.
- `tests/Feature/Auth/PasswordResetTest.php`: fluxo completo (forgot → verify-code → reset), código errado, código expirado, revogação de tokens após reset.
- `database/factories/UserFactory.php`: já vem no scaffold padrão do Laravel, ajustar se `role` for adicionado (D1).

## 9. Ordem de execução sugerida (pra quando formos codar)

1. Migration `users` (+ `role` se D1=b) e `password_reset_tokens` ajustada.
2. `UserFactory` + seeder de dev (opcional).
3. `RegisterRequest` + `RegistrationService` + `RegisterController` + teste.
4. `LoginRequest` + `AuthService` + `LoginController` + `LogoutController` + teste.
5. `UserResource`, ajustar `GET /api/user`.
6. `PasswordResetCodeNotification` + `PasswordResetService` + os 3 endpoints de reset + testes.
7. Rate limiting (`RateLimiter::for()` em `AppServiceProvider` ou `bootstrap/app.php`).
8. Rodar Pint + `php artisan test`.

## 10. Fora de escopo deste plano (registrado, não esquecido)

- Guard de rota (`canActivate`) e `HttpInterceptor` de `Authorization: Bearer` no Angular — front hoje não tem nenhum dos dois; entra num plano de integração front separado, depois que os endpoints existirem.
- RBAC de verdade (permissões por `role`) — só a coluna fica pronta (D1), a lógica de autorização vem depois.
- "Conta inativa/suspensa" — sem pedido concreto do front, não modelo agora.
