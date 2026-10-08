# Passa

Implementação do desafio Full Stack da 3Pontos Tech.

O Passa representa um cartão corporativo pré-pago. A empresa mantém saldo disponível, os funcionários possuem cartões com limites e regras, e a rede envia autorizações, capturas e cancelamentos.

A solução foi desenvolvida com PHP 8.4, Laravel 13, FilamentPHP 5, PostgreSQL, Livewire, Blade, Tailwind e Pest.

As decisões de modelagem e comportamento do domínio estão documentadas em [`MODEL.md`](MODEL.md).

## Requisitos

Para executar o projeto localmente:

- PHP 8.4
- Composer
- Node.js / npm
- Docker
- Docker Compose

## Instalação

Suba os serviços de infraestrutura:

```bash
make env-up
```

Instale e configure o projeto:

```bash
composer setup
```

Caso prefira executar as etapas manualmente:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install
npm run build
```

## Executando a aplicação

Para iniciar a aplicação com servidor Laravel, filas, logs e Vite:

```bash
composer dev
```

A aplicação ficará disponível em:

```text
http://127.0.0.1:8000
```

## Painel administrativo

O painel financeiro utiliza Filament e está disponível em:

```text
http://127.0.0.1:8000/admin
```

Usuária autorizada:

```text
E-mail: marina@acme.test
Senha: password
```

Somente Marina possui acesso ao painel.

O painel permite consultar cartões, compras, transactions, autorizações, issues e eventos pendentes.

A única operação financeira de escrita disponível no painel é o registro de depósito para a empresa.

## Área do funcionário

A área dos portadores fica fora do Filament.

Login:

```text
http://127.0.0.1:8000/login
```

Cartão do funcionário:

```text
http://127.0.0.1:8000/my-card
```

Usuários disponíveis no seed:

```text
ana@acme.test
bruno@acme.test
carla@acme.test
diego@acme.test
```

Senha para todos:

```text
password
```

A tela `/my-card` exibe o cartão do usuário autenticado, disponível atual, limite mensal restante, statement do mês e histórico das compras.

Os dados dinâmicos são atualizados através de polling do Livewire a cada 5 segundos, sem recarregar a página inteira.

Um funcionário nunca escolhe o cartão que deseja visualizar. O cartão é obtido diretamente a partir do usuário autenticado.

Usuários sem cartão recebem `403`.

## API da rede

Os endpoints da rede ficam sob:

```text
/api/network
```

Principais endpoints:

```text
POST /api/network/authorizations
POST /api/network/events
GET  /api/network/cards/{card_token}/available
GET  /api/network/cards/{card_token}/statement
```

Todos os endpoints da rede utilizam autenticação HMAC.

A assinatura utiliza:

```text
<timestamp>.<raw_body>
```

com HMAC-SHA256 e a chave:

```text
NETWORK_SECRET
```

Headers esperados:

```text
X-Network-Timestamp
X-Network-Signature
```

## Banco de dados e seed

O projeto utiliza PostgreSQL.

Para recriar completamente o banco com os dados necessários:

```bash
php artisan migrate:fresh --seed
```

O seed cria a empresa Acme, o depósito inicial de R$ 10.000,00, Marina e os quatro portadores exigidos pelos cenários.

## Testes

Para executar todos os testes:

```bash
php artisan test
```

ou:

```bash
make test
```

A suíte cobre, entre outros pontos:

- autenticação HMAC;
- decisão de authorization;
- ordem dos motivos de recusa;
- idempotência;
- capture e cancellation;
- eventos recebidos antes da authorization;
- capture após cancellation;
- capture de authorization recusada;
- overcapture;
- saldo e reserva da empresa;
- limite mensal dos cartões;
- `/available`;
- statement e seu invariante;
- cenários publicados P1, P2 e P3;
- acesso ao painel;
- depósito administrativo;
- login e isolamento da área do funcionário.

## Qualidade

Para executar todas as verificações de qualidade:

```bash
make check
```

O comando executa as ferramentas configuradas no projeto, incluindo Pint, Larastan e Rector.

## Modelo financeiro

A solução mantém duas representações complementares do estado financeiro.

As projeções atuais permitem decisões rápidas de autorização:

```text
Company.balance_cents
Company.reserved_cents
CardMonthBalance.limit_remaining_cents
Purchase.reserved_amount_cents
```

As `transactions` preservam cada movimentação financeira e são utilizadas para construção dos statements.

Os valores monetários são sempre armazenados em centavos inteiros.

As decisões completas sobre reserva, captura, cancelamento, eventos fora de ordem, overcapture, mês da compra, IDs e idempotência estão documentadas em [`MODEL.md`](MODEL.md).

## Concorrência

As alterações financeiras são realizadas dentro de transações do banco de dados.

Onde o estado precisa ser protegido durante uma decisão financeira, são utilizados bloqueios com:

```php
lockForUpdate()
```

O objetivo é impedir perda de atualização e inconsistência entre limite do cartão, saldo e reserva da empresa quando mensagens são processadas simultaneamente.

## Suposições

O desafio trabalha apenas com a empresa Acme.

O identificador externo recebido da rede é preservado separadamente do ID interno do banco.

O mês da compra é definido pelo `occurred_at` da authorization convertido para `America/Sao_Paulo`.

Eventos podem chegar antes da authorization e são persistidos para reconciliação posterior.

Capturas válidas não são rejeitadas por ultrapassar o valor esperado. O movimento financeiro é processado e, quando aplicável, um issue é criado.

Uma cancellation sem reserva aberta é armazenada como fato, mas não cria transaction financeira.

Mais detalhes e alternativas descartadas estão em [`MODEL.md`](MODEL.md).

## Documentação adicional

[`MODEL.md`](MODEL.md) contém as decisões de domínio, riscos, garantias, cenários P2/P3 e alternativas descartadas.

[`DEVELOPMENT.md`](DEVELOPMENT.md) contém anotações sobre o processo de implementação e os principais ajustes realizados durante o desenvolvimento.
