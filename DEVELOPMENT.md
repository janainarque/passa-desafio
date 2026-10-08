# Anotações

## Início

Comecei entendendo primeiro o fluxo financeiro do domínio antes de implementar os endpoints.

A principal separação que fiz foi entre:

- fatos recebidos da rede;
- estado atual do cartão e da empresa;
- histórico das movimentações financeiras.

A `Purchase` ficou como o ponto de agrupamento da authorization com captures, cancellation, issues e transactions.

No início considerei usar ULID como identificador interno, mas durante a modelagem entendi que não havia necessidade para este projeto e mantive os IDs numéricos padrão do PostgreSQL.

## Dinheiro

Todos os valores foram mantidos em centavos inteiros.

O entendimento usado durante o desenvolvimento foi:

```text
saldo da empresa = depósitos ainda não consumidos por captures

reserva = valores aprovados ainda não liberados ou capturados

disponível da empresa = saldo - reserva
```

Na authorization aprovada o dinheiro ainda não saiu da empresa, mas passa a ficar reservado.

Na capture o saldo da empresa é reduzido e a parte correspondente da reserva deixa de existir.

Na cancellation somente o valor ainda reservado é liberado.

## Transactions

Decidi registrar uma transaction sempre que uma operação altera:

```text
limite restante do cartão
saldo da empresa
saldo disponível da empresa
```

Por isso uma authorization aprovada também gera transaction, mesmo sem saída imediata de dinheiro.

Essa decisão facilitou a construção do statement e a verificação do invariante pedido pelo desafio.

## Eventos fora de ordem

Uma das partes que exigiu mais atenção foi não assumir que a authorization chega antes dos events.

Quando capture ou cancellation chegam primeiro, a compra é criada como:

```text
pending_authorization
```

O evento é armazenado sem produzir efeito financeiro naquele momento.

Quando a authorization chega, a decisão é realizada e os eventos anteriores são reconciliados.

Não considerei evento fora de ordem como issue porque o próprio contrato informa que essa ordem é válida.

## Captures

Foi necessário diferenciar o valor autorizado do valor realmente capturado.

Nos MCCs especiais:

```text
5812
7011
7512
```

o total esperado pode chegar a 120% da authorization.

Mesmo acima desse limite a capture é processada, pois representa dinheiro que a rede informa que já foi cobrado.

Quando o total ultrapassa o esperado, o sistema registra um issue em vez de rejeitar o evento.

## Casos encontrados durante os testes

Durante os testes apareceram alguns casos que não estavam evidentes no primeiro desenho.

Um deles foi receber uma capture depois de uma authorization recusada antes de existir `CardMonthBalance`.

Nesse caso, a capture ainda precisava ser registrada e produzir efeito financeiro. O fluxo foi ajustado para criar a projeção mensal quando necessário.

Outro caso foi uma nova capture depois de uma capture com:

```text
final = true
```

A reserva já deveria continuar zero e a compra não poderia voltar de `settled` para `partially_captured`.

Também revisei cancellation após authorization recusada. Se não existe reserva aberta, o fato é armazenado, mas nenhuma transaction financeira é criada.

## Validação dos events

Inicialmente as regras de campos de capture estavam juntas com as regras gerais do event.

Isso poderia rejeitar uma cancellation apenas porque ela continha campos extras relacionados a capture.

Como o contrato diz que campos não descritos devem ser ignorados, as regras de:

```text
amount_cents
currency
sequence
final
```

passaram a ser aplicadas somente quando:

```text
type = capture
```

## Concorrência

As operações financeiras foram colocadas em `DB::transaction()`.

Usei `lockForUpdate()` nas entidades que representam estado financeiro atual, principalmente compra, empresa e saldo mensal.

Também foi tratado conflito de unicidade na authorization para reduzir o risco de duas entregas simultâneas do mesmo fato aplicarem a decisão duas vezes.

## Statements

Mantive uma projeção atual para as decisões financeiras e transactions para o histórico.

No statement a ordenação escolhida foi:

```text
occurred_at
id
```

O ID funciona como desempate quando duas transactions possuem o mesmo horário.

Também foi necessário preservar o `occurred_at` recebido da rede como horário do fato, sem substituí-lo pelo horário de processamento.

## Painel administrativo

O Filament foi usado somente para a área administrativa.

Os resources financeiros ficaram voltados para consulta.

A única ação de escrita disponível é registrar depósito.

O depósito usa `CompanyDepositService`, que atualiza o saldo da empresa e registra a transaction correspondente dentro da mesma transação de banco.

O acesso ao painel é restrito à Marina.

## Área do funcionário

A autenticação foi implementada utilizando:

```php
Auth::attempt()
```

sem cadastro e sem recuperação de senha.

A rota `/my-card` não recebe card ID nem token do usuário. O cartão é obtido diretamente a partir do usuário autenticado.

Isso reduz o risco de um portador tentar acessar diretamente o cartão de outro funcionário.

Inicialmente a tela usava refresh da página a cada 5 segundos.

Depois, ao revisar novamente o requisito, percebi que a atualização precisava ocorrer sem recarregar a página.

A tela foi ajustada para utilizar Livewire com:

```text
wire:poll.5s
```

Também foi complementado o histórico da compra para carregar authorization, decision, reason, captures e cancellation.

## Testes

Além dos fluxos principais, foram adicionados testes para casos de borda encontrados durante a implementação.

Os cenários publicados P1, P2 e P3 também foram implementados como testes para comparar o comportamento do sistema com os resultados esperados.

Após os últimos ajustes funcionais, a suíte estava com:

```text
71 testes
418 assertions
```

## IA

Usei IA como apoio para discutir alternativas, revisar casos de borda e acelerar partes repetitivas da implementação.

As sugestões não foram adotadas automaticamente.

Algumas propostas foram descartadas ou simplificadas quando aumentavam a complexidade sem necessidade para o domínio, como ULID e abstrações adicionais.

As decisões que alteraram efetivamente o modelo ou o comportamento do sistema foram registradas no `MODEL.md`.

## Etapa final da entrega

Depois de fechar a implementação funcional, os próximos passos são:

```text
php artisan test
make check
```

Com a qualidade verde, a etapa final é revisar os arquivos alterados, manter os commits incrementais na branch `develop`, publicar a branch e abrir o Pull Request para `main`.
